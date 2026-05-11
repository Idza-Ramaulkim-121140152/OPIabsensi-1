<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class IotScanLog extends Model
{
    use HasFactory;

    protected $table = 'iot_scan_logs';
    protected $primaryKey = 'id_scan';
    public $timestamps = false;
    protected $guarded = [];
}
