<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Events\EventService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Events\EventDayRequest;
use App\Http\Resources\EventDayResource;
use App\Models\Event;
use App\Models\EventDay;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class EventDayController extends Controller
{
    public function __construct(private readonly EventService $events) {}

    public function index(Event $event): AnonymousResourceCollection
    {
        Gate::authorize('view', $event);

        return EventDayResource::collection($event->days()->get());
    }

    public function store(EventDayRequest $request, Event $event): EventDayResource
    {
        return EventDayResource::make($this->events->addDay($event, $request->validated()));
    }

    public function update(EventDayRequest $request, Event $event, EventDay $day): EventDayResource
    {
        return EventDayResource::make($this->events->updateDay($day, $request->validated()));
    }
}
