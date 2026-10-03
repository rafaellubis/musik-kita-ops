<?php

namespace App\Http\Controllers;

use App\Http\Requests\Event\CancelEventRequest;
use App\Http\Requests\Event\RescheduleEventRequest;
use App\Models\Event;
use App\Services\EventLifecycleService;
use Illuminate\Http\Request;

class EventLifecycleController extends Controller
{
    public function reschedule(RescheduleEventRequest $request, Event $event, EventLifecycleService $service)
    {
        $service->reschedule($event, $request->validated(), $request->user());

        return redirect()->route('events.show', $event)->with('success', 'Jadwal event berhasil diperbarui.');
    }

    public function cancel(CancelEventRequest $request, Event $event, EventLifecycleService $service)
    {
        $service->cancel($event, $request->user(), $request->validated('cancellation_reason'));

        return redirect()->route('events.show', $event)->with('success', 'Event berhasil dibatalkan.');
    }

    public function publish(Request $request, Event $event, EventLifecycleService $service)
    {
        $service->publish($event, $request->user());

        return redirect()->route('events.show', $event)->with('success', 'Event berhasil dipublikasikan.');
    }
}
