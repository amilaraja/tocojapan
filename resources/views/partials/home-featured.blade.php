@php
    $showStockCounts = app(\App\Settings\GeneralSettings::class)->show_stock_counts;
    // Back-compat: the controller now passes $hotDeals and $latest; older
    // CMS pages may still hand us $featured.
    $hotDealsList = $hotDeals ?? collect();
    $latestList = $latest ?? ($featured ?? collect());
@endphp
<section class="bg-surface mt-10">
    <div class="max-w-[1600px] mx-auto px-6 2xl:px-8">
        <div class="grid grid-cols-1 lg:grid-cols-[240px_minmax(0,1fr)_240px] gap-6">

            {{-- LEFT SIDEBARS --}}
            <div class="hidden lg:flex flex-col gap-6">
                @include('partials.home-sidebar-makes', ['makesWithCounts' => $makesWithCounts, 'showStockCounts' => $showStockCounts])
                @include('partials.home-sidebar-countries')
            </div>

            {{-- MAIN COLUMN --}}
            <div class="flex flex-col gap-10 min-w-0">

                {{-- HOT DEAL CAROUSEL --}}
                @if ($hotDealsList->isNotEmpty())
                    @include('partials.home-hot-deals', ['hotDeals' => $hotDealsList])
                @endif


                {{-- RECENTLY VIEWED (renders client-side from localStorage) --}}
                @include('partials.home-recently-viewed')

                {{-- LATEST STOCK GRID --}}
                <div>
                    @include('partials.home-section-heading', [
                        'kicker' => 'Just in',
                        'heading' => 'Latest Stock',
                        'icon' => 'star',
                        'sublabel' => $showStockCounts
                            ? number_format($totalPublished ?? 0) . ' vehicles currently listed for export.'
                            : 'Quality used vehicles currently listed for export.',
                        'viewAllUrl' => route('vehicles.index'),
                    ])

                    @if ($latestList->isEmpty())
                        <div class="bg-white border border-line rounded-sm p-8 text-center text-ink-soft">No published vehicles yet.</div>
                    @else
                        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 xl:grid-cols-4 gap-3">
                            {{-- Two rows at every width: 1/2/3/4 columns → 2/4/6/8 cards. --}}
                            @foreach ($latestList->take(8) as $vehicle)
                                @php($rowClass = match (true) { $loop->index < 2 => '', $loop->index < 4 => 'hidden sm:block', $loop->index < 6 => 'hidden md:block', default => 'hidden xl:block' })
                                <div class="{{ $rowClass }}">
                                    <x-vehicle-card :vehicle="$vehicle" :priority="$loop->first" />
                                </div>
                            @endforeach
                        </div>
                        <div class="mt-4 text-right">
                            <a href="{{ route('vehicles.index') }}" class="text-sm font-bold text-toco-red hover:text-toco-red-deep inline-flex items-center gap-1">
                                View all <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><path d="m9 6 6 6-6 6"/></svg>
                            </a>
                        </div>
                    @endif
                </div>

                {{-- DEALER STOCK CAROUSEL — partner/supplier vehicles only --}}
                @if (($dealerStock ?? collect())->isNotEmpty())
                    @include('partials.home-hot-deals', ['hotDeals' => $dealerStock, 'carousel' => [
                        'kicker' => 'Partner network',
                        'heading' => 'Dealer Stock',
                        'icon' => 'star',
                        'ribbon' => 'Dealer Stock',
                        'ribbonClass' => 'bg-toco-navy',
                        'viewAllUrl' => route('vehicles.index').'?supplier='.($dealerStockSupplier ?? ''),
                    ]])
                @endif
            </div>

            {{-- RIGHT SIDEBARS --}}
            <div class="hidden lg:flex flex-col gap-6">
                @include('partials.home-sidebar-top-banner', ['content' => $content ?? []])
                @include('partials.home-sidebar-bodytypes', ['bodyTypesWithCounts' => $bodyTypesWithCounts, 'showStockCounts' => $showStockCounts])
                @include('partials.home-sidebar-seasonal', ['content' => $content ?? []])
            </div>
        </div>
    </div>
</section>
