<?php

use App\Models\FaceEmbedding;
use App\Models\Guru;
use App\Models\Siswa;
use App\Services\FaceRegistrationService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('face:backfill-embeddings {--type=all : siswa, guru, or all} {--force : Regenerate all embeddings including valid landmarks}', function () {
    $type = strtolower((string) $this->option('type'));
    $force = (bool) $this->option('force');

    if (! in_array($type, ['all', 'siswa', 'guru'], true)) {
        $this->error('Option --type hanya boleh bernilai all, siswa, atau guru.');
        return 1;
    }

    $faceRegistration = app(FaceRegistrationService::class);
    $totals = ['processed' => 0, 'registered' => 0, 'skipped' => 0, 'failed' => 0];
    $failedReasons = [];
    $expectedLandmarks = 1;

    $backfill = function (string $userType, $query, string $primaryKey) use (
        $faceRegistration,
        $force,
        $expectedLandmarks,
        &$totals,
        &$failedReasons
    ): void {
        $query->whereNotNull('foto_wajah')
            ->where('foto_wajah', '<>', '')
            ->orderBy($primaryKey)
            ->chunk(50, function ($rows) use (
                $userType,
                $primaryKey,
                $faceRegistration,
                $force,
                $expectedLandmarks,
                &$totals,
                &$failedReasons
            ): void {
                foreach ($rows as $row) {
                    $userId = (int) $row->{$primaryKey};
                    $totals['processed']++;
                    try {
                        Cache::store(config('cache.default'))->increment('backfill_processed');
                    } catch (\Throwable) {
                        // best effort metric counter
                    }

                    $existing = FaceEmbedding::where('user_type', $userType)->where('user_id', $userId)->first();
                    $landmarkCount = ($existing && is_array($existing->landmarks)) ? count($existing->landmarks) : 0;
                    $hasVector = $existing && is_string($existing->vector_image_path) && trim($existing->vector_image_path) !== '';
                    $hasOverlay = $existing && is_string($existing->overlay_image_path) && trim($existing->overlay_image_path) !== '';
                    $hasValidLandmark = $landmarkCount >= $expectedLandmarks && $hasVector && $hasOverlay;
                    if ($existing && $hasValidLandmark && ! $force) {
                        $totals['skipped']++;
                        $this->line("skip {$userType}#{$userId}: vector valid sudah ada");
                        continue;
                    }

                    try {
                        $result = $faceRegistration->register($userType, $userId, $row->foto_wajah);
                        $totals['registered']++;
                        Log::info('face.backfill.registered', [
                            'user_type' => $userType,
                            'user_id' => $userId,
                            'landmark_count' => (int) ($result['landmark_count'] ?? 0),
                            'image_size' => $result['image_size'] ?? null,
                            'vector_image_path' => $result['vector_image_path'] ?? null,
                            'overlay_image_path' => $result['overlay_image_path'] ?? null,
                        ]);
                        $this->info("ok {$userType}#{$userId}: vector tersimpan");
                    } catch (\RuntimeException $exception) {
                        $totals['failed']++;
                        $reason = $exception->getMessage();
                        $failedReasons[$reason] = ($failedReasons[$reason] ?? 0) + 1;
                        Log::warning('face.backfill.failed', [
                            'user_type' => $userType,
                            'user_id' => $userId,
                            'reason_if_failed' => $reason,
                        ]);
                        $this->warn("gagal {$userType}#{$userId}: {$reason}");
                    }
                }
            });
    };

    if ($type === 'all' || $type === 'siswa') {
        $backfill('siswa', Siswa::query(), 'id');
    }

    if ($type === 'all' || $type === 'guru') {
        $backfill('guru', Guru::query(), 'id_guru');
    }

    $this->newLine();
    $this->info("Selesai. processed={$totals['processed']} registered={$totals['registered']} skipped={$totals['skipped']} failed={$totals['failed']}");
    if ($totals['failed'] > 0) {
        $this->line('Rangkuman gagal:');
        foreach ($failedReasons as $reason => $count) {
            $this->line("- {$count}x {$reason}");
        }
    }

    return $totals['failed'] > 0 ? 1 : 0;
})->purpose('Generate or refresh face vectors (embedding + landmarks) from stored siswa/guru photos');
