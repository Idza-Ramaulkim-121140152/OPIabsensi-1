<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;

class FaceEmbedding extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'employee_id',
        'embedding',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'embedding' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
