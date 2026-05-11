<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FaceEmbedding extends Model
{
    use HasFactory;

    protected $table = 'face_embeddings';
    protected $guarded = [];
    
    protected $casts = [
        'embedding' => 'array',
        'landmarks' => 'array',
        'landmark_count' => 'integer',
    ];
}
