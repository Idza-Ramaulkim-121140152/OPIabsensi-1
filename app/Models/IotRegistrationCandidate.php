<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class IotRegistrationCandidate extends Model
{
    use HasFactory;

    protected $table = 'iot_registration_candidates';
    protected $primaryKey = 'id_candidate';
    protected $guarded = [];
}
