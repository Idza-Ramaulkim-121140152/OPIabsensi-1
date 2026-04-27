<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class IotDevice extends Model
{
    use HasFactory;

    protected $table = 'iot_devices';
    protected $primaryKey = 'id_device';
    protected $guarded = [];
}
