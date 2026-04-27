<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FaceEmbedding;
use App\Services\FaceEngineClient;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class FaceController extends Controller
{
    private const MATCH_THRESHOLD = 0.8;

    public function register(Request $request, FaceEngineClient $faceEngine): JsonResponse
    {
        $payload = $request->validate([
            'user_id' => 'required|integer',
            'user_type' => 'required|string|in:siswa,guru',
            'image' => 'required|file|image',
        ]);

        try {
            $engineResponse = $faceEngine->register($request->file('image'));
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        $embedding = $engineResponse['embedding'] ?? null;
        if (!is_array($embedding) || count($embedding) !== 512) {
            return response()->json(['message' => 'Face engine returned invalid embedding.'], 422);
        }

        $normalizedEmbedding = array_map(static fn (mixed $value): float => (float) $value, $embedding);

        FaceEmbedding::updateOrCreate(
            ['user_id' => $payload['user_id'], 'user_type' => $payload['user_type']],
            ['embedding' => $normalizedEmbedding, 'created_at' => now()]
        );

        return response()->json([
            'message' => 'Face registered successfully.',
            'data' => [
                'user_id' => $payload['user_id'],
                'user_type' => $payload['user_type'],
                'embedding_dimension' => 512,
            ],
        ], 201);
    }
}
