<?php

namespace App\Http\Controllers;

use App\Models\Room;
use Illuminate\Http\Request;

class RoomController extends Controller
{
    public function index()
    {
        $rooms = Room::orderBy('code')->get();
        return view('rooms.index', compact('rooms'));
    }

    public function create()
    {
        return view('rooms.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'code' => 'required|string|max:10|unique:rooms,code|regex:/^[A-Z0-9]+$/',
            'name' => 'required|string|max:50',
            'capacity' => 'required|integer|min:1|max:20',
            'notes' => 'nullable|string',
        ]);
        $validated['has_piano'] = $request->has('has_piano');
        $validated['has_drum'] = $request->has('has_drum');
        $validated['has_amplifier'] = $request->has('has_amplifier');
        $validated['is_active'] = $request->has('is_active');
        Room::create($validated);
        return redirect()->route('rooms.index')->with('success', 'Ruangan ditambahkan.');
    }

    public function edit(string $id)
    {
        $room = Room::findOrFail($id);
        return view('rooms.edit', compact('room'));
    }

    public function update(Request $request, string $id)
    {
        $room = Room::findOrFail($id);
        $validated = $request->validate([
            'code' => 'required|string|max:10|unique:rooms,code,' . $id . '|regex:/^[A-Z0-9]+$/',
            'name' => 'required|string|max:50',
            'capacity' => 'required|integer|min:1|max:20',
            'notes' => 'nullable|string',
        ]);
        $validated['has_piano'] = $request->has('has_piano');
        $validated['has_drum'] = $request->has('has_drum');
        $validated['has_amplifier'] = $request->has('has_amplifier');
        $validated['is_active'] = $request->has('is_active');
        $room->update($validated);
        return redirect()->route('rooms.index')->with('success', 'Ruangan diperbarui.');
    }

    public function destroy(string $id)
    {
        Room::findOrFail($id)->delete();
        return redirect()->route('rooms.index')->with('success', 'Ruangan dihapus.');
    }
}