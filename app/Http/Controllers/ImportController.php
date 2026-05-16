<?php

namespace App\Http\Controllers;

use App\Exports\StudentTemplateExport;
use App\Models\AuditLog;
use App\Services\StudentImportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ImportController extends Controller
{
    public function __construct(
        private readonly StudentImportService $service,
    ) {}

    /**
     * Tampilkan halaman import: form upload (step 1) atau preview (step 2).
     */
    public function index(): View
    {
        $preview = session('import_preview');
        return view('imports.index', compact('preview'));
    }

    /**
     * Download template .xlsx siap pakai.
     */
    public function downloadTemplate(): BinaryFileResponse
    {
        return Excel::download(new StudentTemplateExport(), 'template-import-murid.xlsx');
    }

    /**
     * Dry run: parse + validasi file, simpan hasil ke session, redirect ke index.
     */
    public function validate(Request $request): RedirectResponse
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx|max:5120',
        ], [
            'file.required' => 'File Excel wajib diupload.',
            'file.mimes'    => 'File harus berformat .xlsx.',
            'file.max'      => 'Ukuran file maksimal 5MB.',
        ]);

        try {
            $result = $this->service->validate($request->file('file'));
        } catch (\Throwable $e) {
            return redirect()->route('import.index')
                ->with('error', 'File tidak dapat dibaca. Pastikan file tidak rusak dan menggunakan format .xlsx yang benar.');
        }

        // Hapus field 'data' dari errors sebelum disimpan ke session — hanya perlu row/name/message untuk preview
        $result['errors'] = array_map(fn ($e) => [
            'row'     => $e['row'],
            'name'    => $e['name'],
            'message' => $e['message'],
        ], $result['errors']);

        session(['import_preview' => $result]);

        $totalValid = count($result['valid']) + count($result['overwrite']);
        $totalError = count($result['errors']);

        return redirect()->route('import.index')
            ->with('info', "{$totalValid} baris valid, {$totalError} baris error — periksa preview sebelum konfirmasi.");
    }

    /**
     * Simpan baris valid + overwrite dari session ke database.
     */
    public function confirm(Request $request): RedirectResponse
    {
        $preview = session('import_preview');

        if (!$preview || !isset($preview['valid'], $preview['overwrite'])) {
            return redirect()->route('import.index')
                ->with('error', 'Sesi validasi kadaluarsa. Upload ulang file.');
        }

        try {
            $result = $this->service->confirm($preview['valid'], $preview['overwrite']);
        } catch (\Throwable $e) {
            session()->forget('import_preview');
            return redirect()->route('import.index')
                ->with('error', 'Terjadi kesalahan saat menyimpan data. Import dibatalkan, tidak ada data yang tersimpan.');
        }

        session()->forget('import_preview');

        AuditLog::record(
            action: AuditLog::ACTION_CREATE,
            entityLabel: 'Import Murid Excel',
            newValues: $result,
            notes: "Diimport: {$result['imported']} murid.",
        );

        return redirect()->route('students.index')
            ->with('success', "{$result['imported']} murid berhasil diimport.");
    }

    /**
     * Batalkan preview — clear session, kembali ke form upload.
     */
    public function cancel(): RedirectResponse
    {
        session()->forget('import_preview');
        return redirect()->route('import.index')
            ->with('info', 'Import dibatalkan. Silakan upload ulang file jika ingin mencoba lagi.');
    }
}
