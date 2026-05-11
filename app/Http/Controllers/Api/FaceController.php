<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FaceEmbedding;
use App\Models\Guru;
use App\Models\Siswa;
use App\Services\FaceRegistrationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class FaceController extends Controller
{
    public function register(Request $request, FaceRegistrationService $faceRegistration): JsonResponse
    {
        $payload = $request->validate([
            'user_id' => 'required|integer',
            'user_type' => 'required|string|in:siswa,guru',
            'image' => 'required|file|image',
        ]);

        try {
            $data = $faceRegistration->register(
                $payload['user_type'],
                (int) $payload['user_id'],
                $request->file('image')
            );
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Face registered successfully.',
            'data' => $data,
        ], 201);
    }

    public function landmark(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'user_id' => 'required|integer|min:1',
            'user_type' => 'required|string|in:siswa,guru',
        ]);

        $embedding = FaceEmbedding::query()
            ->where('user_id', (int) $payload['user_id'])
            ->where('user_type', $payload['user_type'])
            ->latest('id')
            ->first();

        if (! $embedding) {
            return response()->json([
                'message' => 'Data wajah belum tersedia.',
                'data' => [
                    'user_id' => (int) $payload['user_id'],
                    'user_type' => (string) $payload['user_type'],
                    'has_landmark' => false,
                    'landmark_status' => 'missing',
                    'landmark_count' => 0,
                    'landmarks' => [],
                'image_size' => ['width' => null, 'height' => null],
                'real_image_url' => null,
                'vector_image_url' => null,
                'overlay_image_url' => null,
            ],
        ], 200);
        }

        $landmarks = is_array($embedding->landmarks) ? $embedding->landmarks : [];
        $landmarkCount = count($landmarks);
        $vectorImagePath = trim((string) ($embedding->vector_image_path ?? ''));
        $overlayImagePath = trim((string) ($embedding->overlay_image_path ?? ''));
        $vectorImageUrl = $this->resolveVectorImageDataUri($vectorImagePath);
        $overlayImageUrl = $this->resolveVectorImageDataUri($overlayImagePath);
        $hasLandmark = $landmarkCount > 0 && $vectorImageUrl !== null && $overlayImageUrl !== null;
        $landmarkStatus = $landmarkCount === 0 ? 'missing' : ($hasLandmark ? 'valid' : 'invalid');
        $message = match ($landmarkStatus) {
            'valid' => 'Landmark wajah berhasil diambil.',
            'invalid' => 'Landmark tidak valid.',
            default => 'Belum ada landmark.',
        };
        $realImageUrl = $this->resolveRealImageUrl((string) $embedding->user_type, (int) $embedding->user_id);

        return response()->json([
            'message' => $message,
            'data' => [
                'user_id' => (int) $embedding->user_id,
                'user_type' => (string) $embedding->user_type,
                'has_landmark' => $hasLandmark,
                'landmark_status' => $landmarkStatus,
                'landmark_count' => $landmarkCount,
                'landmarks' => $landmarks,
                'image_size' => [
                    'width' => $embedding->image_width !== null ? (int) $embedding->image_width : null,
                    'height' => $embedding->image_height !== null ? (int) $embedding->image_height : null,
                ],
                'real_image_url' => $realImageUrl,
                'vector_image_url' => $vectorImageUrl,
                'overlay_image_url' => $overlayImageUrl,
            ],
        ]);
    }

    private function resolveRealImageUrl(string $userType, int $userId): ?string
    {
        if ($userType === 'siswa') {
            $user = Siswa::query()->find($userId);
            return $this->normalizeImageUrl((string) ($user->foto_wajah ?? ''));
        }

        if ($userType === 'guru') {
            $user = Guru::query()->find($userId);
            return $this->normalizeImageUrl((string) ($user->foto_wajah ?? ''));
        }

        return null;
    }

    private function normalizeImageUrl(string $raw): ?string
    {
        $value = trim($raw);
        if ($value === '') {
            return null;
        }
        if (str_starts_with($value, 'data:image/')) {
            return $value;
        }
        if (preg_match('#^https?://#i', $value) === 1) {
            return $value;
        }
        return asset(ltrim($value, '/'));
    }

    private function resolveVectorImageDataUri(string $vectorImagePath): ?string
    {
        $path = trim($vectorImagePath);
        if ($path === '') {
            return null;
        }

        $disk = Storage::disk('public');
        if (! $disk->exists($path)) {
            return null;
        }

        $content = (string) $disk->get($path);
        if ($content === '') {
            return null;
        }

        $mime = (string) $disk->mimeType($path);
        if ($mime === '') {
            $mime = 'image/svg+xml';
        }

        return 'data:' . $mime . ';base64,' . base64_encode($content);
    }
}
