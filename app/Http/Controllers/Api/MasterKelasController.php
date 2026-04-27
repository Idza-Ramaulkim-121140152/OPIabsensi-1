<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MasterKelas;
use Illuminate\Http\Request;

class MasterKelasController extends Controller
{
    public function index()
    {
        return response()->json(MasterKelas::all());
    }

    public function show($id)
    {
        $kelas = MasterKelas::find($id);
        if (!$kelas) return response()->json(['message' => 'Not found'], 404);
        return response()->json($kelas);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nama_kelas' => 'required|string|max:50|unique:master_kelas,nama_kelas',
        ]);

        $kelas = MasterKelas::create($validated);
        return response()->json($kelas, 201);
    }

    public function update(Request $request, $id)
    {
        $kelas = MasterKelas::find($id);
        if (!$kelas) return response()->json(['message' => 'Not found'], 404);

        $validated = $request->validate([
            'nama_kelas' => 'required|string|max:50|unique:master_kelas,nama_kelas,' . $id . ',id_kelas',
        ]);

        $kelas->update($validated);
        return response()->json($kelas);
    }

    public function destroy($id)
    {
        $kelas = MasterKelas::find($id);
        if (!$kelas) return response()->json(['message' => 'Not found'], 404);

        $kelas->delete();
        return response()->json(['message' => 'Deleted']);
    }
}
