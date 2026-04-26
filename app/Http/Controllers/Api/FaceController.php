<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\AttendanceRequest;
use App\Http\Requests\RegisterFaceRequest;
use App\Http\Controllers\Controller;
use App\Models\AttendanceLog;
use App\Models\Employee;
use App\Models\FaceEmbedding;
use App\Services\FaceEngineClient;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class FaceController extends Controller
{
    private const MATCH_THRESHOLD = 0.8;

    public function register(RegisterFaceRequest $request, FaceEngineClient $faceEngine): JsonResponse
    {
        $payload = $request->validated();

        try {
            $engineResponse = $faceEngine->register($request->file('image'));
        } catch (RuntimeException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        }

        $embedding = $engineResponse['embedding'] ?? null;

        if (! is_array($embedding) || count($embedding) === 0) {
            return response()->json([
                'message' => 'Face engine did not return embedding data.',
            ], 422);
        }

        if (count($embedding) !== 512) {
            return response()->json([
                'message' => 'Face engine returned invalid embedding dimension.',
            ], 422);
        }

        $normalizedEmbedding = array_map(
            static fn (mixed $value): float => (float) $value,
            $embedding
        );

        $employee = DB::transaction(function () use ($payload, $normalizedEmbedding): Employee {
            $employee = Employee::query()->updateOrCreate(
                ['id' => (int) $payload['employee_id']],
                ['name' => $payload['name']]
            );

            FaceEmbedding::query()->updateOrCreate(
                ['employee_id' => $employee->id],
                [
                    'embedding' => $normalizedEmbedding,
                    'created_at' => now(),
                ]
            );

            return $employee;
        });

        return response()->json([
            'message' => 'Face registered successfully.',
            'data' => [
                'employee_id' => $employee->id,
                'name' => $employee->name,
                'embedding_dimension' => count($normalizedEmbedding),
            ],
        ], 201);
    }

    public function attendance(AttendanceRequest $request, FaceEngineClient $faceEngine): JsonResponse
    {
        try {
            $engineResponse = $faceEngine->attendance($request->file('image'));
        } catch (RuntimeException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        }

        $confidence = (float) ($engineResponse['confidence'] ?? 0);
        $status = strtolower((string) ($engineResponse['status'] ?? 'unknown')) === 'matched'
            ? 'matched'
            : 'unknown';
        $employeeId = isset($engineResponse['employee_id']) ? (int) $engineResponse['employee_id'] : null;

        if ($confidence < self::MATCH_THRESHOLD) {
            $status = 'unknown';
            $employeeId = null;
        }

        $employee = $employeeId !== null ? Employee::query()->find($employeeId) : null;
        if ($employee === null) {
            $status = 'unknown';
            $employeeId = null;
        }

        $timestamp = $this->resolveTimestamp($engineResponse['timestamp'] ?? null);

        $log = AttendanceLog::query()->create([
            'employee_id' => $employeeId,
            'timestamp' => $timestamp,
            'confidence' => $confidence,
            'status' => $status,
        ]);

        return response()->json([
            'message' => 'Attendance processed successfully.',
            'data' => [
                'attendance_log_id' => $log->id,
                'status' => $status,
                'confidence' => $confidence,
                'threshold' => self::MATCH_THRESHOLD,
                'employee' => $employee === null ? null : [
                    'id' => $employee->id,
                    'name' => $employee->name,
                ],
                'timestamp' => $timestamp->toIso8601String(),
            ],
        ]);
    }

    private function resolveTimestamp(mixed $timestamp): CarbonImmutable
    {
        if (! is_string($timestamp) || trim($timestamp) === '') {
            return CarbonImmutable::now();
        }

        try {
            return CarbonImmutable::parse($timestamp);
        } catch (\Throwable) {
            return CarbonImmutable::now();
        }
    }
}
