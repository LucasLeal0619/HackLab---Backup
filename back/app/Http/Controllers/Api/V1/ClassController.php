<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Classes\ClassService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Classes\ChangeClassStatusRequest;
use App\Http\Requests\Classes\ClassRequest;
use App\Http\Resources\SchoolClassResource;
use App\Models\Event;
use App\Models\SchoolClass;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class ClassController extends Controller
{
    public function __construct(private readonly ClassService $classes) {}

    public function index(Request $request, Event $event): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', SchoolClass::class);

        $classes = SchoolClass::query()
            ->where('event_id', $event->id)
            ->withCount('participants')
            ->when($request->has('active'), fn ($q) => $q->where('active', $request->boolean('active')))
            ->orderBy('name')
            ->paginate(min((int) $request->query('per_page', 50), 100));

        return SchoolClassResource::collection($classes);
    }

    public function store(ClassRequest $request, Event $event): SchoolClassResource
    {
        return SchoolClassResource::make($this->classes->create($event, $request->validated()));
    }

    public function show(SchoolClass $class): SchoolClassResource
    {
        Gate::authorize('view', $class);

        return SchoolClassResource::make($class->loadCount('participants'));
    }

    public function update(ClassRequest $request, SchoolClass $class): SchoolClassResource
    {
        return SchoolClassResource::make($this->classes->update($class, $request->validated()));
    }

    public function updateStatus(ChangeClassStatusRequest $request, SchoolClass $class): SchoolClassResource
    {
        return SchoolClassResource::make($this->classes->changeStatus($class, $request->boolean('active')));
    }
}
