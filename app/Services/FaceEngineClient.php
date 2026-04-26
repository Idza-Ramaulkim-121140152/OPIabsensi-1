<?php

namespace App\Services;

use Illuminate\Http\Client\RequestException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class FaceEngineClient
{
    public function register(UploadedFile $image): array
    {
        return $this->sendImage('/v1/register', $image);
    }

    public function attendance(UploadedFile $image): array
    {
        return $this->sendImage('/v1/attendance', $image);
    }

    private function sendImage(string $path, UploadedFile $image): array
    {
        $baseUrl = rtrim((string) config('services.face_engine.base_url'), '/');
        $timeout = (float) config('services.face_engine.timeout', 2.0);
        $token = (string) config('services.face_engine.token');

        if ($baseUrl === '') {
            throw new RuntimeException('FACE_ENGINE_BASE_URL is not configured.');
        }

        $request = Http::acceptJson()->timeout($timeout);

        if ($token !== '') {
            $request = $request->withToken($token);
        }

        try {
            $response = $request
                ->attach(
                    'image',
                    file_get_contents($image->getRealPath()),
                    $image->getClientOriginalName()
                )
                ->post($baseUrl.$path);
        } catch (\Throwable $exception) {
            throw new RuntimeException(
                'Face engine is unreachable: '.$exception->getMessage(),
                previous: $exception
            );
        }

        try {
            $response->throw();
        } catch (RequestException $exception) {
            $errorDetail = $response->json('detail');
            $message = is_string($errorDetail) && $errorDetail !== ''
                ? $errorDetail
                : 'Face engine rejected request.';

            throw new RuntimeException($message, previous: $exception);
        }

        $payload = $response->json();

        if (! is_array($payload)) {
            throw new RuntimeException('Invalid response payload from face engine.');
        }

        return $payload;
    }
}
