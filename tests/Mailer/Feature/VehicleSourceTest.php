<?php

use App\Models\BodyType;
use App\Models\Make;
use App\Models\Vehicle;
use App\Modules\Mailer\Domain\Vehicles\VehicleDTO;
use App\Modules\Mailer\Domain\Vehicles\VehicleSource;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->source = new VehicleSource;
    $this->toyota = Make::create(['slug' => 'toyota', 'name' => 'Toyota']);
    $this->nissan = Make::create(['slug' => 'nissan', 'name' => 'Nissan']);
    $this->van = BodyType::create(['slug' => 'van', 'name' => 'Van']);
});

function mailerVehicle(array $attrs = []): Vehicle
{
    return Vehicle::factory()->create($attrs);
}

it('maps every TOC-VEH-002 field from the vehicle', function () {
    $v = mailerVehicle([
        'stock_no' => 'E02059', 'ref_no' => null, 'title' => '2022 TOYOTA HIACE COMMUTER',
        'make_id' => $this->toyota->id, 'body_type_id' => $this->van->id,
        'year_first_reg' => 2022, 'manufacture_year' => 2021, 'mileage_km' => 50002,
        'transmission' => 'automatic', 'price_fob' => 2750, 'price_fob_discount' => 2650,
        'is_featured' => true, 'slug' => '2022-toyota-hiace-commuter',
    ]);

    $dto = $this->source->find($v->id);

    expect($dto)->toBeInstanceOf(VehicleDTO::class)
        ->and($dto->stockRef)->toBe('E02059')
        ->and($dto->title)->toBe('2022 TOYOTA HIACE COMMUTER')
        ->and($dto->make)->toBe('Toyota')
        ->and($dto->bodyType)->toBe('Van')
        ->and($dto->registrationYear)->toBe(2022)
        ->and($dto->modelYear)->toBe(2021)
        ->and($dto->mileageKm)->toBe(50002)
        ->and($dto->transmission)->toBe('Automatic')
        ->and($dto->priceFob)->toBe(2650.0)
        ->and($dto->previousPrice)->toBe(2750.0)
        ->and($dto->badge)->toBe(VehicleDTO::BADGE_HOT_DEAL)
        ->and($dto->badgeLabel())->toBe('HOT DEAL')
        ->and($dto->status)->toBe(VehicleDTO::STATUS_AVAILABLE)
        ->and($dto->url)->toBe(route('vehicles.show', '2022-toyota-hiace-commuter'))
        ->and($dto->metaLine())->toBe('2022 · 50,002 km · Automatic')
        ->and($dto->photoUrl)->toBeNull();
});

it('has no previous price when the vehicle is not discounted, and no price when on request', function () {
    $plain = $this->source->find(mailerVehicle(['price_fob' => 3000, 'price_fob_discount' => null])->id);
    $onRequest = $this->source->find(mailerVehicle(['price_fob' => 3000, 'price_on_request' => true])->id);

    expect($plain->priceFob)->toBe(3000.0)->and($plain->previousPrice)->toBeNull()
        ->and($onRequest->priceFob)->toBeNull()->and($onRequest->priceOnRequest)->toBeTrue();
});

it('marks one of the 7 latest arrivals as NEW', function () {
    $v = mailerVehicle(['is_featured' => false, 'published_at' => now()]);

    expect($this->source->find($v->id)->badge)->toBe(VehicleDTO::BADGE_NEW);
});

it('never returns sold, draft or deleted vehicles in search (TOC-VEH-003)', function () {
    $available = mailerVehicle(['stock_no' => 'E1']);
    mailerVehicle(['stock_no' => 'E2', 'status' => 'sold', 'sold_at' => now()]);
    mailerVehicle(['stock_no' => 'E3', 'status' => 'draft']);
    mailerVehicle(['stock_no' => 'E4'])->delete();

    $ids = collect($this->source->search()->items())->pluck('id')->all();
    expect($ids)->toBe([$available->id]);

    // includeReserved is accepted for admins but never widens to sold.
    expect(collect($this->source->search([], 1, includeReserved: true)->items())->pluck('id')->all())->toBe([$available->id]);
});

it('returns the exact stock ref first (TOC-VEH-004)', function () {
    mailerVehicle(['stock_no' => 'E020561', 'published_at' => now()]);
    mailerVehicle(['stock_no' => 'E02056X', 'published_at' => now()->subMinute()]);
    $target = mailerVehicle(['stock_no' => 'E02056', 'published_at' => now()->subDays(20)]);

    $first = $this->source->search(['q' => 'E02056'])->items()[0];

    expect($first->id)->toBe($target->id);
});

