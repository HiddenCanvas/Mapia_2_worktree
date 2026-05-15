<?php

namespace App\Http\Controllers;

use App\Models\HistoryKelembapan;
use App\Models\Sensor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class HistoryKelembapanController extends Controller
{
    public function index()
    {
        $userId = Auth::id();

        // Get all sensors for the current user
        $sensors = Sensor::where('id_user', $userId)->get();

        // Get history data
        $history = HistoryKelembapan::whereIn('id_sensor', $sensors->pluck('id_sensor'))
            ->with('sensor')
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        return view('history_kelembapan.index', compact('history', 'sensors'));
    }

    // This method is ready to be used by IoT devices to store history
    public function store(Request $request)
    {
        $validated = $request->validate([
            'id_sensor' => 'required|exists:sensors,id_sensor',
            'kelembapan' => 'required|numeric',
        ]);

        $history = HistoryKelembapan::create($validated);

        return response()->json([
            'message' => 'Data kelembapan berhasil disimpan',
            'data' => $history
        ], 201);
    }
}
