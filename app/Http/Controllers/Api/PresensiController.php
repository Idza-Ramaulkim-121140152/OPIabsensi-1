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

    public function update(Request $request, $id)
    {
        $presensi = Presensi::find($id);
        if (! $presensi) {
            return response()->json(['message' => 'Not found'], 404);
        }

        $data = $request->validate([
            'status' => 'sometimes|string|max:30',
            'jam' => 'sometimes|string|max:10',
            'metode' => 'sometimes|string|max:30',
            'catatan' => 'nullable|string|max:255',
        ]);
        $presensi->update($data);

        return response()->json([
            'message' => 'Updated',
            'data' => $presensi->fresh(),
        ]);
    }

    public function destroy($id)
    {
        $presensi = Presensi::find($id);
        if (! $presensi) {
            return response()->json(['message' => 'Not found'], 404);
        }
        $presensi->delete();
        return response()->json(['message' => 'Deleted']);
    }
}
