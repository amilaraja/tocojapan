<?php

namespace App\Modules\PartnerApi\Http\Controllers;

use App\Models\BodyType;
use App\Models\Country;
use App\Models\Make;
use App\Models\Port;
use App\Models\VehicleModel;
use App\Modules\PartnerApi\Domain\Stock\PartnerScope;
use App\Modules\PartnerApi\Http\Middleware\AuthenticatePartner;
use App\Modules\PartnerApi\Support\ETag;
use App\Modules\PartnerApi\Support\StrictInput;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;

/**
 * Reference lists (FR-CAT-01). Makes, models and body types carry the
 * number of vehicles currently in the partner's scope.
 */
class CatalogController
{
    public function makes(Request $request): Response
    {
        StrictInput::only($request, []);
        $counts = $this->counts($request, 'make_id');

        return ETag::respond($request, ['data' => Make::query()->where('is_active', true)->orderBy('name')->get()
            ->map(fn (Make $make) => ['slug' => $make->slug, 'name' => $make->name, 'vehicle_count' => $counts[$make->id] ?? 0])
            ->values()->all()]);
    }

    public function models(Request $request): Response
    {
        StrictInput::only($request, ['make']);
        $data = Validator::make($request->query(), ['make' => ['nullable', 'string', 'max:80']])->validate();
        $counts = $this->counts($request, 'vehicle_model_id');

        $models = VehicleModel::query()->with('make')->where('is_active', true)
            ->when($data['make'] ?? null, fn ($query, $make) => $query->whereHas('make', fn ($query) => $query->where('slug', $make)))
            ->orderBy('name')->get();

        return ETag::respond($request, ['data' => $models->map(fn (VehicleModel $model) => [
            'slug' => $model->slug, 'name' => $model->name, 'make' => $model->make?->slug,
            'vehicle_count' => $counts[$model->id] ?? 0,
        ])->values()->all()]);
    }

    public function bodyTypes(Request $request): Response
    {
        StrictInput::only($request, []);
        $counts = $this->counts($request, 'body_type_id');

        return ETag::respond($request, ['data' => BodyType::query()->where('is_active', true)->orderBy('sort_order')->orderBy('name')->get()
            ->map(fn (BodyType $type) => ['slug' => $type->slug, 'name' => $type->name, 'vehicle_count' => $counts[$type->id] ?? 0])
            ->values()->all()]);
    }

    public function countries(Request $request): Response
    {
        StrictInput::only($request, []);

        return ETag::respond($request, ['data' => Country::query()->where('is_active', true)->orderBy('name')->get()
            ->map(fn (Country $country) => ['code' => $country->iso2, 'name' => $country->name])->values()->all()]);
    }

    public function ports(Request $request): Response
    {
        StrictInput::only($request, ['country']);
        $data = Validator::make($request->query(), ['country' => ['nullable', 'string', 'size:2']])->validate();

        $ports = Port::query()->with('country')->where('is_active', true)
            ->whereHas('country', fn ($query) => $query->where('is_active', true)
                ->when($data['country'] ?? null, fn ($query, $code) => $query->where('iso2', strtoupper($code))))
            ->orderBy('name')->get();

        return ETag::respond($request, ['data' => $ports->map(fn (Port $port) => [
            'id' => $port->id, 'name' => $port->name, 'country' => $port->country?->iso2, 'unlocode' => $port->unlocode,
        ])->values()->all()]);
    }

    /** @return array<int, int> */
    private function counts(Request $request, string $column): array
    {
        $partner = $request->attributes->get(AuthenticatePartner::TOKEN)->client->partner;

        return PartnerScope::listable($partner)->whereNotNull('vehicles.'.$column)
            ->groupBy('vehicles.'.$column)->selectRaw('vehicles.'.$column.' as k, COUNT(*) as c')
            ->pluck('c', 'k')->map(fn ($count) => (int) $count)->all();
    }
}
