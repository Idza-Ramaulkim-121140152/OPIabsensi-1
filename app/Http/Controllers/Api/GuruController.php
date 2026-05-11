<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FaceEmbedding;
use App\Models\Guru;
use App\Services\FaceRegistrationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use RuntimeException;

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

    public function store(Request $request, FaceRegistrationService $faceRegistration)
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

        try {
            $guru = DB::transaction(function () use ($validated, $faceRegistration) {
                $guru = Guru::create($validated);

                if (! empty($validated['foto_wajah'])) {
                    $faceRegistration->register('guru', (int) $guru->id_guru, $validated['foto_wajah']);
                }

                return $guru;
            });
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json($guru, 201);
    }

    public function update(Request $request, $id, FaceRegistrationService $faceRegistration)
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

        try {
            DB::transaction(function () use ($guru, $validated, $faceRegistration) {
                $guru->update($validated);

                if (array_key_exists('foto_wajah', $validated)) {
                    if (! empty($validated['foto_wajah'])) {
                        $faceRegistration->register('guru', (int) $guru->id_guru, $validated['foto_wajah']);
                    } else {
                        FaceEmbedding::where('user_id', $guru->id_guru)->where('user_type', 'guru')->delete();
                    }
                }
            });
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        $guru->refresh();
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
