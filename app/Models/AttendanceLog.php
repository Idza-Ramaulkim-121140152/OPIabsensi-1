<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;

class AttendanceLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'employee_id',
        'timestamp',
        'confidence',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'timestamp' => 'datetime',
            'confidence' => 'float',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
