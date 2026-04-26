<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\FaceEmbedding;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FaceApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_face_stores_employee_and_embedding(): void
    {
        config([
            'services.face_gateway.api_token' => 'test-token',
            'services.face_engine.base_url' => 'http://face-engine.local',
        ]);

        Http::fake([
            'http://face-engine.local/v1/register' => Http::response([
                'embedding' => array_fill(0, 512, 0.1234),
            ]),
        ]);

        $response = $this->withHeader('Authorization', 'Bearer test-token')
            ->post('/api/face/register', [
                'employee_id' => 10,
                'name' => 'Budi',
                'image' => UploadedFile::fake()->image('face.jpg'),
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.employee_id', 10)
            ->assertJsonPath('data.embedding_dimension', 512);

        $this->assertDatabaseHas('employees', [
            'id' => 10,
            'name' => 'Budi',
        ]);

        $embedding = FaceEmbedding::query()->first();
        $this->assertNotNull($embedding);
        $this->assertCount(512, $embedding->embedding);
    }

    public function test_attendance_marks_unknown_when_confidence_below_threshold(): void
    {
        config([
            'services.face_gateway.api_token' => 'test-token',
            'services.face_engine.base_url' => 'http://face-engine.local',
        ]);

        Employee::query()->create([
            'id' => 20,
            'name' => 'Siti',
        ]);

        Http::fake([
            'http://face-engine.local/v1/attendance' => Http::response([
                'status' => 'matched',
                'employee_id' => 20,
                'confidence' => 0.72,
                'timestamp' => '2026-04-25T05:00:00+00:00',
            ]),
        ]);

        $response = $this->withHeader('Authorization', 'Bearer test-token')
            ->post('/api/face/attendance', [
                'image' => UploadedFile::fake()->image('attendance.jpg'),
            ]);

        $response->assertOk()
            ->assertJsonPath('data.status', 'unknown')
            ->assertJsonPath('data.employee', null);

        $this->assertDatabaseHas('attendance_logs', [
            'status' => 'unknown',
            'employee_id' => null,
        ]);
    }
}
