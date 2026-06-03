<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreWhatsappMessageTemplateRequest;
use App\Http\Requests\UpdateWhatsappMessageTemplateRequest;
use App\Models\WhatsappMessageTemplate;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * CRUD template pesan WhatsApp — write Owner only (read semua role di route group).
 */
class WhatsappMessageTemplateController extends Controller
{
    public function index(): View
    {
        $templates = WhatsappMessageTemplate::orderBy('sort_order')->orderBy('code')->get();

        return view('whatsapp-templates.index', compact('templates'));
    }

    public function create(): View
    {
        return view('whatsapp-templates.create');
    }

    public function store(StoreWhatsappMessageTemplateRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['is_active'] = $request->boolean('is_active', true);

        WhatsappMessageTemplate::create($data);

        return redirect()->route('whatsapp-templates.index')
            ->with('success', "Template '{$data['code']}' berhasil ditambahkan.");
    }

    public function edit(WhatsappMessageTemplate $whatsappMessageTemplate): View
    {
        return view('whatsapp-templates.edit', ['template' => $whatsappMessageTemplate]);
    }

    public function update(
        UpdateWhatsappMessageTemplateRequest $request,
        WhatsappMessageTemplate $whatsappMessageTemplate,
    ): RedirectResponse {
        $data = $request->validated();
        $data['is_active'] = $request->boolean('is_active', false);

        $whatsappMessageTemplate->update($data);

        return redirect()->route('whatsapp-templates.index')
            ->with('success', "Template '{$whatsappMessageTemplate->code}' berhasil diperbarui.");
    }

    public function destroy(WhatsappMessageTemplate $whatsappMessageTemplate): RedirectResponse
    {
        if (in_array($whatsappMessageTemplate->code, [
            WhatsappMessageTemplate::CODE_INVOICE_REMINDER,
            WhatsappMessageTemplate::CODE_SCHEDULE_REMINDER,
        ], true)) {
            return back()->with('error', "Template {$whatsappMessageTemplate->code} tidak boleh dihapus. Nonaktifkan saja.");
        }

        $code = $whatsappMessageTemplate->code;
        $whatsappMessageTemplate->delete();

        return redirect()->route('whatsapp-templates.index')
            ->with('success', "Template '{$code}' berhasil dihapus.");
    }
}
