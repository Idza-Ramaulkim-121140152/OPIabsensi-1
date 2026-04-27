<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\JadwalMengajar;
use Illuminate\Http\Request;

class JadwalMengajarController extends Controller
{
    public function index()
    {
        return response()->json(JadwalMengajar::all());
    }

    public function show($id)
    {
        $jadwal = JadwalMengajar::find($id);
        if (!$jadwal) return response()->json(['message' => 'Not found'], 404);
        return response()->json($jadwal);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'id_guru' => 'required|exists:guru,id_guru',
            'kelas' => 'required|string|max:50',
            'mata_pelajaran' => 'required|string|max:120',
            'hari' => 'required|string|max:10',
            'jam_mulai' => 'required|date_format:H:i',
            'jam_selesai' => 'required|date_format:H:i',
        ]);

        $jadwal = JadwalMengajar::create($validated);
        return response()->json($jadwal, 201);
    }

    public function update(Request $request, $id)
    {
        $jadwal = JadwalMengajar::find($id);
        if (!$jadwal) return response()->json(['message' => 'Not found'], 404);

        $validated = $request->validate([
            'id_guru' => 'sometimes|required|exists:guru,id_guru',
            'kelas' => 'sometimes|required|string|max:50',
            'mata_pelajaran' => 'sometimes|required|string|max:120',
            'hari' => 'sometimes|required|string|max:10',
            'jam_mulai' => 'sometimes|required|date_format:H:i',
            'jam_selesai' => 'sometimes|required|date_format:H:i',
        ]);

        $jadwal->update($validated);
        return response()->json($jadwal);
    }

    public function destroy($id)
    {
        $jadwal = JadwalMengajar::find($id);
        if (!$jadwal) return response()->json(['message' => 'Not found'], 404);

        $jadwal->delete();
        return response()->json(['message' => 'Deleted']);
    }
}
