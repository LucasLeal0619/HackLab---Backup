<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Events\EventService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Events\StoreEventRequest;
use App\Http\Requests\Events\UpdateEventRequest;
use App\Http\Resources\EventResource;
use App\Models\Event;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class EventController extends Controller
{
    public function __construct(private readonly EventService $events) {}

    public function index(): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Event::class);

        return EventResource::collection(Event::query()->with('days')->orderByDesc('start_date')->paginate(20));
    }

    public function store(StoreEventRequest $request): EventResource
    {
        return EventResource::make($this->events->create($request->validated()));
    }

    public function show(Event $event): EventResource
    {
        Gate::authorize('view', $event);

        return EventResource::make($event->load('days'));
    }

    public function update(UpdateEventRequest $request, Event $event): EventResource
    {
        return EventResource::make($this->events->update($event, $request->validated()));
    }
}
