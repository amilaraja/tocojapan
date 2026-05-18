<x-layouts.cms :page="$page">
    @php($d = $page->data ?? [])

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
        </div>
    </section>

    <section class="max-w-[1100px] mx-auto px-6 py-10 space-y-12">
        @php($hasAny = false)
        @foreach ($regionOrder as $region)
            @php($countries = $countriesByRegion[$region] ?? collect())
            @if ($countries->isNotEmpty())
                @php($hasAny = true)
                <div>
                    <h2 class="text-xl font-extrabold text-toco-navy border-b-2 border-toco-red pb-2 mb-5">{{ $region }}</h2>
                    <div class="space-y-6">
                        @foreach ($countries as $country)
                            <div class="bg-white border border-line rounded-sm overflow-hidden">
                                <div class="px-4 py-3 bg-toco-silver-2 border-b border-line">
                                    <h3 class="font-bold text-toco-navy text-sm">{{ $country->name }}</h3>
                                </div>
                                <div class="overflow-x-auto">
                                    <table class="w-full text-sm">
                                        <thead>
                                            <tr class="text-left font-mono text-[10px] uppercase tracking-widest text-ink-soft border-b border-line">
                                                <th class="px-4 py-2 font-semibold">Destination port</th>
                                                <th class="px-4 py-2 font-semibold">Age limit</th>
                                                <th class="px-4 py-2 font-semibold">Shipment time</th>
                                                <th class="px-4 py-2 font-semibold">Notes</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-line">
                                            @foreach ($country->importRegulations as $reg)
                                                <tr class="align-top">
                                                    <td class="px-4 py-2.5 font-semibold text-toco-navy">
                                                        {{ $reg->ports->pluck('name')->join(', ') ?: 'All ports' }}
                                                        @if ($reg->short_description)
                                                            <span class="block font-normal text-[12px] text-ink-soft mt-0.5">{{ $reg->short_description }}</span>
                                                        @endif
                                                    </td>
                                                    <td class="px-4 py-2.5">{{ $reg->year_restriction ?: '—' }}</td>
                                                    <td class="px-4 py-2.5">{{ $reg->time_of_shipment ?: '—' }}</td>
                                                    <td class="px-4 py-2.5 text-ink-soft whitespace-pre-line">{{ $reg->comments ?: '—' }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
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
</x-layouts.cms>
