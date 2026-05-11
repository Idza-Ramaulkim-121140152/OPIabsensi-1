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
            'hari' => 'required',
            'shifts' => 'required|array|min:1',
            'shifts.*.nama' => 'nullable|string|max:100',
            'shifts.*.masuk_awal' => 'required|date_format:H:i',
            'shifts.*.masuk_akhir' => 'required|date_format:H:i',
            'shifts.*.pulang_awal' => 'required|date_format:H:i',
            'shifts.*.pulang_akhir' => 'required|date_format:H:i',
        ]);

        $validated['hari'] = $this->normalizeHari($request->input('hari'));
        if ($validated['hari'] === '') {
            return response()->json(['message' => 'Pilih minimal satu hari jadwal.'], 422);
        }

        $jadwal = JadwalMengajar::create($validated);
        return response()->json($jadwal, 201);
    }

    public function update(Request $request, $id)
    {
        $jadwal = JadwalMengajar::find($id);
        if (!$jadwal) return response()->json(['message' => 'Not found'], 404);

        $validated = $request->validate([
            'hari' => 'sometimes|required',
            'shifts' => 'sometimes|required|array|min:1',
            'shifts.*.nama' => 'nullable|string|max:100',
            'shifts.*.masuk_awal' => 'required|date_format:H:i',
            'shifts.*.masuk_akhir' => 'required|date_format:H:i',
            'shifts.*.pulang_awal' => 'required|date_format:H:i',
            'shifts.*.pulang_akhir' => 'required|date_format:H:i',
        ]);

        if ($request->has('hari')) {
            $validated['hari'] = $this->normalizeHari($request->input('hari'));
            if ($validated['hari'] === '') {
                return response()->json(['message' => 'Pilih minimal satu hari jadwal.'], 422);
            }
        }

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

    private function normalizeHari(mixed $hari): string
    {
        $allowed = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'];
        $items = is_array($hari)
            ? $hari
            : preg_split('/\s*,\s*/', (string) $hari, -1, PREG_SPLIT_NO_EMPTY);

        $normalized = [];
        foreach ($items ?: [] as $item) {
            $item = trim((string) $item);
            if (in_array($item, $allowed, true) && ! in_array($item, $normalized, true)) {
                $normalized[] = $item;
            }
        }

        return implode(',', $normalized);
    }
}
