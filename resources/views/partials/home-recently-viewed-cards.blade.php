@php /* Rendered by VehicleController::recentlyViewed() and injected via x-html. */ @endphp
<div class="flex gap-3 overflow-x-auto snap-x snap-mandatory scroll-smooth pb-2 -mx-2 px-2" style="scrollbar-width: thin;">
    @foreach ($vehicles as $vehicle)
        <div class="snap-start shrink-0 w-[88%] sm:w-[48%] md:w-[32%] xl:w-[24%]">
            <x-vehicle-card :vehicle="$vehicle" />
        </div>
    @endforeach
</div>
