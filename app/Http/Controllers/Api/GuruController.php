<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Guru;
use Illuminate\Http\Request;

class GuruController extends Controller
{
    public function index()
    {
        return response()->json(Guru::all());
    }

    public function show($id)
    {
        $guru = Guru::find($id);
        if (!$guru) return response()->json(['message' => 'Not found'], 404);
        return response()->json($guru);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nama' => 'required|string|max:150',
            'nip' => 'required|string|max:50|unique:guru,nip',
            'username' => 'required|string|max:100|unique:guru,username',
            'password' => 'required|string|max:255',
            'kelas_wali' => 'nullable|string|max:50',
            'is_wali_kelas' => 'boolean',
            'id_rfid' => 'nullable|string|max:100|unique:guru,id_rfid',
            'foto_wajah' => 'nullable|string',
        ]);

        $guru = Guru::create($validated);
        return response()->json($guru, 201);
    }

    public function update(Request $request, $id)
    {
        $guru = Guru::find($id);
        if (!$guru) return response()->json(['message' => 'Not found'], 404);

        $validated = $request->validate([
            'nama' => 'sometimes|required|string|max:150',
            'nip' => 'sometimes|required|string|max:50|unique:guru,nip,' . $id . ',id_guru',
            'username' => 'sometimes|required|string|max:100|unique:guru,username,' . $id . ',id_guru',
            'password' => 'sometimes|required|string|max:255',
            'kelas_wali' => 'nullable|string|max:50',
            'is_wali_kelas' => 'boolean',
            'id_rfid' => 'nullable|string|max:100|unique:guru,id_rfid,' . $id . ',id_guru',
            'foto_wajah' => 'nullable|string',
        ]);

        $guru->update($validated);
        return response()->json($guru);
    }

    public function destroy($id)
    {
        $guru = Guru::find($id);
        if (!$guru) return response()->json(['message' => 'Not found'], 404);

        $guru->delete();
        return response()->json(['message' => 'Deleted']);
    }
}
