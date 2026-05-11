<?php

namespace App\Services;

use App\Models\FaceEmbedding;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class FaceRegistrationService
{
    public function __construct(private readonly FaceEngineClient $faceEngine)
    {
    }

    public function register(string $userType, int $userId, UploadedFile|string|null $image): array
    {
        if (! in_array($userType, ['siswa', 'guru'], true)) {
            throw new RuntimeException('Tipe user wajah tidak valid.');
        }

        [$contents, $filename] = $this->resolveImage($image);
        $engineResponse = $this->faceEngine->registerImageData($contents, $filename);

        $embedding = $engineResponse['embedding'] ?? null;
        if (! is_array($embedding) || count($embedding) !== 512) {
            throw new RuntimeException('Face engine returned invalid embedding.');
        }

        $normalizedEmbedding = array_map(static fn (mixed $value): float => (float) $value, $embedding);
        $landmarks = $this->normalizeLandmarks($engineResponse['landmarks'] ?? null);
        $landmarks = $this->expandLandmarks($landmarks, 10);
        $imageSize = $this->normalizeImageSize($engineResponse['image_size'] ?? null);
        $landmarkCount = count($landmarks);
        if ($landmarkCount < 1) {
            $this->incrementCounter('registration_landmark_failed');
            Log::warning('face.registration.invalid_landmark', [
                'user_type' => $userType,
                'user_id' => $userId,
                'landmark_count' => $landmarkCount,
                'image_size' => $imageSize,
                'reason_if_failed' => 'empty_landmark',
            ]);
            throw new RuntimeException('Landmark wajah kosong. Silakan ulangi registrasi dengan wajah lebih jelas.');
        }
        $vectorImagePath = $this->buildVectorImage($userType, $userId, $contents, $landmarks, $imageSize);
        $overlayImagePath = $this->buildOverlayImage($userType, $userId, $contents, $landmarks, $imageSize);
        $vectorImageUrl = $vectorImagePath !== null ? Storage::disk('public')->url($vectorImagePath) : null;
        $overlayImageUrl = $overlayImagePath !== null ? Storage::disk('public')->url($overlayImagePath) : null;

        FaceEmbedding::updateOrCreate(
            ['user_id' => $userId, 'user_type' => $userType],
            [
                'embedding' => $normalizedEmbedding,
                'landmarks' => $landmarks,
                'landmark_count' => $landmarkCount,
                'image_width' => $imageSize['width'],
                'image_height' => $imageSize['height'],
                'vector_image_path' => $vectorImagePath,
                'overlay_image_path' => $overlayImagePath,
            ]
        );
        $this->incrementCounter('registration_landmark_success');
        Log::info('face.registration.saved', [
            'user_type' => $userType,
            'user_id' => $userId,
            'landmark_count' => $landmarkCount,
            'image_size' => $imageSize,
            'vector_image_path' => $vectorImagePath,
            'overlay_image_path' => $overlayImagePath,
        ]);

        return [
            'user_id' => $userId,
            'user_type' => $userType,
            'embedding_dimension' => 512,
            'landmark_count' => $landmarkCount,
            'image_size' => $imageSize,
            'vector_image_path' => $vectorImagePath,
            'vector_image_url' => $vectorImageUrl,
            'overlay_image_path' => $overlayImagePath,
            'overlay_image_url' => $overlayImageUrl,
        ];
    }

    private function normalizeLandmarks(mixed $raw): array
    {
        if (! is_array($raw)) {
            return [];
        }

        $points = [];
        foreach ($raw as $item) {
            if (! is_array($item) || ! isset($item['x'], $item['y'])) {
                continue;
            }
            $points[] = [
                'x' => (float) $item['x'],
                'y' => (float) $item['y'],
            ];
        }

        return $points;
    }

    /**
     * @return array{width:?int,height:?int}
     */
    private function normalizeImageSize(mixed $raw): array
    {
        if (! is_array($raw)) {
            return ['width' => null, 'height' => null];
        }

        $width = isset($raw['width']) ? (int) $raw['width'] : null;
        $height = isset($raw['height']) ? (int) $raw['height'] : null;

        return [
            'width' => ($width !== null && $width > 0) ? $width : null,
            'height' => ($height !== null && $height > 0) ? $height : null,
        ];
    }

    private function resolveImage(UploadedFile|string|null $image): array
    {
        if ($image instanceof UploadedFile) {
            if (! $image->isValid()) {
                throw new RuntimeException('File wajah tidak valid.');
            }

            $contents = file_get_contents($image->getRealPath());
            if ($contents === false || $contents === '') {
                throw new RuntimeException('File wajah kosong atau tidak bisa dibaca.');
            }

            return [$contents, $image->getClientOriginalName() ?: 'face.jpg'];
        }

        $raw = trim((string) $image);
        if ($raw === '') {
            throw new RuntimeException('Data wajah kosong.');
        }

        if (preg_match('/^data:([^;]+);base64,(.*)$/s', $raw, $matches) === 1) {
            $decoded = base64_decode($matches[2], true);
            if ($decoded === false || $decoded === '') {
                throw new RuntimeException('Data wajah base64 tidak valid.');
            }

            return [$decoded, $this->filenameFromMime($matches[1])];
        }

        $decoded = base64_decode($raw, true);
        if ($decoded !== false && $decoded !== '') {
            return [$decoded, 'face.jpg'];
        }

        $path = $this->resolveLocalPath($raw);
        if ($path !== null) {
            $contents = file_get_contents($path);
            if ($contents === false || $contents === '') {
                throw new RuntimeException('File wajah kosong atau tidak bisa dibaca.');
            }

            return [$contents, basename($path) ?: 'face.jpg'];
        }

        throw new RuntimeException('Format data wajah tidak dikenali.');
    }

    private function resolveLocalPath(string $raw): ?string
    {
        $candidates = [];

        if (str_starts_with($raw, '/')) {
            $candidates[] = $raw;
        } else {
            $clean = ltrim($raw, '/');
            $candidates[] = storage_path('app/public/'.$clean);
            $candidates[] = public_path($clean);
            $candidates[] = base_path($clean);
        }

        foreach ($candidates as $candidate) {
            if (is_file($candidate) && is_readable($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    private function filenameFromMime(string $mime): string
    {
        return match (strtolower($mime)) {
            'image/png' => 'face.png',
            'image/webp' => 'face.webp',
            default => 'face.jpg',
        };
    }

    private function incrementCounter(string $key): void
    {
        try {
            Cache::store(config('cache.default'))->increment($key);
        } catch (\Throwable) {
            // best effort metric counter
        }
    }

    /**
     * @param array<int, array{x:float,y:float}> $landmarks
     * @param array{width:?int,height:?int} $imageSize
     */
    private function buildVectorImage(
        string $userType,
        int $userId,
        string $sourceImageBytes,
        array $landmarks,
        array $imageSize
    ): ?string {
        $width = (int) ($imageSize['width'] ?? 0);
        $height = (int) ($imageSize['height'] ?? 0);
        if ($width <= 0 || $height <= 0) {
            return null;
        }

        // keep source bytes argument intentionally for signature compatibility
        if ($sourceImageBytes === '') {
            return null;
        }

        if ($width <= 0 || $height <= 0) {
            return null;
        }

        $points = [];
        foreach ($landmarks as $point) {
            $x = (float) ($point['x'] ?? 0.0);
            $y = (float) ($point['y'] ?? 0.0);
            if ($x < 0 || $y < 0 || $x > $width || $y > $height) {
                continue;
            }
            $points[] = ['x' => $x, 'y' => $y];
        }
        if (count($points) < 1) {
            return null;
        }

        $linesSvg = '';
        $pointsSvg = '';
        $edgeKeys = [];

        if (count($points) >= 3) {
            $triangles = $this->triangulateDelaunay($points);
            foreach ($triangles as $tri) {
                [$a, $b, $c] = $tri;
                $pa = $points[$a];
                $pb = $points[$b];
                $pc = $points[$c];
                if (! $this->isReasonableTriangle($pa, $pb, $pc, $width, $height)) {
                    continue;
                }
                $edgeKeys[$this->edgeKey($a, $b)] = [$a, $b];
                $edgeKeys[$this->edgeKey($b, $c)] = [$b, $c];
                $edgeKeys[$this->edgeKey($c, $a)] = [$c, $a];
            }
        }

        if (count($edgeKeys) === 0 && count($points) >= 2) {
            $edgeKeys = $this->nearestNeighborEdges($points, 2);
        }

        foreach ($edgeKeys as [$u, $v]) {
            $pu = $points[$u];
            $pv = $points[$v];
            $linesSvg .= sprintf(
                '<line x1="%0.2f" y1="%0.2f" x2="%0.2f" y2="%0.2f" />',
                $pu['x'],
                $pu['y'],
                $pv['x'],
                $pv['y']
            );
        }

        foreach ($points as $point) {
            $pointsSvg .= sprintf('<circle cx="%0.2f" cy="%0.2f" r="2.2" />', $point['x'], $point['y']);
        }

        $svg = sprintf(
            '<svg xmlns="http://www.w3.org/2000/svg" width="%d" height="%d" viewBox="0 0 %d %d">'.
            '<rect width="100%%" height="100%%" fill="#0b0f15"/>'.
            '<g stroke="#00d4ff" stroke-opacity="0.8" stroke-width="1.0" fill="none">%s</g>'.
            '<g fill="#ff6666" fill-opacity="0.95">%s</g>'.
            '</svg>',
            $width,
            $height,
            $width,
            $height,
            $linesSvg,
            $pointsSvg
        );

        $path = sprintf('face-vectors/%s/%d/vector_%s.svg', $userType, $userId, date('Ymd_His'));
        Storage::disk('public')->put($path, $svg);
        return $path;
    }

    /**
     * @param array<int, array{x:float,y:float}> $landmarks
     * @param array{width:?int,height:?int} $imageSize
     */
    private function buildOverlayImage(
        string $userType,
        int $userId,
        string $sourceImageBytes,
        array $landmarks,
        array $imageSize
    ): ?string {
        $width = (int) ($imageSize['width'] ?? 0);
        $height = (int) ($imageSize['height'] ?? 0);
        if ($width <= 0 || $height <= 0 || $sourceImageBytes === '') {
            return null;
        }

        $points = [];
        foreach ($landmarks as $point) {
            $x = (float) ($point['x'] ?? 0.0);
            $y = (float) ($point['y'] ?? 0.0);
            if ($x < 0 || $y < 0 || $x > $width || $y > $height) {
                continue;
            }
            $points[] = ['x' => $x, 'y' => $y];
        }
        if (count($points) < 1) {
            return null;
        }

        $edgeKeys = [];
        if (count($points) >= 3) {
            $triangles = $this->triangulateDelaunay($points);
            foreach ($triangles as $tri) {
                [$a, $b, $c] = $tri;
                $pa = $points[$a];
                $pb = $points[$b];
                $pc = $points[$c];
                if (! $this->isReasonableTriangle($pa, $pb, $pc, $width, $height)) {
                    continue;
                }
                $edgeKeys[$this->edgeKey($a, $b)] = [$a, $b];
                $edgeKeys[$this->edgeKey($b, $c)] = [$b, $c];
                $edgeKeys[$this->edgeKey($c, $a)] = [$c, $a];
            }
        }
        if (count($edgeKeys) === 0 && count($points) >= 2) {
            $edgeKeys = $this->nearestNeighborEdges($points, 2);
        }

        $linesSvg = '';
        foreach ($edgeKeys as [$u, $v]) {
            $pu = $points[$u];
            $pv = $points[$v];
            $linesSvg .= sprintf(
                '<line x1="%0.2f" y1="%0.2f" x2="%0.2f" y2="%0.2f" />',
                $pu['x'],
                $pu['y'],
                $pv['x'],
                $pv['y']
            );
        }

        $pointsSvg = '';
        foreach ($points as $point) {
            $pointsSvg .= sprintf('<circle cx="%0.2f" cy="%0.2f" r="2.2" />', $point['x'], $point['y']);
        }

        $mime = finfo_buffer(finfo_open(FILEINFO_MIME_TYPE), $sourceImageBytes) ?: 'image/jpeg';
        $imgDataUri = 'data:' . $mime . ';base64,' . base64_encode($sourceImageBytes);
        $svg = sprintf(
            '<svg xmlns="http://www.w3.org/2000/svg" width="%d" height="%d" viewBox="0 0 %d %d">' .
            '<image href="%s" x="0" y="0" width="%d" height="%d" preserveAspectRatio="none"/>' .
            '<rect width="100%%" height="100%%" fill="#000" fill-opacity="0.08"/>' .
            '<g stroke="#00f0ff" stroke-opacity="0.9" stroke-width="1.2" fill="none">%s</g>' .
            '<g fill="#ff4d6d" fill-opacity="0.98">%s</g>' .
            '</svg>',
            $width,
            $height,
            $width,
            $height,
            htmlspecialchars($imgDataUri, ENT_QUOTES),
            $width,
            $height,
            $linesSvg,
            $pointsSvg
        );

        $path = sprintf('face-vectors/%s/%d/overlay_%s.svg', $userType, $userId, date('Ymd_His'));
        Storage::disk('public')->put($path, $svg);

        return $path;
    }

    /**
     * @param array<int, array{x:float,y:float}> $points
     * @return array<int, array{0:int,1:int,2:int}>
     */
    private function triangulateDelaunay(array $points): array
    {
        $n = count($points);
        if ($n < 3) {
            return [];
        }

        $minX = $maxX = $points[0]['x'];
        $minY = $maxY = $points[0]['y'];
        foreach ($points as $p) {
            $minX = min($minX, $p['x']);
            $maxX = max($maxX, $p['x']);
            $minY = min($minY, $p['y']);
            $maxY = max($maxY, $p['y']);
        }
        $dx = $maxX - $minX;
        $dy = $maxY - $minY;
        $delta = max($dx, $dy);
        $midX = ($minX + $maxX) / 2.0;
        $midY = ($minY + $maxY) / 2.0;

        $work = $points;
        $work[] = ['x' => $midX - 20.0 * $delta, 'y' => $midY - $delta];
        $work[] = ['x' => $midX, 'y' => $midY + 20.0 * $delta];
        $work[] = ['x' => $midX + 20.0 * $delta, 'y' => $midY - $delta];

        $triangles = [[
            'a' => $n,
            'b' => $n + 1,
            'c' => $n + 2,
        ]];

        for ($i = 0; $i < $n; $i++) {
            $p = $work[$i];
            $bad = [];
            foreach ($triangles as $tIndex => $tri) {
                if ($this->inCircumcircle($p, $work[$tri['a']], $work[$tri['b']], $work[$tri['c']])) {
                    $bad[] = $tIndex;
                }
            }

            $edgeMap = [];
            foreach ($bad as $tIndex) {
                $tri = $triangles[$tIndex];
                $edges = [
                    [$tri['a'], $tri['b']],
                    [$tri['b'], $tri['c']],
                    [$tri['c'], $tri['a']],
                ];
                foreach ($edges as [$u, $v]) {
                    $key = $u < $v ? "{$u}:{$v}" : "{$v}:{$u}";
                    $edgeMap[$key] = ($edgeMap[$key] ?? 0) + 1;
                }
            }

            rsort($bad);
            foreach ($bad as $idx) {
                unset($triangles[$idx]);
            }
            $triangles = array_values($triangles);

            foreach ($edgeMap as $key => $count) {
                if ($count !== 1) {
                    continue;
                }
                [$u, $v] = array_map('intval', explode(':', $key));
                $triangles[] = ['a' => $u, 'b' => $v, 'c' => $i];
            }
        }

        $result = [];
        foreach ($triangles as $tri) {
            if ($tri['a'] >= $n || $tri['b'] >= $n || $tri['c'] >= $n) {
                continue;
            }
            $result[] = [$tri['a'], $tri['b'], $tri['c']];
        }
        return $result;
    }

    /**
     * @param array{x:float,y:float} $p
     * @param array{x:float,y:float} $a
     * @param array{x:float,y:float} $b
     * @param array{x:float,y:float} $c
     */
    private function inCircumcircle(array $p, array $a, array $b, array $c): bool
    {
        $ax = $a['x'] - $p['x'];
        $ay = $a['y'] - $p['y'];
        $bx = $b['x'] - $p['x'];
        $by = $b['y'] - $p['y'];
        $cx = $c['x'] - $p['x'];
        $cy = $c['y'] - $p['y'];

        $det = ($ax * $ax + $ay * $ay) * ($bx * $cy - $cx * $by)
            - ($bx * $bx + $by * $by) * ($ax * $cy - $cx * $ay)
            + ($cx * $cx + $cy * $cy) * ($ax * $by - $bx * $ay);

        return $det > 0;
    }

    /**
     * @param array{x:float,y:float} $a
     * @param array{x:float,y:float} $b
     * @param array{x:float,y:float} $c
     */
    private function isReasonableTriangle(array $a, array $b, array $c, int $width, int $height): bool
    {
        $maxEdge = max(24.0, max($width, $height) * 0.42);
        $ab = hypot($a['x'] - $b['x'], $a['y'] - $b['y']);
        $bc = hypot($b['x'] - $c['x'], $b['y'] - $c['y']);
        $ca = hypot($c['x'] - $a['x'], $c['y'] - $a['y']);
        return $ab <= $maxEdge && $bc <= $maxEdge && $ca <= $maxEdge;
    }

    /**
     * @param array<int, array{x:float,y:float}> $landmarks
     * @return array<int, array{x:float,y:float}>
     */
    private function expandLandmarks(array $landmarks, int $targetCount): array
    {
        $points = array_values($landmarks);
        if (count($points) < 2 || count($points) >= $targetCount) {
            return $points;
        }

        $seedCount = count($points);
        $pairs = [];
        for ($i = 0; $i < $seedCount; $i++) {
            for ($j = $i + 1; $j < $seedCount; $j++) {
                $dist = hypot($points[$i]['x'] - $points[$j]['x'], $points[$i]['y'] - $points[$j]['y']);
                $pairs[] = ['i' => $i, 'j' => $j, 'd' => $dist];
            }
        }
        usort($pairs, static fn (array $a, array $b): int => $a['d'] <=> $b['d']);

        foreach ($pairs as $pair) {
            if (count($points) >= $targetCount) {
                break;
            }
            $a = $points[$pair['i']];
            $b = $points[$pair['j']];
            $points[] = [
                'x' => round(($a['x'] + $b['x']) / 2.0, 4),
                'y' => round(($a['y'] + $b['y']) / 2.0, 4),
            ];
        }

        return $points;
    }

    private function edgeKey(int $a, int $b): string
    {
        return $a < $b ? $a . ':' . $b : $b . ':' . $a;
    }

    /**
     * @param array<int, array{x:float,y:float}> $points
     * @return array<string, array{0:int,1:int}>
     */
    private function nearestNeighborEdges(array $points, int $neighbors): array
    {
        $edges = [];
        $n = count($points);
        for ($i = 0; $i < $n; $i++) {
            $distances = [];
            for ($j = 0; $j < $n; $j++) {
                if ($i === $j) {
                    continue;
                }
                $distances[] = [
                    'j' => $j,
                    'd' => hypot($points[$i]['x'] - $points[$j]['x'], $points[$i]['y'] - $points[$j]['y']),
                ];
            }
            usort($distances, static fn (array $a, array $b): int => $a['d'] <=> $b['d']);
            $limit = min($neighbors, count($distances));
            for ($k = 0; $k < $limit; $k++) {
                $j = (int) $distances[$k]['j'];
                $edges[$this->edgeKey($i, $j)] = [$i, $j];
            }
        }

        return $edges;
    }
}
