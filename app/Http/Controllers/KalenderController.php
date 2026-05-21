<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Kalender Jadwal Mingguan — tampilan read-only grid sesi per minggu (Senin-Sabtu).
 */
class KalenderController extends Controller
{
    public function index(Request $request): View
    {
        return view('kalender.index');
    }
}
