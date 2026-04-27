<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class IotRegistrationSession extends Model
{
    use HasFactory;

    protected $table = 'iot_registration_sessions';
    protected $primaryKey = 'id_session';
    protected $guarded = [];
}
