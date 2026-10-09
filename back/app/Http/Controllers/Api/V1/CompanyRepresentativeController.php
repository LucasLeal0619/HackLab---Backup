<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Companies\CompanyRepresentativeService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Companies\ChangeRepresentativeStatusRequest;
use App\Http\Requests\Companies\StoreRepresentativeRequest;
use App\Http\Requests\Companies\UpdateRepresentativeRequest;
use App\Http\Resources\CompanyRepresentativeResource;
use App\Models\Company;
use App\Models\CompanyRepresentative;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class CompanyRepresentativeController extends Controller
{
    public function __construct(private readonly CompanyRepresentativeService $representatives) {}

    /**
     * ?active=1 ou ?active=0 filtra; sem filtro, devolve todos (inclusive histórico).
     */
    public function index(Request $request, Company $company): AnonymousResourceCollection
    {
        Gate::authorize('view', $company);

        $representatives = $company->representatives()
            ->with('person')
            ->when($request->has('active'), fn ($q) => $q->where('active', $request->boolean('active')))
            ->orderByDesc('active')
            ->orderByDesc('is_primary')
            ->orderBy('id')
            ->get();

        return CompanyRepresentativeResource::collection($representatives);
    }

    public function store(StoreRepresentativeRequest $request, Company $company): CompanyRepresentativeResource
    {
        return CompanyRepresentativeResource::make($this->representatives->add($company, $request->validated()));
    }

    public function update(UpdateRepresentativeRequest $request, CompanyRepresentative $representative): CompanyRepresentativeResource
    {
        return CompanyRepresentativeResource::make($this->representatives->update($representative, $request->validated()));
    }

    public function updateStatus(ChangeRepresentativeStatusRequest $request, CompanyRepresentative $representative): CompanyRepresentativeResource
    {
        return CompanyRepresentativeResource::make($this->representatives->changeStatus($representative, $request->boolean('active')));
    }
}
