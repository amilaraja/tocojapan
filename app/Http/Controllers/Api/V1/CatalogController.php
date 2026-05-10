<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\BodyType;
use App\Models\Country;
use App\Models\Make;
use App\Models\VehicleModel;
use Illuminate\Http\JsonResponse;

class CatalogController extends Controller
{
    public function makes(): JsonResponse
    {
        $makes = Make::where('is_active', true)
            ->orderBy('sort_order')->orderBy('name')
            ->get(['id', 'slug', 'name'])
            ->map(fn (Make $m) => ['id' => $m->id, 'slug' => $m->slug, 'name' => $m->name])
            ->all();

        return ApiResponse::ok($makes);
    }

    public function models(string $makeSlug): JsonResponse
    {
        $make = Make::where('slug', $makeSlug)->firstOrFail();

        $models = VehicleModel::where('make_id', $make->id)
            ->where('is_active', true)
            ->orderBy('sort_order')->orderBy('name')
            ->get(['id', 'slug', 'name'])
            ->map(fn (VehicleModel $m) => ['id' => $m->id, 'slug' => $m->slug, 'name' => $m->name])
            ->all();

        return ApiResponse::ok($models, ['make' => ['slug' => $make->slug, 'name' => $make->name]]);
    }

    public function bodyTypes(): JsonResponse
    {
        $types = BodyType::where('is_active', true)
            ->orderBy('sort_order')->orderBy('name')
            ->get(['id', 'slug', 'name'])
            ->map(fn (BodyType $b) => ['id' => $b->id, 'slug' => $b->slug, 'name' => $b->name])
            ->all();

        return ApiResponse::ok($types);
    }

    public function countries(): JsonResponse
    {
        $rows = Country::where('is_active', true)
            ->with(['ports' => fn ($q) => $q->where('is_active', true)->orderBy('sort_order')])
            ->orderBy('sort_order')->orderBy('name')
            ->get();

        $countries = [];
        foreach ($rows as $c) {
            $ports = [];
            foreach ($c->ports as $p) {
                $ports[] = [
                    'id' => $p->id,
                    'slug' => $p->slug,
                    'name' => $p->name,
                    'unlocode' => $p->unlocode,
                    'rate_per_m3' => (float) $p->rate_per_m3,
                ];
            }
            $countries[] = [
                'id' => $c->id,
                'iso2' => $c->iso2,
                'slug' => $c->slug,
                'name' => $c->name,
                'currency_code' => $c->currency_code,
                'ports' => $ports,
            ];
        }

        return ApiResponse::ok($countries);
    }
}
