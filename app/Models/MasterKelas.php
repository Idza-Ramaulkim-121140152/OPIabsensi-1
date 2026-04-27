<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MasterKelas extends Model
{
    use HasFactory;

    protected $table = 'master_kelas';
    protected $primaryKey = 'id_kelas';
    protected $guarded = [];
}
