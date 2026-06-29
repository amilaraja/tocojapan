<x-layouts.cms :page="$page">
    @php
        $d = $page->data ?? [];
        $img = function (?string $path): string {
            if (! $path) {
                return '';
            }
            if (str_starts_with($path, '/') || str_starts_with($path, 'http')) {
                return $path;
            }
            return \Illuminate\Support\Facades\Storage::disk('public')->url($path);
        };
        // Step navigator data — alternating red/navy tones across the 5 circles.
        $steps = [
            ['n' => '01', 'label' => 'Search & Order',       'href' => '#step-1', 'tone' => 'red',  'icon' => 'search'],
            ['n' => '02', 'label' => 'Payment',              'href' => '#step-2', 'tone' => 'navy', 'icon' => 'payment'],
            ['n' => '03', 'label' => 'Car Shipment',         'href' => '#step-3', 'tone' => 'red',  'icon' => 'ship'],
            ['n' => '04', 'label' => 'Documents',            'href' => '#step-4', 'tone' => 'navy', 'icon' => 'docs'],
            ['n' => '05', 'label' => 'Receive Your Vehicle', 'href' => '#step-5', 'tone' => 'red',  'icon' => 'receive'],
        ];
    @endphp

    {{-- ============ HERO ============ --}}
    <section class="relative bg-gradient-to-br from-toco-navy to-toco-navy-deep text-white overflow-hidden">
        <div class="pointer-events-none absolute -top-[10%] -right-10 w-44 h-[120%]"
             style="background: linear-gradient(-115deg, transparent 0 38%, #E30613 38% 46%, transparent 46% 54%, #1A1A1A 54% 60%, transparent 60% 70%, #E30613 70% 74%, transparent 74%);"
             aria-hidden="true"></div>
        <div class="relative max-w-[1440px] mx-auto px-6 py-14 md:py-24">
            <div class="grid grid-cols-1 lg:grid-cols-[1.05fr_0.95fr] gap-10 lg:gap-12 items-center">
                <div>
                    @if (! empty($d['eyebrow']))
                        <p class="font-mono text-[12px] uppercase tracking-[0.2em] font-bold text-[#FF4D58] mb-3">{{ $d['eyebrow'] }}</p>
                    @endif
                    <h1 class="font-extrabold leading-[0.95] tracking-tight text-[clamp(46px,7vw,96px)] mb-4">
                        {{ $d['headline_lead'] ?? 'How to' }}
                        @if (! empty($d['headline_accent']))
                            <span class="text-toco-red">{{ $d['headline_accent'] }}</span>
                        @endif
                    </h1>
                    @if (! empty($d['subtitle']))
                        <p class="text-white/70 max-w-2xl text-[clamp(15px,1.4vw,18px)] leading-[1.55]">{!! nl2br(e($d['subtitle'])) !!}</p>
                    @endif
                    <div class="flex flex-wrap gap-3 mt-7">
                        @if (! empty($d['cta_primary_label']))
                            <a href="{{ $d['cta_primary_url'] ?? '#step-1' }}"
                               class="bg-toco-red hover:bg-toco-red-deep text-white font-bold uppercase tracking-widest text-xs px-5 py-3 rounded-sm inline-flex items-center gap-2">
                                {{ $d['cta_primary_label'] }}
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
                            </a>
                        @endif
                        @if (! empty($d['cta_secondary_label']))
                            <a href="{{ $d['cta_secondary_url'] ?? '/vehicles' }}"
                               class="border border-white/30 hover:bg-white/10 text-white font-bold uppercase tracking-widest text-xs px-5 py-3 rounded-sm">
                                {{ $d['cta_secondary_label'] }}
                            </a>
                        @endif
                    </div>
                </div>
                @if (! empty($d['video_thumb']))
                    <div>
                        <div class="relative aspect-video bg-black border border-white/10 overflow-hidden">
                            <img src="{{ $img($d['video_thumb']) }}" alt="" class="w-full h-full object-cover opacity-70">
                            @if (! empty($d['video_url']))
                                <a href="{{ $d['video_url'] }}" target="_blank" rel="noopener" aria-label="Play video"
                                   class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-20 h-20 rounded-full bg-white/95 hover:scale-110 transition-transform text-toco-red grid place-items-center shadow-2xl">
                                    <svg width="28" height="28" viewBox="0 0 24 24" fill="currentColor"><path d="M8 5v14l11-7z"/></svg>
                                </a>
                            @else
                                <span aria-hidden class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-20 h-20 rounded-full bg-white/95 text-toco-red grid place-items-center shadow-2xl">
                                    <svg width="28" height="28" viewBox="0 0 24 24" fill="currentColor"><path d="M8 5v14l11-7z"/></svg>
                                </span>
                            @endif
                            @if (! empty($d['video_caption']))
                                <div class="absolute bottom-3 left-3 bg-black/65 text-white px-2.5 py-1.5 font-mono text-[11px] tracking-wider">{{ $d['video_caption'] }}</div>
                            @endif
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </section>

    {{-- ============ STEP NAVIGATOR ============ --}}
    <section class="bg-white">
        <div class="max-w-[1440px] mx-auto px-6 pt-14 md:pt-20 pb-10">
            <div class="text-center mb-8">
                @if (! empty($d['nav_eyebrow']))
                    <p class="font-mono text-[11px] uppercase tracking-[0.18em] text-toco-red font-bold">{{ $d['nav_eyebrow'] }}</p>
                @endif
                <h2 class="font-extrabold text-toco-navy tracking-tight mt-2 text-[clamp(28px,3.4vw,42px)]">{{ $d['nav_headline'] ?? 'Your journey to your new vehicle.' }}</h2>
            </div>

            {{-- Numbered circles + arrows --}}
            <ol class="flex items-center justify-center gap-2 sm:gap-4 lg:gap-5 max-w-[880px] mx-auto mb-8 flex-wrap sm:flex-nowrap list-none p-0">
                @foreach ($steps as $i => $s)
                    <li>
                        <a href="{{ $s['href'] }}"
                           class="w-12 h-12 sm:w-14 sm:h-14 rounded-full grid place-items-center text-white font-mono font-bold text-base tracking-wide hover:scale-110 transition-transform shadow-md
                                  {{ $s['tone'] === 'red' ? 'bg-toco-red' : 'bg-toco-navy' }}">
                            {{ $s['n'] }}
                        </a>
                    </li>
                    @if ($i < count($steps) - 1)
                        <li aria-hidden class="text-ink-soft/60 hidden sm:flex items-center">
                            <svg width="28" height="14" viewBox="0 0 28 14" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M0 7 H24 M18 1 L24 7 L18 13"/></svg>
                        </li>
                    @endif
                @endforeach
            </ol>

            {{-- 5 icon cards anchored to the detail sections --}}
            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3 max-w-[1100px] mx-auto">
                @foreach ($steps as $s)
                    <a href="{{ $s['href'] }}"
                       class="group flex flex-col items-center text-center gap-3 px-3 py-5 bg-white border border-line hover:border-toco-red hover:-translate-y-0.5 hover:text-toco-red transition-all text-ink no-underline">
                        <div class="w-16 h-16 grid place-items-center text-toco-navy group-hover:text-toco-red">
                            @switch($s['icon'])
                                @case('search')
                                    <svg width="56" height="56" viewBox="0 0 64 64" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M8 40 L8 32 L18 28 L32 26 L46 28 L54 32 L54 40 Z" fill="currentColor" fill-opacity="0.08"/><circle cx="18" cy="42" r="4"/><circle cx="44" cy="42" r="4"/><path d="M8 40 L8 32 L18 28 L32 26 L46 28 L54 32 L54 40"/><circle cx="40" cy="20" r="8"/><path d="M46 26 L52 32"/></svg>
                                    @break
                                @case('payment')
                                    <svg width="56" height="56" viewBox="0 0 64 64" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><rect x="6" y="14" width="52" height="36" rx="3" fill="currentColor" fill-opacity="0.08"/><rect x="6" y="14" width="52" height="36" rx="3"/><line x1="6" y1="24" x2="58" y2="24"/><rect x="42" y="36" width="10" height="8" fill="currentColor"/><circle cx="34" cy="38" r="6"/><path d="M34 33 L34 38 L37 41"/></svg>
                                    @break
                                @case('ship')
                                    <svg width="56" height="56" viewBox="0 0 64 64" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M4 48 c4 4 8 4 12 0 c4 -4 8 -4 12 0 c4 4 8 4 12 0 c4 -4 8 -4 12 0"/><path d="M10 44 L8 36 L56 36 L52 44 Z" fill="currentColor" fill-opacity="0.08"/><path d="M10 44 L8 36 L56 36 L52 44"/><rect x="20" y="20" width="24" height="16" fill="currentColor" fill-opacity="0.12"/><rect x="20" y="20" width="24" height="16"/><line x1="32" y1="20" x2="32" y2="36"/><path d="M32 8 L32 20"/></svg>
                                    @break
                                @case('docs')
                                    <svg width="56" height="56" viewBox="0 0 64 64" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><rect x="14" y="8" width="36" height="48" rx="2" fill="currentColor" fill-opacity="0.08"/><rect x="14" y="8" width="36" height="48" rx="2"/><rect x="24" y="4" width="16" height="8" rx="1" fill="currentColor"/><path d="M22 26 L42 26"/><path d="M22 34 L36 34"/><path d="M22 42 L40 42"/></svg>
                                    @break
                                @case('receive')
                                    <svg width="56" height="56" viewBox="0 0 64 64" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M6 44 L6 36 L16 32 L32 30 L44 32 L52 36 L52 44 Z" fill="currentColor" fill-opacity="0.08"/><circle cx="16" cy="46" r="4"/><circle cx="42" cy="46" r="4"/><path d="M6 44 L6 36 L16 32 L32 30 L44 32 L52 36 L52 44"/><circle cx="56" cy="16" r="6"/><path d="M56 22 L56 32 M50 28 L56 32 L62 28"/></svg>
                                    @break
                            @endswitch
                        </div>
                        <div class="font-bold text-[13px] leading-tight">{{ $s['label'] }}</div>
                    </a>
                @endforeach
            </div>
        </div>
    </section>

    {{-- ============ STEP DETAIL BLOCKS ============ --}}
    <div class="bg-toco-silver-2 py-6 md:py-12">
        <div class="max-w-[1440px] mx-auto px-6 space-y-6">
            @foreach (['1','2','3','4','5'] as $num)
                @php
                    $title = $d["step_{$num}_title"] ?? '';
                    $items = is_array($d["step_{$num}_items"] ?? null) ? $d["step_{$num}_items"] : [];
                    $body = $d["step_{$num}_body"] ?? null;
                    $subhead = $d["step_{$num}_subhead"] ?? null;
                    $callout = $d["step_{$num}_callout"] ?? null;
                    $mediaType = $d["step_{$num}_media_type"] ?? 'image';
                    $mediaImage = $d["step_{$num}_media_image"] ?? null;
                    $mediaTape = $d["step_{$num}_media_tape"] ?? null;
                    $mediaTapeColor = $d["step_{$num}_media_tape_color"] ?? 'navy';
                @endphp
                <section id="step-{{ $num }}" class="scroll-mt-24">
                    <div class="bg-white border border-line p-6 md:p-10 grid grid-cols-1 lg:grid-cols-[0.95fr_1.05fr] gap-7 lg:gap-14 items-start">
                        {{-- MEDIA --}}
                        <div class="flex items-center justify-center">
                            @switch($mediaType)
                                @case('search_mock')
                                    <div class="grid grid-cols-[0.85fr_1fr] gap-3.5 bg-white border border-line p-3.5 max-w-[460px] w-full">
                                        <div class="aspect-[4/3] overflow-hidden bg-toco-silver-2">
                                            @if ($mediaImage)
                                                <img src="{{ $img($mediaImage) }}" alt="" class="w-full h-full object-cover">
                                            @endif
                                        </div>
                                        <div class="flex flex-col gap-2 min-w-0">
                                            <div class="font-extrabold text-ink text-[12px] tracking-tight">2024 NISSAN SERENA</div>
                                            <div class="font-mono text-[16px] font-bold text-toco-red leading-tight">$32,500
                                                <div class="font-sans text-[10px] text-ink-soft font-medium mt-0.5">+ Freight $2,150</div>
                                            </div>
                                            <button class="bg-toco-red text-white font-bold text-[10px] uppercase tracking-wider px-2.5 py-2 text-center">Back to the buy-now quote</button>
                                            <div class="text-[10px] text-ink-soft font-semibold uppercase tracking-wider">Estimate CIF to your port</div>
                                            <div class="flex gap-1.5 flex-wrap">
                                                <span class="bg-[#F4F5F7] border border-line px-2.5 py-1.5 text-[10px] font-semibold text-ink">Australia (AU)</span>
                                                <span class="bg-[#F4F5F7] border border-line px-2.5 py-1.5 text-[10px] font-semibold text-ink">Darwin</span>
                                            </div>
                                            <button class="bg-transparent text-toco-navy border border-toco-navy font-bold text-[10px] uppercase tracking-wider px-2.5 py-2">Calculate</button>
                                            <div class="border-t border-line pt-2 grid grid-cols-2 gap-x-2 gap-y-1 text-[10px]">
                                                <div class="flex justify-between"><span class="text-ink-soft">Year</span><span class="font-bold text-ink">2024</span></div>
                                                <div class="flex justify-between"><span class="text-ink-soft">Mileage</span><span class="font-bold text-ink">4,800 KM</span></div>
                                                <div class="flex justify-between"><span class="text-ink-soft">Engine</span><span class="font-bold text-ink">1500cc</span></div>
                                                <div class="flex justify-between"><span class="text-ink-soft">Trans</span><span class="font-bold text-ink">CVT Automatic</span></div>
                                            </div>
                                        </div>
                                    </div>
                                    @break

                                @case('payment')
                                    <div class="grid grid-cols-[auto_1fr] gap-6 items-center bg-white border border-line p-7 w-full">
                                        <div class="flex flex-col gap-2 items-center text-center">
                                            <div class="text-toco-navy">
                                                <svg width="48" height="48" viewBox="0 0 64 64" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M16 36 L16 50 M28 36 L28 50 M40 36 L40 50 M52 36 L52 50"/><path d="M10 50 L58 50"/><path d="M34 8 L8 24 L60 24 L34 8 Z" fill="currentColor" fill-opacity="0.12"/></svg>
                                            </div>
                                            <div class="font-mono text-[11px] tracking-widest text-ink-soft leading-tight">
                                                <strong class="text-ink font-extrabold">BANK</strong><br>TRANSFER
                                            </div>
                                        </div>
                                        <div class="flex flex-col gap-4 items-center text-center">
                                            <div class="flex items-center gap-3.5">
                                                <span class="font-extrabold text-[18px] italic text-[#1A1F71]">VISA</span>
                                                <span class="relative w-9 h-[22px] inline-block">
                                                    <span class="absolute top-0 left-0 w-[22px] h-[22px] rounded-full bg-[#EB001B]"></span>
                                                    <span class="absolute top-0 right-0 w-[22px] h-[22px] rounded-full bg-[#F79E1B]" style="mix-blend-mode: multiply"></span>
                                                </span>
                                                <span class="font-extrabold text-[18px] text-[#006FCF]">AMEX</span>
                                            </div>
                                            <div class="font-extrabold text-[26px] italic">
                                                <span class="text-[#009CDE]">P</span><span class="text-[#003087]">ayPal</span>
                                            </div>
                                            <div class="font-mono text-[10px] tracking-widest text-ink-soft leading-tight">SALES<br>OFFICE</div>
                                        </div>
                                    </div>
                                    @break

                                @case('docs')
                                    <div class="flex flex-col gap-3.5 w-full">
                                        <div class="bg-white border border-line border-l-8 border-l-[#003876] p-7 flex items-center gap-3.5">
                                            <span class="font-black text-[38px] italic text-[#003876] tracking-tighter leading-none">EMS</span>
                                            <span class="font-bold text-[14px] text-[#003876]">国際スピード郵便</span>
                                        </div>
                                        <div class="bg-[#FFCC00] border border-line p-7 flex items-center justify-center">
                                            <span class="font-black italic text-[56px] text-[#D40511] tracking-tighter leading-none">DHL</span>
                                        </div>
                                    </div>
                                    @break

                                @default
                                    @if ($mediaImage)
                                        <div class="relative w-full border border-line overflow-hidden">
                                            <img src="{{ $img($mediaImage) }}" alt="" class="block w-full aspect-[4/3] object-cover">
                                            @if ($mediaTape)
                                                <div class="absolute top-3.5 left-3.5 text-white font-mono font-semibold text-[10px] tracking-widest px-2.5 py-1.5 {{ $mediaTapeColor === 'green' ? 'bg-emerald-600/90' : 'bg-toco-navy/85' }}">{{ $mediaTape }}</div>
                                            @endif
                                        </div>
                                    @endif
                            @endswitch
                        </div>

                        {{-- COPY --}}
                        <div>
                            <div class="flex items-center gap-4 mb-5">
                                <span class="font-mono text-[13px] font-bold tracking-widest text-toco-red uppercase">Step {{ $num }}</span>
                                <span aria-hidden class="flex-none w-8 h-px bg-line"></span>
                                <span class="font-extrabold text-ink tracking-tight leading-tight text-[clamp(22px,2.4vw,30px)]">{{ $title }}</span>
                            </div>

                            <div class="space-y-2">
                                @if ($subhead)
                                    <div class="font-mono text-[12px] font-bold tracking-widest text-toco-red uppercase">{{ $subhead }}</div>
                                @endif

                                @if (! empty($items))
                                    <ol class="list-none p-0 m-0">
                                        @foreach ($items as $it)
                                            <li class="py-2.5 text-[15px] leading-relaxed text-ink border-b border-line last:border-b-0">
                                                @if (! empty($it['marker']))
                                                    <strong class="inline-block font-mono text-toco-red font-bold mr-2.5 min-w-[18px]">{{ $it['marker'] }}.</strong>
                                                @endif
                                                <span class="prose prose-sm prose-em:not-italic prose-em:text-toco-navy prose-em:font-semibold inline [&_p]:inline">{!! $it['body'] ?? '' !!}</span>
                                            </li>
                                        @endforeach
                                    </ol>
                                @endif

                                @if ($body)
                                    <div class="prose prose-sm max-w-none text-ink prose-p:text-[15px] prose-p:leading-relaxed prose-em:not-italic prose-em:text-toco-navy prose-em:font-semibold">{!! $body !!}</div>
                                @endif

                                @if ($callout)
                                    <div class="mt-4 px-5 py-3.5 bg-[#FFF5F5] border-l-4 border-toco-red text-[14px] font-semibold italic text-toco-red">{{ $callout }}</div>
                                @endif

                                @if ($num === '2' && ! empty($d['step_2_subhead_b']))
                                    <div class="font-mono text-[12px] font-bold tracking-widest text-toco-red uppercase mt-4">{{ $d['step_2_subhead_b'] }}</div>
                                    <div class="prose prose-sm max-w-none text-ink prose-p:text-[15px] prose-p:leading-relaxed">{!! $d['step_2_body_b'] ?? '' !!}</div>
                                @endif

                                @if ($num === '4' && ! empty($d['step_4_checklist']))
                                    <ul class="list-none p-0 mt-4 grid grid-cols-1 sm:grid-cols-2 gap-y-2 gap-x-6">
                                        @foreach ($d['step_4_checklist'] as $row)
                                            @if (! empty($row['item']))
                                                <li class="relative pl-6 text-[14px] font-medium text-ink before:content-['✓'] before:absolute before:left-0 before:text-emerald-600 before:font-extrabold">{{ $row['item'] }}</li>
                                            @endif
                                        @endforeach
                                    </ul>
                                @endif

                                @if ($num === '4')
                                    <p class="mt-3.5 text-[13px] italic text-ink-soft flex gap-1.5 items-start">
                                        <span class="text-toco-red font-extrabold">*</span>
                                        Contact your customs clearance agent for guidance — you need to clear your car before importing it.
                                    </p>
                                @endif

                                @if ($num === '5')
                                    <p class="mt-4 text-[18px] font-bold text-toco-red">Enjoy your new vehicle! 🚗</p>
                                @endif
                            </div>
                        </div>
                    </div>
                </section>
            @endforeach
        </div>
    </div>
</x-layouts.cms>
