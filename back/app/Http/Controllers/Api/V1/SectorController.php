<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Sectors\SectorService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Sectors\ChangeSectorStatusRequest;
use App\Http\Requests\Sectors\SectorRequest;
use App\Http\Resources\SectorResource;
use App\Models\Event;
use App\Models\Sector;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class SectorController extends Controller
{
    public function __construct(private readonly SectorService $sectors) {}

    /**
     * Setores do evento ao alcance do usuário (o próprio setor para Gestor/Editor).
     */
    public function index(Request $request, Event $event): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Sector::class);

        $sectors = $event->sectors()
            ->visibleTo($request->user())
            ->when($request->has('active'), fn ($q) => $q->where('active', $request->boolean('active')))
            ->orderBy('name')
            ->get();

        return SectorResource::collection($sectors);
    }

    public function store(SectorRequest $request, Event $event): SectorResource
    {
        return SectorResource::make($this->sectors->create($event, $request->validated()));
    }

    public function show(Sector $sector): SectorResource
    {
        Gate::authorize('view', $sector);

        return SectorResource::make($sector);
    }

    public function update(SectorRequest $request, Sector $sector): SectorResource
    {
        return SectorResource::make($this->sectors->update($sector, $request->validated()));
    }

    public function updateStatus(ChangeSectorStatusRequest $request, Sector $sector): SectorResource
    {
        return SectorResource::make($this->sectors->changeStatus($sector, $request->boolean('active')));
    }
}
