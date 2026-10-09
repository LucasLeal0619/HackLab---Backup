<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Meetings\MeetingService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Meetings\MeetingRequest;
use App\Http\Resources\MeetingResource;
use App\Models\Event;
use App\Models\Meeting;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class MeetingController extends Controller
{
    public function __construct(private readonly MeetingService $meetings) {}

    /**
     * Reuniões gerais + as do setor do usuário (todas para quem tem escopo global).
     */
    public function index(Request $request, Event $event): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Meeting::class);

        $meetings = $event->meetings()
            ->visibleTo($request->user())
            ->with('sector', 'creator.person')
            ->when($request->query('sector_id'), fn ($q, $sectorId) => $q->where('sector_id', $sectorId))
            ->when($request->query('scope') === 'general', fn ($q) => $q->whereNull('sector_id'))
            ->orderBy('scheduled_at')
            ->paginate(min((int) $request->query('per_page', 50), 100));

        return MeetingResource::collection($meetings);
    }

    public function store(MeetingRequest $request, Event $event): MeetingResource
    {
        // Autorização no MeetingRequest (create no setor informado, ou geral).
        return MeetingResource::make($this->meetings->create($event, $request->user(), $request->validated()));
    }

    public function show(Meeting $meeting): MeetingResource
    {
        Gate::authorize('view', $meeting);

        return MeetingResource::make($meeting->load('sector', 'creator.person'));
    }

    public function update(MeetingRequest $request, Meeting $meeting): MeetingResource
    {
        $data = $request->validated();

        if (array_key_exists('sector_id', $data) && $data['sector_id'] !== $meeting->sector_id) {
            // Mover de setor (ou tornar geral) exige poder gerenciar também no destino.
            Gate::authorize('create', [Meeting::class, $data['sector_id']]);
        }

        return MeetingResource::make($this->meetings->update($meeting, $data));
    }
}
