<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class JadwalMengajar extends Model
{
    use HasFactory;

    protected $table = 'jadwal_mengajar';
    protected $primaryKey = 'id_jadwal';
    protected $guarded = [];

    /**
     * Cast attributes to native types
     *
     * @var array
     */
    protected $casts = [
        'shifts' => 'array', // JSON array of shift times
    ];

    protected $appends = [
        'hari_list',
    ];

    /**
     * Get the presensi records for this schedule
     */
    public function presensi()
    {
        return $this->hasMany(Presensi::class, 'id_jadwal', 'id_jadwal');
    }

    /**
     * Get shifts for a specific day
     * Returns array of shift objects with masuk_awal, masuk_akhir, pulang_awal, pulang_akhir
     */
    public function getShifts()
    {
        return $this->shifts ?? [];
    }

    public function getHariListAttribute(): array
    {
        return $this->parseHariList((string) $this->hari);
    }

    public function hasHari(string $hari): bool
    {
        return in_array($hari, $this->hari_list, true);
    }

    /**
     * Add a new shift to the schedule
     */
    public function addShift(array $shift)
    {
        $shifts = $this->shifts ?? [];
        $shifts[] = $shift;
        $this->shifts = $shifts;
        return $this;
    }

    /**
     * Check if a time is within the allowed attendance window for this day.
     * Returns: ['allowed' => bool, 'shift' => shift_info or null, 'shift_index' => int|null,
     *           'time_type' => 'masuk'|'pulang'|null, 'reason' => string]
     */
    public function validateAttendanceTime($time): array
    {
        $shifts = $this->getShifts();

        if (empty($shifts)) {
            return [
                'allowed' => false,
                'shift' => null,
                'shift_index' => null,
                'time_type' => null,
                'reason' => 'Tidak ada jadwal untuk hari ini'
            ];
        }

        $timeTs = strtotime($time);
        $toTimestamp = static function ($value): ?int {
            $value = trim((string) $value);
            if ($value === '') {
                return null;
            }

            $timestamp = strtotime($value);
            return $timestamp === false ? null : $timestamp;
        };

        foreach ($shifts as $index => $shift) {
            if (! is_array($shift)) {
                continue;
            }

            $masukAwal = $toTimestamp($shift['masuk_awal'] ?? '');
            $masukAkhir = $toTimestamp($shift['masuk_akhir'] ?? '');
            if ($masukAwal !== null && $masukAkhir !== null && $timeTs >= $masukAwal && $timeTs <= $masukAkhir) {
                return [
                    'allowed' => true,
                    'shift' => $shift,
                    'shift_index' => $index + 1,
                    'time_type' => 'masuk',
                    'reason' => 'OK'
                ];
            }

            $pulangAwal = $toTimestamp($shift['pulang_awal'] ?? '');
            $pulangAkhir = $toTimestamp($shift['pulang_akhir'] ?? '');
            if ($pulangAwal !== null && $pulangAkhir !== null && $timeTs >= $pulangAwal && $timeTs <= $pulangAkhir) {
                return [
                    'allowed' => true,
                    'shift' => $shift,
                    'shift_index' => $index + 1,
                    'time_type' => 'pulang',
                    'reason' => 'OK'
                ];
            }
        }

        return [
            'allowed' => false,
            'shift' => null,
            'shift_index' => null,
            'time_type' => null,
            'reason' => 'Di luar jam shift'
        ];
    }

    private function parseHariList(string $hari): array
    {
        $allowed = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'];
        $items = preg_split('/\s*,\s*/', $hari, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $normalized = [];

        foreach ($items as $item) {
            $item = trim($item);
            if (in_array($item, $allowed, true) && ! in_array($item, $normalized, true)) {
                $normalized[] = $item;
            }
        }

        return $normalized;
    }
}
