<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FaceEmbedding;
use App\Models\Siswa;
use App\Services\FaceRegistrationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use RuntimeException;

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

    public function store(Request $request, FaceRegistrationService $faceRegistration)
    {
        $validated = $request->validate([
            'nama' => 'required|string|max:150',
            'no_induk' => 'nullable|string|max:50|unique:siswa,no_induk',
            'kelas' => 'nullable|string|max:50',
            'alamat' => 'nullable|string|max:255',
            'id_rfid' => 'nullable|string|max:100|unique:siswa,id_rfid',
            'foto_wajah' => 'nullable|string',
        ]);

        try {
            $siswa = DB::transaction(function () use ($validated, $faceRegistration) {
                $siswa = Siswa::create($validated);

                if (! empty($validated['foto_wajah'])) {
                    $faceRegistration->register('siswa', (int) $siswa->id, $validated['foto_wajah']);
                }

                return $siswa;
            });
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json($siswa, 201);
    }

    public function update(Request $request, $id, FaceRegistrationService $faceRegistration)
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

        try {
            DB::transaction(function () use ($siswa, $validated, $faceRegistration) {
                $siswa->update($validated);

                if (array_key_exists('foto_wajah', $validated)) {
                    if (! empty($validated['foto_wajah'])) {
                        $faceRegistration->register('siswa', (int) $siswa->id, $validated['foto_wajah']);
                    } else {
                        FaceEmbedding::where('user_id', $siswa->id)->where('user_type', 'siswa')->delete();
                    }
                }
            });
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        $siswa->refresh();
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
