<?php

namespace App\Http\Controllers;

use App\Http\Requests\CheckWhatsAppConnectionRequest;
use App\Http\Requests\SendWhatsAppTestRequest;
use App\Models\AuditLog;
use App\Services\WhatsAppService;
use Illuminate\Http\RedirectResponse;

class WhatsAppConnectionController extends Controller
{
    public function check(CheckWhatsAppConnectionRequest $request, WhatsAppService $whatsapp): RedirectResponse
    {
        return redirect(route('scheduler.edit').'#whatsapp-diagnostics')->with('whatsapp_result', $whatsapp->forBatch()->checkConnection());
    }

    public function sendTest(SendWhatsAppTestRequest $request, WhatsAppService $whatsapp): RedirectResponse
    {
        $sender = $whatsapp->forBatch();
        $provider = $sender->provider();
        $result = $sender->sendText($request->validated('phone'), "Pesan uji WhatsApp Musik KITA. Provider: {$provider}. Pesan ini dikirim secara manual oleh Owner.");
        $feedback = [
            'provider' => $result['provider'],
            'state' => $result['ok'] ? 'sent' : 'failed',
            'message' => $result['ok'] ? 'Pesan uji berhasil dikirim melalui provider. Periksa penerimaan di WhatsApp tujuan.' : $result['error'],
            'message_ids' => $result['message_ids'],
        ];
        AuditLog::record(
            action: AuditLog::ACTION_CREATE,
            entity: null,
            entityLabel: 'WhatsApp Test',
            newValues: $feedback + ['phone' => $sender->normalizePhone($request->validated('phone'))],
        );

        return redirect(route('scheduler.edit').'#whatsapp-diagnostics')->with('whatsapp_result', $feedback);
    }
}
