<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Services\EventLifecycleService;
use Illuminate\Http\Request;

class EventLifecycleController extends Controller
{
    public function publish(Request $request, Event $event, EventLifecycleService $service)
    {
        $service->publish($event, $request->user());

        return redirect()->route('events.show', $event)->with('success', 'Event berhasil dipublikasikan.');
    }
}
