<x-layouts.cms :page="$page">
    @php
        $d = $page->data ?? [];
        $steps = collect($d['steps'] ?? [])
            ->map(fn ($s) => [
                'title' => trim($s['title'] ?? ''),
                'description' => trim($s['description'] ?? ''),
                'icon' => trim($s['icon'] ?? ''),
            ])
            ->filter(fn ($s) => $s['title'] !== '')
            ->values();

        $palette = ['#E8920A', '#E0314B', '#5B4FB0', '#1C8C9C', '#3F9E47'];

        $iconUrl = function (string $icon): ?string {
            if ($icon === '') {
                return null;
            }
            if (\Illuminate\Support\Str::startsWith($icon, ['http://', 'https://', '/'])) {
                return $icon;
            }

            return \Illuminate\Support\Facades\Storage::disk('public')->url($icon);
        };
    @endphp

    <section class="bg-gradient-to-b from-toco-navy to-toco-navy-deep text-white">
        <div class="max-w-[1280px] mx-auto px-6 pt-12 md:pt-14 pb-3">
            <p class="font-mono text-[11px] uppercase tracking-[0.2em] text-toco-red font-bold">{{ $d['kicker'] ?? 'Buying process' }}</p>
            <h1 class="text-3xl md:text-5xl font-extrabold mt-1.5 leading-tight">{{ $d['headline'] ?? $page->title }}</h1>
        </div>

        @if ($steps->isNotEmpty())
            <div class="max-w-[1280px] mx-auto px-6 pt-6 pb-20">
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
                    @foreach ($steps as $i => $step)
                        @php($c = $palette[$i % count($palette)])
                        <div class="flex flex-col items-center">
                            <div class="w-full bg-white rounded-md shadow-xl overflow-hidden flex flex-col flex-1">
                                <div class="text-white text-center font-extrabold text-[15px] py-3 px-2" style="background-color: {{ $c }}">
                                    {{ $step['title'] }}
                                </div>
                                <div class="px-4 py-4 text-center text-[12px] text-ink-soft leading-relaxed flex-1">
                                    {{ $step['description'] }}
                                </div>
                                <div class="text-center font-extrabold text-[44px] leading-none pb-3" style="color: {{ $c }}">
                                    {{ str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT) }}
                                </div>
                                <div class="text-white text-center font-bold text-[11px] uppercase tracking-[0.2em] py-2.5" style="background-color: {{ $c }}">
                                    Step {{ $i + 1 }}
                                </div>
                            </div>
                            @if ($iconUrl($step['icon']))
                                <img src="{{ $iconUrl($step['icon']) }}" alt="{{ $step['title'] }}"
                                     class="mt-2 w-14 h-14 rounded-full object-cover shadow-lg" loading="lazy">
                            @else
                                <div class="mt-2 w-14 h-14 rounded-full grid place-items-center text-white font-extrabold shadow-lg" style="background-color: {{ $c }}">
                                    {{ $i + 1 }}
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    </section>

    @if (! empty($d['intro']))
        <section class="max-w-[800px] mx-auto px-6 py-12">
            <div class="prose max-w-none">{!! $d['intro'] !!}</div>
        </section>
    @endif
</x-layouts.cms>
