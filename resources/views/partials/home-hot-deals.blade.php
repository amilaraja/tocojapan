@props(['hotDeals'])
<div x-data="hotDealCarousel()" x-init="init()">
    @include('partials.home-section-heading', [
        'kicker' => 'Limited time',
        'heading' => 'Hot Deal',
        'icon' => 'fire',
        'sublabel' => null,
        'viewAllUrl' => route('vehicles.index').'?featured=1',
    ])

    <div class="relative">
        {{-- scroll arrows --}}
        <button type="button" @click="scrollByCard(-1)" x-show="canPrev" x-cloak aria-label="Previous"
                class="hidden md:grid place-items-center absolute left-0 top-1/2 -translate-y-1/2 -translate-x-1/2 z-10 w-9 h-9 bg-white border border-line shadow rounded-full hover:bg-toco-silver-2">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#10143A" stroke-width="3"><path d="m15 6-6 6 6 6"/></svg>
        </button>
        <button type="button" @click="scrollByCard(1)" x-show="canNext" x-cloak aria-label="Next"
                class="hidden md:grid place-items-center absolute right-0 top-1/2 -translate-y-1/2 translate-x-1/2 z-10 w-9 h-9 bg-white border border-line shadow rounded-full hover:bg-toco-silver-2">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#10143A" stroke-width="3"><path d="m9 6 6 6-6 6"/></svg>
        </button>

        <div x-ref="track" @scroll.passive="updateBounds()"
             class="flex gap-3 overflow-x-auto snap-x snap-mandatory scroll-smooth pb-2 -mx-2 px-2"
             style="scrollbar-width: thin;">
            @foreach ($hotDeals as $vehicle)
                <div class="snap-start shrink-0 w-[88%] sm:w-[48%] md:w-[32%] xl:w-[24%]">
                    <div class="relative">
                        {{-- diagonal HOT ribbon (top-left) --}}
                        <div class="absolute top-0 left-0 z-10 pointer-events-none">
                            <div class="bg-toco-red text-white font-extrabold uppercase tracking-widest text-[10px] px-3 py-1 shadow-md"
                                 style="clip-path: polygon(0 0, 100% 0, 90% 100%, 0 100%);">
                                Hot Deal
                            </div>
                        </div>
                        <x-vehicle-card :vehicle="$vehicle" />
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>

@once
@push('scripts')
<script>
    function hotDealCarousel() {
        return {
            canPrev: false,
            canNext: true,
            init() {
                this.$nextTick(() => this.updateBounds());
                window.addEventListener('resize', () => this.updateBounds());
            },
            updateBounds() {
                const el = this.$refs.track;
                if (!el) return;
                this.canPrev = el.scrollLeft > 4;
                this.canNext = el.scrollLeft + el.clientWidth < el.scrollWidth - 4;
            },
            scrollByCard(dir) {
                const el = this.$refs.track;
                const card = el.querySelector(':scope > div');
                const step = card ? card.getBoundingClientRect().width + 12 : el.clientWidth * 0.8;
                el.scrollBy({ left: step * dir, behavior: 'smooth' });
            },
        };
    }
</script>
@endpush
@endonce
