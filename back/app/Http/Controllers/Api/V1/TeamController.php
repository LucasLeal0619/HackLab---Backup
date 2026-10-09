<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Teams\TeamService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Teams\AddTeamMemberRequest;
use App\Http\Requests\Teams\TeamRequest;
use App\Http\Resources\TeamMemberResource;
use App\Http\Resources\TeamResource;
use App\Models\Event;
use App\Models\Participant;
use App\Models\Team;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class TeamController extends Controller
{
    private const MEMBER_RELATIONS = ['activeMemberships.participant.person', 'activeMemberships.participant.schoolClass'];

    public function __construct(private readonly TeamService $teams) {}

    public function index(Request $request, Event $event): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Team::class);

        $teams = Team::query()
            ->where('event_id', $event->id)
            ->with('challenge')
            ->withCount('activeMemberships')
            ->when($request->query('status'), fn ($q, string $status) => $q->where('status', $status))
            ->when($request->has('has_challenge'), fn ($q) => $request->boolean('has_challenge')
                ? $q->whereNotNull('challenge_id')
                : $q->whereNull('challenge_id'))
            ->orderBy('name')
            ->paginate(min((int) $request->query('per_page', 50), 100));

        return TeamResource::collection($teams);
    }

    public function store(TeamRequest $request, Event $event): TeamResource
    {
        return TeamResource::make($this->teams->create($event, $request->validated()));
    }

    /**
     * Equipe com a composição atual (membros ativos).
     */
    public function show(Team $team): TeamResource
    {
        Gate::authorize('view', $team);

        return TeamResource::make($team->load([...self::MEMBER_RELATIONS, 'challenge'])->loadCount('activeMemberships'));
    }

    public function update(TeamRequest $request, Team $team): TeamResource
    {
        return TeamResource::make($this->teams->update($team, $request->validated()));
    }

    /**
     * Membros da equipe. ?history=1 inclui vínculos encerrados.
     */
    public function members(Request $request, Team $team): AnonymousResourceCollection
    {
        Gate::authorize('view', $team);

        $members = $team->memberships()
            ->with('participant.person', 'participant.schoolClass')
            ->when(! $request->boolean('history'), fn ($q) => $q->where('active', true))
            ->orderByDesc('active')
            ->orderBy('joined_at')
            ->get();

        return TeamMemberResource::collection($members);
    }

    public function addMember(AddTeamMemberRequest $request, Team $team): TeamResource
    {
        $this->teams->addMember($team, Participant::query()->findOrFail($request->validated('participant_id')));

        return TeamResource::make($team->load(self::MEMBER_RELATIONS)->loadCount('activeMemberships'));
    }

    public function removeMember(Team $team, Participant $participant): Response
    {
        Gate::authorize('manageMembers', $team);

        $this->teams->removeMember($team, $participant);

        return response()->noContent();
    }
}
