<?php
 
namespace App\Http\Controllers;
 
use App\Models\Instrument;
use App\Http\Requests\StoreInstrumentRequest;
use App\Http\Requests\UpdateInstrumentRequest;
use Illuminate\Http\Request;
 
class InstrumentController extends Controller
{
    public function index()
    {
        $instruments = Instrument::orderBy('sort_order')->get();
        return view('instruments.index', compact('instruments'));
    }
 
    public function create()
    {
        return view('instruments.create');
    }
 
    public function store(StoreInstrumentRequest $request)
    {
        Instrument::create($request->validated());
        return redirect()->route('instruments.index')
            ->with('success', 'Instrumen berhasil ditambahkan.');
    }
 
    public function edit(Instrument $instrument)
    {
        return view('instruments.edit', compact('instrument'));
    }
 
    public function update(UpdateInstrumentRequest $request, Instrument $instrument)
    {
        $instrument->update($request->validated());
        return redirect()->route('instruments.index')
            ->with('success', 'Instrumen berhasil diperbarui.');
    }
 
    public function destroy(Instrument $instrument)
    {
        $instrument->delete();
        return redirect()->route('instruments.index')
            ->with('success', 'Instrumen berhasil dihapus.');
    }
 
    public function toggleActive(Instrument $instrument)
    {
        $instrument->update(['is_active' => !$instrument->is_active]);
        return back()->with('success', 'Status berhasil diubah.');
    }
}
