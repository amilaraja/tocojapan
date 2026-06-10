<x-layouts.cms :page="$page">
    {{-- This page is itself a destination/country picker — don't let the global
         "Set your destination" dialog auto-open over the flag grid. --}}
    <script>window.tocoSuppressDestPrompt = true;</script>

    @php
        $d = $page->data ?? [];

        // Human labels for the structured steering column.
        $steeringLabel = fn (?string $s) => match ($s) {
            'rhd_only' => 'Right-hand drive only',
            'lhd_only' => 'Left-hand drive only',
            default => null,
        };

        // Flat list of every published country slug — used client-side to
        // validate a #hash before opening its popup.
        $validSlugs = collect($countriesByRegion)->flatten(1)->pluck('slug')->values();
    @endphp

    <div x-data="importRegs(@js($validSlugs))" @keydown.escape.window="close()">

        <section class="bg-gradient-to-b from-toco-navy to-toco-navy-deep text-white">
            <div class="max-w-[1100px] mx-auto px-6 py-12 md:py-16">
                @if (! empty($d['kicker']))
                    <p class="font-mono text-[11px] uppercase tracking-[0.2em] text-toco-red font-bold">{{ $d['kicker'] }}</p>
                @endif
                <h1 class="text-3xl md:text-5xl font-extrabold mt-2 leading-tight">
                    {{ $d['headline'] ?? $page->title }}
                </h1>
                @if (! empty($d['intro']))
                    <div class="mt-4 text-white/80 max-w-2xl prose prose-invert">{!! $d['intro'] !!}</div>
                @endif

                {{-- Region quick-nav --}}
                <nav class="mt-6 flex flex-wrap gap-2">
                    @foreach ($regionOrder as $region)
                        @if (($countriesByRegion[$region] ?? collect())->isNotEmpty())
                            <a href="#region-{{ \Illuminate\Support\Str::slug($region) }}"
                               class="font-mono text-[11px] uppercase tracking-widest bg-white/10 hover:bg-toco-red text-white/90 hover:text-white px-3 py-1.5 rounded-sm transition-colors">
                                {{ $region }}
                            </a>
                        @endif
                    @endforeach
                </nav>
            </div>
        </section>

        <section class="max-w-[1100px] mx-auto px-6 py-10 space-y-12">
            <p class="text-sm text-ink-soft -mb-4 inline-flex items-center gap-2">
                <svg class="w-4 h-4 text-toco-red shrink-0" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/></svg>
                Tap any country flag to view its import regulations.
            </p>

            @php $hasAny = false; @endphp
            @foreach ($regionOrder as $region)
                @php $countries = $countriesByRegion[$region] ?? collect(); @endphp
                @if ($countries->isNotEmpty())
                    @php $hasAny = true; @endphp
                    <div id="region-{{ \Illuminate\Support\Str::slug($region) }}" class="scroll-mt-24">
                        <h2 class="text-xl font-extrabold text-toco-navy border-b-2 border-toco-red pb-2 mb-5">{{ $region }}</h2>
                        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6 gap-3">
                            @foreach ($countries as $country)
                                <a href="#{{ $country->slug }}"
                                   @click.prevent="show('{{ $country->slug }}')"
                                   class="group flex flex-col items-center text-center bg-white border border-line rounded-sm p-3 hover:border-toco-red hover:shadow-md hover:-translate-y-0.5 transition-all focus:outline-none focus-visible:ring-2 focus-visible:ring-toco-red"
                                   aria-label="View import regulations for {{ $country->name }}">
                                    <span class="block w-16 h-12 rounded-sm overflow-hidden border border-line shadow-sm shrink-0">
                                        <img src="/img/flags/{{ strtolower($country->iso2) }}.svg"
                                             alt="{{ $country->name }} flag" width="64" height="48" loading="lazy"
                                             class="w-full h-full object-cover">
                                    </span>
                                    <span class="mt-2 text-[13px] font-semibold text-toco-navy leading-tight group-hover:text-toco-red">{{ $country->name }}</span>
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endif
            @endforeach

            @unless ($hasAny)
                <div class="bg-toco-silver-2 border border-line rounded-sm px-6 py-10 text-center text-ink-soft">
                    <p class="font-mono text-[11px] uppercase tracking-widest">No import regulations published yet</p>
                    <p class="text-sm mt-2">Add them in the admin panel under Import Regulations.</p>
                </div>
            @endunless
        </section>

        {{-- ============ Country popup ============ --}}
        <div x-show="open !== null" x-cloak
             class="fixed inset-0 z-[70] flex items-start sm:items-center justify-center p-4 sm:p-6 overflow-y-auto"
             role="dialog" aria-modal="true">
            {{-- Backdrop --}}
            <div x-show="open !== null" x-transition.opacity @click="close()"
                 class="fixed inset-0 bg-toco-navy-deep/70 backdrop-blur-sm"></div>

            {{-- Panel shell: one detail block per country, only the open one shows --}}
            <div class="relative w-full max-w-2xl my-auto"
                 x-show="open !== null"
                 x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="opacity-0 translate-y-3 sm:scale-95"
                 x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100">
                @foreach ($regionOrder as $region)
                    @foreach (($countriesByRegion[$region] ?? collect()) as $country)
                        <div x-show="open === '{{ $country->slug }}'" class="bg-white rounded-sm shadow-2xl overflow-hidden">
                            {{-- Header --}}
                            <div class="flex items-center gap-3 px-5 py-4 bg-toco-navy text-white">
                                <span class="block w-12 h-9 rounded-sm overflow-hidden border border-white/20 shrink-0">
                                    <img src="/img/flags/{{ strtolower($country->iso2) }}.svg" alt="{{ $country->name }} flag" width="48" height="36" class="w-full h-full object-cover">
                                </span>
                                <div class="min-w-0">
                                    <p class="font-mono text-[10px] uppercase tracking-widest text-toco-red font-bold">{{ $region }} · Import regulations</p>
                                    <h2 class="text-lg font-extrabold leading-tight truncate">{{ $country->name }}</h2>
                                </div>
                                <button type="button" @click="close()" aria-label="Close"
                                        class="ml-auto shrink-0 text-white/70 hover:text-white p-1.5 -mr-1.5 rounded-sm hover:bg-white/10">
                                    <svg class="w-5 h-5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>
                                </button>
                            </div>

                            {{-- Body --}}
                            <div class="max-h-[70vh] overflow-y-auto p-5 space-y-4">
                                @forelse ($country->importRegulations as $reg)
                                    @php
                                        // Prefer linked Port records; otherwise fall back to the
                                        // scraped port names stored in short_description.
                                        $pivotPorts = $reg->ports->pluck('name')->join(', ');
                                        $portNames = $pivotPorts ?: ($reg->short_description ?: 'All ports');
                                        // Only show short_description as a sub-note when it isn't
                                        // already being used as the port value.
                                        $portSubNote = $pivotPorts ? $reg->short_description : null;
                                        $rows = array_filter([
                                            'Age limit' => $reg->year_restriction,
                                            'Shipment time' => $reg->time_of_shipment,
                                            'Steering' => $steeringLabel($reg->steering_restriction),
                                            'Inspection' => $reg->inspection,
                                            'Other restrictions' => $reg->other_restrictions,
                                        ], fn ($v) => filled($v));
                                    @endphp
                                    <div class="border border-line rounded-sm">
                                        <div class="px-4 py-2.5 bg-toco-silver-2 border-b border-line">
                                            <p class="font-mono text-[10px] uppercase tracking-widest text-ink-soft">Destination port</p>
                                            <p class="font-bold text-toco-navy text-sm">{{ $portNames }}</p>
                                            @if ($portSubNote)
                                                <p class="text-[12px] text-ink-soft mt-0.5">{{ $portSubNote }}</p>
                                            @endif
                                        </div>
                                        @if (! empty($rows))
                                            <dl class="divide-y divide-line text-sm">
                                                @foreach ($rows as $label => $value)
                                                    <div class="flex gap-3 px-4 py-2.5">
                                                        <dt class="font-mono text-[10px] uppercase tracking-widest text-ink-soft w-32 shrink-0 pt-0.5">{{ $label }}</dt>
                                                        <dd class="text-toco-navy font-medium">{{ $value }}</dd>
                                                    </div>
                                                @endforeach
                                            </dl>
                                        @endif
                                        @if ($reg->comments)
                                            <div class="px-4 py-3 border-t border-line bg-amber-50/50">
                                                <p class="font-mono text-[10px] uppercase tracking-widest text-ink-soft mb-1">Notes</p>
                                                <p class="text-sm text-ink whitespace-pre-line leading-relaxed">{{ $reg->comments }}</p>
                                            </div>
                                        @endif
                                    </div>
                                @empty
                                    <p class="text-sm text-ink-soft">No detailed regulations recorded for this country yet.</p>
                                @endforelse

                                @php
                                    // Pre-filter the listing to vehicles this country actually allows:
                                    // newest allowed year (from the tightest age limit) + steering side.
                                    $ages = $country->importRegulations->pluck('year_max_age')->filter();
                                    $browseParams = [];
                                    if ($ages->isNotEmpty()) {
                                        $browseParams['year_from'] = now()->year - (int) $ages->min();
                                    }
                                    $steer = $country->importRegulations->pluck('steering_restriction')->filter()->first();
                                    if ($steer === 'rhd_only') {
                                        $browseParams['steering'] = 'right';
                                    } elseif ($steer === 'lhd_only') {
                                        $browseParams['steering'] = 'left';
                                    }
                                    $browseUrl = route('vehicles.index', $browseParams);
                                    $firstPort = $country->ports->first();
                                @endphp
                                <div class="pt-1 flex flex-wrap items-center gap-3">
                                    @if ($firstPort)
                                        {{-- Set the global destination (this country + its first port) and
                                             land on the matching, pre-filtered stock listing. --}}
                                        <form method="POST" action="{{ route('destination.set') }}" class="contents">
                                            @csrf
                                            <input type="hidden" name="port_id" value="{{ $firstPort->id }}">
                                            <input type="hidden" name="redirect_to" value="{{ $browseUrl }}">
                                            <button type="submit" class="bg-toco-red hover:bg-toco-red-deep text-white font-bold uppercase tracking-widest text-xs px-4 py-2.5 rounded-sm">
                                                Browse stock for {{ $country->name }}
                                            </button>
                                        </form>
                                    @else
                                        <a href="{{ $browseUrl }}" class="bg-toco-red hover:bg-toco-red-deep text-white font-bold uppercase tracking-widest text-xs px-4 py-2.5 rounded-sm">
                                            Browse stock for {{ $country->name }}
                                        </a>
                                    @endif
                                    <button type="button" @click="close()" class="text-xs font-semibold text-ink-soft hover:text-toco-navy uppercase tracking-widest">Close</button>
                                </div>
                            </div>
                        </div>
                    @endforeach
                @endforeach
            </div>
        </div>
    </div>

    @push('scripts')
        <script>
            function importRegs(validSlugs) {
                return {
                    valid: validSlugs || [],
                    open: null,
                    init() {
                        this.syncFromHash();
                        window.addEventListener('hashchange', () => this.syncFromHash());
                    },
                    slugFromHash() {
                        return decodeURIComponent((window.location.hash || '').replace(/^#/, '')).toLowerCase();
                    },
                    syncFromHash() {
                        const slug = this.slugFromHash();
                        if (slug && this.valid.includes(slug)) {
                            this.open = slug;
                            document.body.classList.add('overflow-hidden');
                        }
                    },
                    show(slug) {
                        if (! this.valid.includes(slug)) return;
                        this.open = slug;
                        document.body.classList.add('overflow-hidden');
                        if (window.history && history.replaceState) {
                            history.replaceState(null, '', '#' + slug);
                        } else {
                            window.location.hash = slug;
                        }
                    },
                    close() {
                        if (this.open === null) return;
                        this.open = null;
                        document.body.classList.remove('overflow-hidden');
                        if (window.history && history.replaceState) {
                            history.replaceState(null, '', window.location.pathname + window.location.search);
                        }
                    },
                };
            }
        </script>
    @endpush
</x-layouts.cms>
