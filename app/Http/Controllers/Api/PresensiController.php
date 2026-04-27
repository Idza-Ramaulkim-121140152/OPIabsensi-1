<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Presensi;
use Illuminate\Http\Request;

class PresensiController extends Controller
{
    public function index()
    {
        return response()->json(Presensi::with(['siswa', 'guru', 'jadwal'])->orderBy('tanggal', 'desc')->orderBy('jam', 'desc')->get());
    }

    public function show($id)
    {
        $presensi = Presensi::find($id);
        if (!$presensi) return response()->json(['message' => 'Not found'], 404);
        return response()->json($presensi);
    }
}
