<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\IotDevice;
use App\Models\IotRegistrationCandidate;
use App\Models\IotRegistrationSession;
use App\Models\Siswa;
use App\Services\FaceRegistrationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class IotAdminController extends Controller
{
    public function devices()
    {
        $windowSec = max(20, (int) env('IOT_DEVICE_ONLINE_WINDOW_SEC', 45));
        $now = now()->getTimestamp();
        
        $devices = IotDevice::orderBy('last_seen_at', 'DESC')
            ->get()
            ->map(function ($device) use ($now, $windowSec) {
                $lastSeenTs = false;
                if ($device->last_seen_at) {
                    $lastSeenDt = is_string($device->last_seen_at) 
                        ? \Carbon\Carbon::parse($device->last_seen_at)
                        : $device->last_seen_at;
                    $lastSeenTs = $lastSeenDt->getTimestamp();
                }
                
                $isOnline = $lastSeenTs !== false && ($now - $lastSeenTs) <= $windowSec;
                $lastSeenDt = $device->last_seen_at 
                    ? (is_string($device->last_seen_at) ? \Carbon\Carbon::parse($device->last_seen_at) : $device->last_seen_at)
                    : null;
                
                return [
                    'id_device' => $device->id_device,
                    'device_code' => $device->device_code,
                    'device_name' => $device->device_name,
                    'device_type' => $device->device_type,
                    'status_mode' => $device->status_mode,
                    'last_seen_at' => $lastSeenDt?->toDateTimeString(),
                    'last_seen_human' => $lastSeenDt ? $lastSeenDt->diffForHumans() : '-',
                    'last_ip' => $device->last_ip,
                    'last_message' => $device->last_message,
                    'firmware_version' => $device->firmware_version,
                    'is_online' => $isOnline,
                    'created_at' => $device->created_at?->toDateTimeString(),
                    'updated_at' => $device->updated_at?->toDateTimeString(),
                ];
            });
        
        return response()->json($devices);
    }

    public function sessions()
    {
        return response()->json(IotRegistrationSession::orderBy('id_session', 'DESC')->get());
    }

    public function candidates()
    {
        return response()->json(IotRegistrationCandidate::orderBy('id_candidate', 'DESC')->get());
    }

    public function startSession(Request $request)
    {
        $deviceId = $request->input('device_id');
        $device = IotDevice::find($deviceId);
        if (!$device) return response()->json(['message' => 'Device not found'], 404);

        $session = IotRegistrationSession::create([
            'device_id' => $deviceId,
            'session_token' => bin2hex(random_bytes(20)),
            'status' => 'waiting_device',
            'command_issued_at' => now(),
        ]);

        $device->update(['status_mode' => 'register', 'last_message' => 'Menunggu capture registrasi...']);

        return response()->json($session);
    }

    public function cancelSession($id)
    {
        $session = IotRegistrationSession::find($id);
        if (!$session) return response()->json(['message' => 'Not found'], 404);

        $session->update(['status' => 'cancelled', 'error_message' => 'Dibatalkan oleh admin', 'completed_at' => now()]);
        IotDevice::where('id_device', $session->device_id)->update(['status_mode' => 'attendance', 'last_message' => 'Mode registrasi ditutup']);

        return response()->json(['message' => 'Cancelled']);
    }

    public function saveSession(Request $request, $id, FaceRegistrationService $faceRegistration)
    {
        $session = IotRegistrationSession::find($id);
        if (!$session) return response()->json(['message' => 'Session not found'], 404);

        $targetType = $request->input('target_type');
        $targetId = $request->input('target_id');
        $namaSiswa = $request->input('nama_siswa');
        if ($targetType !== 'siswa') {
            return response()->json(['message' => 'Registrasi RFID/wajah hanya untuk siswa.'], 422);
        }
        
        $rfid = $request->input('id_rfid', $session->captured_rfid);
        $face = $request->input('foto_wajah', $session->captured_face);
        if (empty($face)) {
            return response()->json(['message' => 'Foto wajah wajib tersedia untuk menyimpan registrasi.'], 422);
        }

        try {
            DB::transaction(function () use (
                $targetType,
                &$targetId,
                $namaSiswa,
                $rfid,
                $face,
                $faceRegistration
            ): void {
                if ($targetType === 'siswa') {
                    if ($targetId) {
                        $siswa = Siswa::find($targetId);
                        if (! $siswa) {
                            throw new RuntimeException('Siswa not found');
                        }
                        $siswa->update(['id_rfid' => $rfid, 'foto_wajah' => $face]);
                    } else {
                        $siswa = Siswa::create(['nama' => $namaSiswa, 'id_rfid' => $rfid, 'foto_wajah' => $face]);
                        $targetId = $siswa->id;
                    }
                    $faceRegistration->register('siswa', (int) $targetId, $face);
                    return;
                }

                throw new RuntimeException('Registrasi RFID/wajah hanya untuk siswa.');
            });
        } catch (RuntimeException $exception) {
            $message = $exception->getMessage();
            $status = in_array($message, ['Siswa not found', 'Guru not found'], true) ? 404 : 422;
            return response()->json(['message' => $message], $status);
        } catch (\Throwable $exception) {
            return response()->json(['message' => 'Gagal menyimpan registrasi wajah.'], 422);
        }

        $session->update(['status' => 'assigned', 'target_type' => $targetType, 'target_id' => $targetId, 'completed_at' => now()]);
        IotDevice::where('id_device', $session->device_id)->update(['status_mode' => 'attendance', 'last_message' => 'Registrasi selesai']);

        return response()->json(['message' => 'Saved']);
    }

    public function savePemetaan(Request $request, FaceRegistrationService $faceRegistration)
    {
        $candidateId = $request->input('candidate_id');
        $candidate = IotRegistrationCandidate::find($candidateId);
        if (!$candidate) return response()->json(['message' => 'Candidate not found'], 404);

        $targetType = $request->input('target_type');
        $targetId = $request->input('target_id');
        if ($targetType !== 'siswa') {
            return response()->json(['message' => 'Pemetaan RFID/wajah hanya untuk siswa.'], 422);
        }
        if (empty($candidate->foto_wajah)) {
            return response()->json(['message' => 'Candidate belum memiliki foto wajah untuk dipetakan.'], 422);
        }

        if ($targetType === 'siswa') {
            $siswa = Siswa::find($targetId);
            if (!$siswa) return response()->json(['message' => 'Siswa not found'], 404);
            $siswa->update(['id_rfid' => $candidate->id_rfid, 'foto_wajah' => $candidate->foto_wajah, 'kelas' => $request->input('kelas_siswa')]);

            try {
                if (! empty($candidate->foto_wajah)) {
                    $faceRegistration->register('siswa', (int) $siswa->id, $candidate->foto_wajah);
                }
            } catch (RuntimeException $exception) {
                return response()->json(['message' => $exception->getMessage()], 422);
            }
        }

        $candidate->update(['status' => 'mapped', 'mapped_target_type' => $targetType, 'mapped_target_id' => $targetId, 'mapped_at' => now()]);

        return response()->json(['message' => 'Mapped']);
    }
}
