<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Siswa;
use Illuminate\Http\Request;

class SiswaController extends Controller
{
    public function index()
    {
        return response()->json(Siswa::all());
    }

    public function show($id)
    {
        $siswa = Siswa::find($id);
        if (!$siswa) return response()->json(['message' => 'Not found'], 404);
        return response()->json($siswa);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nama' => 'required|string|max:150',
            'no_induk' => 'nullable|string|max:50|unique:siswa,no_induk',
            'kelas' => 'nullable|string|max:50',
            'alamat' => 'nullable|string|max:255',
            'id_rfid' => 'nullable|string|max:100|unique:siswa,id_rfid',
            'foto_wajah' => 'nullable|string',
        ]);

        $siswa = Siswa::create($validated);
        return response()->json($siswa, 201);
    }

    public function update(Request $request, $id)
    {
        $siswa = Siswa::find($id);
        if (!$siswa) return response()->json(['message' => 'Not found'], 404);

        $validated = $request->validate([
            'nama' => 'sometimes|required|string|max:150',
            'no_induk' => 'nullable|string|max:50|unique:siswa,no_induk,' . $id,
            'kelas' => 'nullable|string|max:50',
            'alamat' => 'nullable|string|max:255',
            'id_rfid' => 'nullable|string|max:100|unique:siswa,id_rfid,' . $id,
            'foto_wajah' => 'nullable|string',
        ]);

        $siswa->update($validated);
        return response()->json($siswa);
    }

    public function destroy($id)
    {
        $siswa = Siswa::find($id);
        if (!$siswa) return response()->json(['message' => 'Not found'], 404);

        $siswa->delete();
        return response()->json(['message' => 'Deleted']);
    }
}
