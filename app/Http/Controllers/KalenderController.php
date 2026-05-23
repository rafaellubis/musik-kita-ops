<?php

namespace App\Http\Controllers;

use App\Models\ClassSession;
use App\Models\Room;
use App\Models\Teacher;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Kalender Jadwal Mingguan — tampilan read-only grid sesi per minggu (Senin-Sabtu).
 * Data dari class_sessions (sesi konkret), bukan jadwal template.
 */
class KalenderController extends Controller
{
    public function index(Request $request): View
    {
        // --- 1. Resolve minggu yang ditampilkan ---
        try {
            $weekStart = $request->filled('week')
                ? Carbon::parse($request->input('week'))->startOfWeek(Carbon::MONDAY)
                : Carbon::now()->startOfWeek(Carbon::MONDAY);
        } catch (\Exception $e) {
            // Param week tidak valid — fallback ke minggu ini
            $weekStart = Carbon::now()->startOfWeek(Carbon::MONDAY);
        }
        $weekEnd = $weekStart->copy()->addDays(5); // Sabtu

        // --- 2. Query sesi minggu ini dengan eager load ---
        $query = ClassSession::whereBetween('session_date', [$weekStart, $weekEnd])
            ->with([
                'student',                        // nama + kode murid
                'teacher',                        // nama guru
                'room',                           // kode + nama ruangan
                'enrollment.package.instrument',  // nama instrumen
            ]);

        // --- 3. Apply filter opsional ---
        if ($request->filled('teacher_id')) {
            $query->where('teacher_id', (int) $request->input('teacher_id'));
        }
        if ($request->filled('room_id')) {
            $query->where('room_id', (int) $request->input('room_id'));
        }

        $sessions = $query
            ->orderBy('session_date')
            ->orderBy('start_time')
            ->get();

        // --- 4. Bangun $grid[day_of_week][start_time] = [ClassSession, ...] ---
        // day_of_week: 1=Senin ... 6=Sabtu (Carbon: 1=Monday)
        $grid = [];
        foreach ($sessions as $session) {
            $dow  = \Carbon\Carbon::parse($session->session_date)->dayOfWeek; // Carbon: 1=Mon...6=Sat
            $time = $session->start_time;
            $grid[$dow][$time][] = $session;
        }

        // --- 5. Daftar slot jam unik yang tampil (baris grid) ---
        $timeSlots = $sessions->pluck('start_time')->unique()->sort()->values();

        // --- 6. Kolom hari: Carbon objects Senin-Sabtu ---
        $days = [];
        for ($i = 0; $i <= 5; $i++) {
            $date = $weekStart->copy()->addDays($i);
            $days[$date->dayOfWeek] = $date;
        }

        // --- 7. Data dropdown filter ---
        $teachers = Teacher::where('is_active', true)->orderBy('name')->get(['id', 'name']);
        $rooms    = Room::where('is_active', true)->orderBy('code')->get(['id', 'code', 'name']);

        // --- 8. URL untuk navigator minggu (filter tetap disertakan) ---
        $filterParams = array_filter([
            'teacher_id' => $request->input('teacher_id'),
            'room_id'    => $request->input('room_id'),
        ]);
        $prevWeek    = array_merge($filterParams, ['week' => $weekStart->copy()->subWeek()->format('Y-m-d')]);
        $nextWeek    = array_merge($filterParams, ['week' => $weekStart->copy()->addWeek()->format('Y-m-d')]);
        $currentWeek = array_merge($filterParams, ['week' => Carbon::now()->startOfWeek(Carbon::MONDAY)->format('Y-m-d')]);

        return view('kalender.index', compact(
            'weekStart', 'weekEnd',
            'grid', 'timeSlots', 'days',
            'teachers', 'rooms',
            'prevWeek', 'nextWeek', 'currentWeek',
        ));
    }
}
