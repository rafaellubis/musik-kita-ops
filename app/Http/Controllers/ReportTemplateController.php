<?php
namespace App\Http\Controllers;

use App\Models\Instrument;
use App\Models\ReportTemplate;
use App\Models\ReportTemplateSection;
use App\Models\ReportTemplateItem;
use Illuminate\Http\Request;

/**
 * CRUD template laporan progres per instrumen (Owner only).
 * Template berisi kumpulan seksi dan indikator yang dipakai
 * untuk membuat laporan perkembangan murid.
 */
class ReportTemplateController extends Controller
{
    // ===== Template =====

    public function index()
    {
        $templates = ReportTemplate::with('instrument', 'sections')
            ->orderBy('sort_order')->orderBy('name')->get();
        return view('report-templates.index', compact('templates'));
    }

    public function create()
    {
        $instruments = Instrument::where('is_active', true)->orderBy('name')->get();
        return view('report-templates.create', compact('instruments'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'instrument_id' => 'required|exists:instruments,id',
            'name'          => 'required|string|max:100',
            'description'   => 'nullable|string|max:500',
            'sort_order'    => 'required|integer|min:0|max:999',
        ], [
            'instrument_id.required' => 'Instrumen wajib dipilih.',
            'name.required'          => 'Nama template wajib diisi.',
        ]);

        $validated['is_active'] = $request->boolean('is_active', true);
        ReportTemplate::create($validated);

        return redirect()->route('report-templates.index')
            ->with('success', "Template '{$validated['name']}' berhasil dibuat.");
    }

    public function show(ReportTemplate $reportTemplate)
    {
        $reportTemplate->load(['instrument', 'sections.items']);
        return view('report-templates.show', compact('reportTemplate'));
    }

    public function edit(ReportTemplate $reportTemplate)
    {
        $instruments = Instrument::where('is_active', true)->orderBy('name')->get();
        return view('report-templates.edit', compact('reportTemplate', 'instruments'));
    }

    public function update(Request $request, ReportTemplate $reportTemplate)
    {
        $validated = $request->validate([
            'instrument_id' => 'required|exists:instruments,id',
            'name'          => 'required|string|max:100',
            'description'   => 'nullable|string|max:500',
            'sort_order'    => 'required|integer|min:0|max:999',
        ], [
            'instrument_id.required' => 'Instrumen wajib dipilih.',
            'name.required'          => 'Nama template wajib diisi.',
        ]);

        $validated['is_active'] = $request->boolean('is_active', false);
        $reportTemplate->update($validated);

        return redirect()->route('report-templates.show', $reportTemplate)
            ->with('success', 'Template berhasil diperbarui.');
    }

    public function destroy(ReportTemplate $reportTemplate)
    {
        // Cegah hapus jika template sudah dipakai di laporan murid
        if ($reportTemplate->progressReports()->exists()) {
            return back()->with('error',
                "Template '{$reportTemplate->name}' tidak bisa dihapus karena sudah dipakai di laporan. Nonaktifkan saja.");
        }
        $name = $reportTemplate->name;
        $reportTemplate->delete();
        return redirect()->route('report-templates.index')
            ->with('success', "Template '{$name}' berhasil dihapus.");
    }

    // ===== Sections =====

    /**
     * Tambah seksi baru ke template.
     */
    public function storeSection(Request $request, ReportTemplate $reportTemplate)
    {
        $validated = $request->validate([
            'title'      => 'required|string|max:100',
            'sort_order' => 'required|integer|min:0|max:99',
        ], [
            'title.required' => 'Judul seksi wajib diisi.',
        ]);
        $validated['report_template_id'] = $reportTemplate->id;
        ReportTemplateSection::create($validated);
        return back()->with('success', "Seksi '{$validated['title']}' berhasil ditambahkan.");
    }

    /**
     * Hapus seksi beserta semua indikatornya (cascade via migrasi).
     */
    public function destroySection(ReportTemplate $reportTemplate, ReportTemplateSection $section)
    {
        abort_if($section->report_template_id !== $reportTemplate->id, 404);
        $title = $section->title;
        $section->delete();
        return back()->with('success', "Seksi '{$title}' berhasil dihapus.");
    }

    // ===== Items =====

    /**
     * Tambah item indikator ke seksi.
     */
    public function storeItem(Request $request, ReportTemplate $reportTemplate, ReportTemplateSection $section)
    {
        abort_if($section->report_template_id !== $reportTemplate->id, 404);
        $validated = $request->validate([
            'label'      => 'required|string|max:200',
            'sort_order' => 'required|integer|min:0|max:99',
        ], [
            'label.required' => 'Label indikator wajib diisi.',
        ]);
        $validated['report_template_section_id'] = $section->id;
        ReportTemplateItem::create($validated);
        return back()->with('success', 'Indikator berhasil ditambahkan.');
    }

    /**
     * Hapus satu item indikator dari seksi.
     */
    public function destroyItem(ReportTemplate $reportTemplate, ReportTemplateSection $section, ReportTemplateItem $item)
    {
        abort_if($section->report_template_id !== $reportTemplate->id, 404);
        abort_if($item->report_template_section_id !== $section->id, 404);
        $item->delete();
        return back()->with('success', 'Indikator berhasil dihapus.');
    }
}
