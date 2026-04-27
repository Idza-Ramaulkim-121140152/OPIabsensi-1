<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Guru;
use App\Models\IotDevice;
use App\Models\IotRegistrationCandidate;
use App\Models\IotRegistrationSession;
use App\Models\Siswa;
use App\Services\FaceEngineClient;
use Illuminate\Http\Request;

class IotAdminController extends Controller
{
    public function devices()
    {
        return response()->json(IotDevice::orderBy('last_seen_at', 'DESC')->get());
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

    public function saveSession(Request $request, $id, FaceEngineClient $faceEngine)
    {
        $session = IotRegistrationSession::find($id);
        if (!$session) return response()->json(['message' => 'Session not found'], 404);

        $targetType = $request->input('target_type');
        $targetId = $request->input('target_id');
        $namaSiswa = $request->input('nama_siswa');
        
        $rfid = $request->input('id_rfid', $session->captured_rfid);
        $face = $request->input('foto_wajah', $session->captured_face);

        if ($targetType === 'siswa') {
            if ($targetId) {
                $siswa = Siswa::find($targetId);
                $siswa->update(['id_rfid' => $rfid, 'foto_wajah' => $face]);
            } else {
                $siswa = Siswa::create(['nama' => $namaSiswa, 'id_rfid' => $rfid, 'foto_wajah' => $face]);
                $targetId = $siswa->id;
            }
        } else {
            $guru = Guru::find($targetId);
            $guru->update(['id_rfid' => $rfid, 'foto_wajah' => $face]);
        }

        $session->update(['status' => 'assigned', 'target_type' => $targetType, 'target_id' => $targetId, 'completed_at' => now()]);
        IotDevice::where('id_device', $session->device_id)->update(['status_mode' => 'attendance', 'last_message' => 'Registrasi selesai']);

        return response()->json(['message' => 'Saved']);
    }

    public function savePemetaan(Request $request)
    {
        $candidateId = $request->input('candidate_id');
        $candidate = IotRegistrationCandidate::find($candidateId);
        if (!$candidate) return response()->json(['message' => 'Candidate not found'], 404);

        $targetType = $request->input('target_type');
        $targetId = $request->input('target_id');

        if ($targetType === 'siswa') {
            $siswa = Siswa::find($targetId);
            $siswa->update(['id_rfid' => $candidate->id_rfid, 'foto_wajah' => $candidate->foto_wajah, 'kelas' => $request->input('kelas_siswa')]);
        } else {
            $guru = Guru::find($targetId);
            $guru->update(['id_rfid' => $candidate->id_rfid, 'foto_wajah' => $candidate->foto_wajah]);
        }

        $candidate->update(['status' => 'mapped', 'mapped_target_type' => $targetType, 'mapped_target_id' => $targetId, 'mapped_at' => now()]);

        return response()->json(['message' => 'Mapped']);
    }
}