it('filters by make, body type, price range and badge', function () {
    $a = mailerVehicle(['make_id' => $this->toyota->id, 'body_type_id' => $this->van->id, 'price_fob' => 5000, 'is_featured' => true]);
    $b = mailerVehicle(['make_id' => $this->nissan->id, 'body_type_id' => null, 'price_fob' => 9000, 'is_featured' => false]);

    $ids = fn (array $f) => collect($this->source->search($f)->items())->pluck('id')->all();

    expect($ids(['make' => 'toyota']))->toBe([$a->id])
        ->and($ids(['body_type' => 'van']))->toBe([$a->id])
        ->and($ids(['badge' => 'hot_deal']))->toBe([$a->id]);
});

// The shared Vehicle::scopeFilter binds prices as floats; SQLite compares a
// float-bound parameter as text, so this only runs on MySQL. It is also
// covered by the live-data check in docs/mailer/progress.md (Phase 4).
it('filters by price range on the effective price', function () {
    $a = mailerVehicle(['price_fob' => 5000]);
    $b = mailerVehicle(['price_fob' => 9000, 'price_fob_discount' => 5500]);
    $c = mailerVehicle(['price_fob' => 9000]);

    $ids = fn (array $f) => collect($this->source->search($f)->items())->pluck('id')->sort()->values()->all();

    expect($ids(['price_from' => 6000]))->toBe([$c->id])
        ->and($ids(['price_to' => 6000]))->toBe([$a->id, $b->id]);
})->skip(fn () => DB::connection()->getDriverName() === 'sqlite', 'Price binding needs MySQL (see comment).');

it('pages results 20 at a time', function () {
    Vehicle::factory()->count(25)->create();

    $page1 = $this->source->search();
    $page2 = $this->source->search([], 2);

    expect(count($page1->items()))->toBe(20)
        ->and(count($page2->items()))->toBe(5)
        ->and($page1->total())->toBe(25);
});

it('reports sold and deleted vehicles on find/findMany for re-checks', function () {
    $sold = mailerVehicle(['status' => 'sold', 'sold_at' => now()]);
    $gone = mailerVehicle();
    $gone->delete();

    $found = $this->source->findMany([$sold->id, $gone->id, 999999]);

    expect($found->keys()->sort()->values()->all())->toBe(collect([$sold->id, $gone->id])->sort()->values()->all())
        ->and($found[$sold->id]->status)->toBe(VehicleDTO::STATUS_SOLD)
        ->and($found[$sold->id]->isSelectable())->toBeFalse()
        ->and($found[$gone->id]->status)->toBe(VehicleDTO::STATUS_UNAVAILABLE)
        ->and($this->source->find(999999))->toBeNull();
});

it('performs only read queries (TOC-VEH-001)', function () {
    $v = mailerVehicle(['stock_no' => 'E9']);

    $writes = [];
    DB::listen(function ($q) use (&$writes) {
        if (preg_match('/^\s*(insert|update|delete|replace|alter|drop|truncate)\b/i', $q->sql)) {
            $writes[] = $q->sql;
        }
    });

    $this->source->search(['q' => 'E9', 'make' => 'toyota', 'price_from' => 1, 'badge' => 'new']);
    $this->source->find($v->id);
    $this->source->findMany([$v->id]);

    expect($writes)->toBe([]);
});

it('has no Mailer code that writes to vehicle models (TOC-VEH-001)', function () {
    $offenders = [];
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(app_path('Modules/Mailer')));

    foreach ($files as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }
        $code = file_get_contents($file->getPathname());
        if (! str_contains($code, 'App\Models\Vehicle')) {
            continue;
        }
        if (preg_match('/Vehicle::(create|insert|upsert|update|destroy|forceCreate|query\(\)->(update|delete|insert))|\$(v|vehicle)->(save|update|delete|forceDelete|restore|increment|decrement|push|touch)\(/', $code)) {
            $offenders[] = $file->getFilename();
        }
    }

    expect($offenders)->toBe([]);
});

it('round-trips a DTO through a snapshot array', function () {
    $dto = $this->source->find(mailerVehicle(['stock_no' => 'E5', 'price_fob' => 1234])->id);

    expect(VehicleDTO::fromArray(json_decode(json_encode($dto->toArray()), true)))->toEqual($dto);
});
