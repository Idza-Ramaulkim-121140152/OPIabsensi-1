<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Guru;
use App\Models\IotDevice;
use App\Models\IotRegistrationSession;
use App\Models\IotScanLog;
use App\Models\JadwalMengajar;
use App\Models\Presensi;
use App\Models\Siswa;
use App\Services\FaceEngineClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class IotController extends Controller
{
    private function authorizeDevice(Request $request)
    {
        $expected = trim(config('services.iot.device_token', env('IOT_DEVICE_TOKEN', 'orange-pi-zero3-token')));
        
        if ($expected === '') {
            return response()->json(['status' => 'error', 'message' => 'Konfigurasi device_token belum diatur di server.'], 500);
        }

        $provided = trim($request->header('X-Device-Token', ''));
        if ($provided === '' || !hash_equals($expected, $provided)) {
            return response()->json(['status' => 'error', 'message' => 'Unauthorized device token.'], 401);
        }

        return null;
    }

    public function health(Request $request)
    {
        if ($authError = $this->authorizeDevice($request)) return $authError;

        return response()->json([
            'status' => 'ok',
            'message' => 'IoT endpoint ready.',
            'server_time' => date('c'),
        ]);
    }

    public function scan(Request $request, FaceEngineClient $faceEngine)
    {
        if ($authError = $this->authorizeDevice($request)) return $authError;

        $rfidUid = trim((string) $request->input('rfid_uid'));
        $image = $request->file('image');
        $hasImage = $image && $image->isValid();
        $intent = strtolower(trim((string) $request->input('intent', 'attendance')));

        if (!in_array($intent, ['attendance', 'precheck'])) {
            $intent = 'attendance';
        }

        if ($rfidUid === '' && !$hasImage) {
            return $this->jsonError(422, 'Isi minimal salah satu data: rfid_uid atau image.');
        }

        if ($rfidUid !== '' && $hasImage) {
            return $this->scanWithRfidAndFace($rfidUid, $image, $faceEngine);
        }

        if ($rfidUid !== '') {
            return $this->scanWithRfidOnly($rfidUid, $intent, $faceEngine);
        }

        $requireDualFactor = env('ATTENDANCE_REQUIRE_DUAL_FACTOR', false);
        if ($requireDualFactor) {
            $this->saveScanLog([
                'result' => 'rejected',
                'message' => 'Wajib tap kartu RFID dulu sebelum verifikasi wajah.',
                'gateway_status' => 'face_only_blocked',
            ]);
            return $this->jsonError(422, 'Wajib tap kartu RFID dulu sebelum verifikasi wajah.');
        }

        return $this->scanWithFaceOnly($image, $faceEngine);
    }

    private function scanWithRfidAndFace(string $rfidUid, $image, FaceEngineClient $faceEngine)
    {
        $identity = $this->findIdentityByRfid($rfidUid);
        if (!$identity) {
            $this->saveScanLog(['rfid_uid' => $rfidUid, 'result' => 'rejected', 'message' => 'RFID tidak terdaftar.', 'gateway_status' => 'unknown']);
            return $this->jsonError(404, 'RFID tidak terdaftar di sistem.');
        }

        try {
            $engineResponse = $faceEngine->attendance($image);
        } catch (RuntimeException $e) {
            $this->saveScanLog(['rfid_uid' => $rfidUid, 'entity_type' => $identity['type'], 'entity_id' => $identity['id'], 'result' => 'error', 'message' => $e->getMessage(), 'gateway_status' => 'error']);
            return $this->jsonError(502, 'Gateway face recognition gagal: ' . $e->getMessage());
        }

        $confidence = (float) ($engineResponse['confidence'] ?? 0);
        $matchedUserId = isset($engineResponse['user_id']) ? (int) $engineResponse['user_id'] : null;
        $matchedUserType = $engineResponse['user_type'] ?? null;
        $gatewayStatus = $engineResponse['status'] ?? 'unknown';
        
        $isVerified = false;
        if ($gatewayStatus === 'matched' && $matchedUserId === $identity['id'] && $matchedUserType === $identity['type']) {
            $isVerified = true;
        }

        $autoPresensi = null;
        if ($isVerified && $identity['type'] === 'siswa') {
            $autoPresensi = $this->recordSiswaAttendance($identity, 'rfid_face');
        }

        $message = $isVerified ? 'Verifikasi RFID + wajah berhasil.' : 'Verifikasi gagal. Wajah tidak cocok dengan kartu RFID.';
        
        $this->saveScanLog([
            'entity_type' => $identity['type'],
            'entity_id' => $identity['id'],
            'rfid_uid' => $rfidUid,
            'expected_user_id' => $identity['id'],
            'matched_user_id' => $matchedUserId,
            'gateway_status' => $gatewayStatus,
            'confidence' => $confidence,
            'result' => $isVerified ? 'verified' : 'rejected',
            'message' => $message,
            'raw_response' => json_encode($engineResponse)
        ]);

        return response()->json([
            'status' => $isVerified ? 'verified' : 'rejected',
            'auth_mode' => 'rfid_face',
            'message' => $message,
            'identity' => $identity,
            'face' => [
                'gateway_status' => $gatewayStatus,
                'confidence' => $confidence,
                'expected_user_id' => $identity['id'],
                'matched_user_id' => $matchedUserId,
            ],
            'presensi' => $autoPresensi,
            'server_time' => date('c'),
        ], $isVerified ? 200 : 422);
    }

    private function scanWithRfidOnly(string $rfidUid, string $intent, FaceEngineClient $faceEngine)
    {
        $identity = $this->findIdentityByRfid($rfidUid);
        if (!$identity) {
            $this->saveScanLog(['rfid_uid' => $rfidUid, 'result' => 'rejected', 'message' => 'RFID tidak terdaftar.', 'gateway_status' => 'rfid_only']);
            return $this->jsonError(404, 'RFID tidak terdaftar di sistem.');
        }

        $isPrecheck = $intent === 'precheck' || env('ATTENDANCE_REQUIRE_DUAL_FACTOR', false);
        $message = $isPrecheck ? 'RFID valid. Lanjutkan verifikasi wajah.' : 'Verifikasi RFID berhasil.';
        
        $autoPresensi = null;
        if (!$isPrecheck && $identity['type'] === 'siswa') {
            $autoPresensi = $this->recordSiswaAttendance($identity, 'rfid_only');
        }

        $this->saveScanLog([
            'entity_type' => $identity['type'],
            'entity_id' => $identity['id'],
            'rfid_uid' => $rfidUid,
            'result' => 'verified',
            'message' => $message,
            'gateway_status' => $isPrecheck ? 'rfid_precheck' : 'rfid_only',
        ]);

        return response()->json([
            'status' => 'verified',
            'auth_mode' => $isPrecheck ? 'rfid_precheck' : 'rfid_only',
            'message' => $message,
            'identity' => $identity,
            'presensi' => $autoPresensi,
            'requires_face' => $isPrecheck,
            'server_time' => date('c'),
        ]);
    }

    private function scanWithFaceOnly($image, FaceEngineClient $faceEngine)
    {
        try {
            $engineResponse = $faceEngine->attendance($image);
        } catch (RuntimeException $e) {
            $this->saveScanLog(['result' => 'error', 'message' => $e->getMessage(), 'gateway_status' => 'error']);
            return $this->jsonError(502, 'Gateway face recognition gagal: ' . $e->getMessage());
        }

        $confidence = (float) ($engineResponse['confidence'] ?? 0);
        $matchedUserId = isset($engineResponse['user_id']) ? (int) $engineResponse['user_id'] : null;
        $matchedUserType = $engineResponse['user_type'] ?? null;
        $gatewayStatus = $engineResponse['status'] ?? 'unknown';

        $identity = null;
        if ($gatewayStatus === 'matched' && $matchedUserId && $matchedUserType) {
            $identity = $this->findIdentityByIdAndType($matchedUserId, $matchedUserType);
        }

        $isVerified = $identity !== null;
        $autoPresensi = null;
        if ($isVerified && $identity['type'] === 'siswa') {
            $autoPresensi = $this->recordSiswaAttendance($identity, 'face_only');
        }

        $message = $isVerified ? 'Verifikasi wajah berhasil.' : 'Verifikasi wajah gagal. Wajah tidak dikenal.';

        $this->saveScanLog([
            'entity_type' => $identity ? $identity['type'] : null,
            'entity_id' => $identity ? $identity['id'] : null,
            'matched_user_id' => $matchedUserId,
            'gateway_status' => $gatewayStatus,
            'confidence' => $confidence,
            'result' => $isVerified ? 'verified' : 'rejected',
            'message' => $message,
            'raw_response' => json_encode($engineResponse)
        ]);

        return response()->json([
            'status' => $isVerified ? 'verified' : 'rejected',
            'auth_mode' => 'face_only',
            'message' => $message,
            'identity' => $identity,
            'face' => [
                'gateway_status' => $gatewayStatus,
                'confidence' => $confidence,
                'matched_user_id' => $matchedUserId,
            ],
            'presensi' => $autoPresensi,
            'server_time' => date('c'),
        ], $isVerified ? 200 : 422);
    }

    public function deviceHeartbeat(Request $request)
    {
        if ($authError = $this->authorizeDevice($request)) return $authError;

        return $this->handleDevicePing($request, 'post');
    }

    public function deviceCommand(Request $request)
    {
        if ($authError = $this->authorizeDevice($request)) return $authError;

        return $this->handleDevicePing($request, 'get');
    }

    private function handleDevicePing(Request $request, string $method)
    {
        $deviceCode = $this->sanitizeDeviceCode($request->input('device_code'));
        if ($deviceCode === '') return $this->jsonError(422, 'Field device_code wajib diisi.');

        $device = IotDevice::updateOrCreate(
            ['device_code' => $deviceCode],
            [
                'device_name' => $request->input('device_name', $deviceCode),
                'status_mode' => strtolower($request->input('mode', 'attendance')),
                'last_seen_at' => now(),
                'last_ip' => $request->ip(),
                'last_message' => $request->input('message'),
                'firmware_version' => $request->input('firmware_version'),
            ]
        );

        $this->expireStaleRegisterSessions($device->id_device);
        $pending = $this->resolvePendingRegisterSession($device->id_device);

        return response()->json([
            'status' => 'ok',
            'device' => [
                'device_code' => $device->device_code,
                'mode' => $device->status_mode,
                'last_seen_at' => $device->last_seen_at->toDateTimeString(),
            ],
            'mode' => $pending ? 'register' : 'attendance',
            'register_session' => $pending,
            'server_time' => date('c'),
        ]);
    }

    public function registerCapture(Request $request)
    {
        if ($authError = $this->authorizeDevice($request)) return $authError;

        $deviceCode = $this->sanitizeDeviceCode($request->input('device_code'));
        $rfidUid = trim((string) $request->input('rfid_uid'));
        $sessionToken = trim((string) $request->input('session_token'));
        $image = $request->file('image');

        if ($deviceCode === '' || $rfidUid === '' || $sessionToken === '' || !$image || !$image->isValid()) {
            return $this->jsonError(422, 'Data tidak lengkap atau image invalid.');
        }

        $device = IotDevice::where('device_code', $deviceCode)->first();
        if (!$device) return $this->jsonError(404, 'Device tidak terdaftar.');

        $session = IotRegistrationSession::where('device_id', $device->id_device)->where('session_token', $sessionToken)->first();
        if (!$session) return $this->jsonError(404, 'Session tidak ditemukan.');
        if ($session->status !== 'waiting_device') return $this->jsonError(409, 'Capture sudah ada.');

        $faceData = 'data:' . $image->getClientMimeType() . ';base64,' . base64_encode(file_get_contents($image->getRealPath()));

        $session->update([
            'status' => 'captured',
            'captured_rfid' => $rfidUid,
            'captured_face' => $faceData,
            'captured_at' => now(),
        ]);

        $device->update(['status_mode' => 'register', 'last_seen_at' => now(), 'last_message' => 'Capture diterima.']);

        return response()->json([
            'status' => 'captured',
            'session' => [
                'id_session' => $session->id_session,
                'session_token' => $sessionToken,
                'rfid_uid' => $rfidUid,
            ],
            'server_time' => date('c'),
        ]);
    }

    private function findIdentityByRfid(string $rfidUid)
    {
        $siswa = Siswa::where('id_rfid', $rfidUid)->first();
        if ($siswa) return ['type' => 'siswa', 'id' => $siswa->id, 'name' => $siswa->nama, 'kelas' => $siswa->kelas, 'rfid_uid' => $siswa->id_rfid];
        
        $guru = Guru::where('id_rfid', $rfidUid)->first();
        if ($guru) return ['type' => 'guru', 'id' => $guru->id_guru, 'name' => $guru->nama, 'kelas' => null, 'rfid_uid' => $guru->id_rfid];

        return null;
    }

    private function findIdentityByIdAndType(int $id, string $type)
    {
        if ($type === 'siswa') {
            $siswa = Siswa::find($id);
            if ($siswa) return ['type' => 'siswa', 'id' => $siswa->id, 'name' => $siswa->nama, 'kelas' => $siswa->kelas, 'rfid_uid' => $siswa->id_rfid];
        } elseif ($type === 'guru') {
            $guru = Guru::find($id);
            if ($guru) return ['type' => 'guru', 'id' => $guru->id_guru, 'name' => $guru->nama, 'kelas' => null, 'rfid_uid' => $guru->id_rfid];
        }
        return null;
    }

    private function recordSiswaAttendance(array $identity, string $authMode)
    {
        $kelas = $identity['kelas'];
        $hariIni = $this->hariIndonesia(date('l'));
        $jamSekarang = date('H:i:s');
        $tanggal = date('Y-m-d');

        $jadwal = JadwalMengajar::where('kelas', $kelas)
            ->where('hari', $hariIni)
            ->whereTime('jam_mulai', '<=', $jamSekarang)
            ->whereTime('jam_selesai', '>=', $jamSekarang)
            ->first();

        $catatan = 'Validasi IoT ' . $authMode;

        if ($jadwal) {
            $presensi = Presensi::updateOrCreate(
                ['id_siswa' => $identity['id'], 'tanggal' => $tanggal, 'id_jadwal' => $jadwal->id_jadwal],
                ['id_guru' => $jadwal->id_guru, 'kelas' => $kelas, 'jam' => $jamSekarang, 'metode' => 'iot', 'catatan' => $catatan]
            );
            return ['saved' => true, 'id_presensi' => $presensi->id_presensi];
        }

        $guru = Guru::where('is_wali_kelas', 1)->where('kelas_wali', $kelas)->first();
        if ($guru) {
            $presensi = Presensi::updateOrCreate(
                ['id_siswa' => $identity['id'], 'tanggal' => $tanggal, 'id_jadwal' => null],
                ['id_guru' => $guru->id_guru, 'kelas' => $kelas, 'jam' => $jamSekarang, 'metode' => 'iot', 'catatan' => $catatan . ' (tanpa jadwal aktif)']
            );
            return ['saved' => true, 'id_presensi' => $presensi->id_presensi];
        }

        return ['saved' => false, 'reason' => 'Tidak ada jadwal atau wali kelas.'];
    }

    private function saveScanLog(array $payload)
    {
        $payload['request_time'] = now();
        IotScanLog::create($payload);
    }

    private function jsonError(int $status, string $message)
    {
        return response()->json(['status' => 'error', 'message' => $message], $status);
    }

    private function sanitizeDeviceCode($raw)
    {
        return preg_replace('/[^A-Za-z0-9._-]/', '', (string)$raw);
    }

    private function expireStaleRegisterSessions($deviceId)
    {
        IotRegistrationSession::where('device_id', $deviceId)
            ->whereIn('status', ['waiting_device', 'captured'])
            ->where('created_at', '<', now()->subMinutes(5))
            ->update(['status' => 'expired', 'error_message' => 'Timeout']);
    }

    private function resolvePendingRegisterSession($deviceId)
    {
        $session = IotRegistrationSession::where('device_id', $deviceId)
            ->whereIn('status', ['waiting_device', 'captured'])
            ->latest('id_session')->first();

        if (!$session) return null;

        return [
            'id_session' => $session->id_session,
            'session_token' => $session->session_token,
            'status' => $session->status,
        ];
    }

    private function hariIndonesia(string $englishDay)
    {
        $map = ['Monday' => 'Senin', 'Tuesday' => 'Selasa', 'Wednesday' => 'Rabu', 'Thursday' => 'Kamis', 'Friday' => 'Jumat', 'Saturday' => 'Sabtu', 'Sunday' => 'Minggu'];
        return $map[$englishDay] ?? $englishDay;
    }
}
