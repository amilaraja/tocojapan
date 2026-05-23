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
        $brands = collect($d['brands'] ?? [])->pluck('name')->filter()->values();
        $categories = collect($d['categories'] ?? [])->pluck('label')->filter()->values();
        $commitment = collect($d['commitment_items'] ?? []);
        $quickStats = collect($d['quick_stats'] ?? []);
        $gallery = collect($d['gallery_items'] ?? []);
        $info = collect($d['info_rows'] ?? []);
        $branches = collect($d['branches'] ?? [])->filter(fn ($b) => trim((string) ($b['name'] ?? '')) !== '')->values();
    @endphp

    {{-- ============ Page title band (matches Contact page style) ============ --}}
    <section class="relative bg-gradient-to-br from-toco-navy to-toco-navy-deep text-white overflow-hidden">
        {{-- Right-edge red/black diagonal strips (from design) --}}
        <div class="pointer-events-none absolute -top-[10%] -right-10 w-44 h-[120%]"
             style="background: linear-gradient(-115deg, transparent 0 38%, #E30613 38% 46%, transparent 46% 54%, #1A1A1A 54% 60%, transparent 60% 70%, #E30613 70% 74%, transparent 74%);"
             aria-hidden="true"></div>
        <div class="relative max-w-[1440px] mx-auto px-6 py-14 md:py-24">
            @if (! empty($d['eyebrow']))
                <p class="font-mono text-[12px] uppercase tracking-[0.2em] font-bold text-[#FF4D58] mb-3">{{ $d['eyebrow'] }}</p>
            @endif
            <h1 class="font-extrabold leading-[1.05] tracking-tight text-[clamp(38px,5.4vw,64px)] max-w-3xl">
                {{ $d['headline'] ?? $page->title }}
            </h1>
            @if (! empty($d['subtitle']))
                <p class="text-white/70 mt-4 max-w-2xl text-[clamp(15px,1.4vw,18px)] leading-relaxed">{{ $d['subtitle'] }}</p>
            @endif
        </div>
    </section>

    {{-- ============ Overview ============ --}}
    <section class="bg-white">
        <div class="max-w-[1440px] mx-auto px-6 py-14 md:py-20">
            <div class="grid grid-cols-1 lg:grid-cols-[1.05fr_1fr] gap-10 lg:gap-14 items-start">
                <div>
                    @if (! empty($d['overview_eyebrow']))
                        <p class="font-mono text-[11px] uppercase tracking-[0.18em] text-toco-red font-bold relative pl-4 before:content-[''] before:absolute before:left-0 before:top-1/2 before:-translate-y-1/2 before:w-2 before:h-0.5 before:bg-toco-red">{{ $d['overview_eyebrow'] }}</p>
                    @endif
                    <h2 class="font-extrabold text-toco-navy tracking-tight leading-[1.1] mt-3 text-[clamp(28px,3.4vw,42px)]">{{ $d['overview_headline'] ?? '' }}</h2>
                    @if (! empty($d['overview_body']))
                        <div class="mt-5 prose prose-sm max-w-[56ch] text-ink-soft prose-strong:text-ink prose-p:leading-[1.7] prose-p:text-[15.5px]">
                            {!! $d['overview_body'] !!}
                        </div>
                    @endif
                    @if ($quickStats->isNotEmpty())
                        <div class="mt-8 pt-7 border-t border-line grid grid-cols-2 md:grid-cols-4 gap-3">
                            @foreach ($quickStats as $s)
                                <div>
                                    <div class="font-extrabold tracking-tight text-ink text-[28px] leading-none">
                                        {{ $s['n'] ?? '' }}<span class="text-toco-red text-sm font-bold ml-0.5">{{ $s['suffix'] ?? '' }}</span>
                                    </div>
                                    <div class="font-mono text-[11px] uppercase tracking-[0.1em] font-semibold text-ink-soft mt-1.5">{{ $s['l'] ?? '' }}</div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
                @if (! empty($d['overview_image']))
                    <div class="relative border border-line bg-toco-silver-2 overflow-hidden">
                        <img src="{{ $img($d['overview_image']) }}" alt="{{ $d['overview_image_caption'] ?? '' }}" class="block w-full h-auto aspect-[4/3] object-cover">
                        @if (! empty($d['overview_image_caption']))
                            <div class="absolute left-4 bottom-4 inline-flex items-center gap-2 px-3 py-2 bg-toco-navy/85 backdrop-blur text-white text-[12px] font-semibold tracking-wide">
                                <span class="w-1.5 h-1.5 rounded-full bg-toco-red ring-4 ring-toco-red/25"></span>
                                {{ $d['overview_image_caption'] }}
                            </div>
                        @endif
                    </div>
                @endif
            </div>
        </div>
    </section>

    {{-- ============ Commitment ============ --}}
    @if ($commitment->isNotEmpty())
        <section class="bg-toco-silver-2">
            <div class="max-w-[1440px] mx-auto px-6 py-14 md:py-20">
                <div class="mb-8 max-w-3xl">
                    @if (! empty($d['commitment_eyebrow']))
                        <p class="font-mono text-[11px] uppercase tracking-[0.18em] text-toco-red font-bold relative pl-4 before:content-[''] before:absolute before:left-0 before:top-1/2 before:-translate-y-1/2 before:w-2 before:h-0.5 before:bg-toco-red">{{ $d['commitment_eyebrow'] }}</p>
                    @endif
                    <h2 class="font-extrabold text-toco-navy tracking-tight leading-[1.1] mt-3 text-[clamp(26px,3vw,38px)]">{{ $d['commitment_headline'] ?? '' }}</h2>
                    @if (! empty($d['commitment_body']))
                        <p class="text-ink-soft mt-3 text-[15px] leading-relaxed">{{ $d['commitment_body'] }}</p>
                    @endif
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-px bg-line border border-line">
                    @foreach ($commitment as $it)
                        <article class="bg-white p-7 md:p-9 flex flex-col gap-3">
                            <div class="font-mono text-[11px] tracking-[0.18em] text-toco-red font-bold">{{ $it['num'] ?? '' }}</div>
                            <h3 class="font-extrabold text-ink text-[18px] leading-tight">{{ $it['t'] ?? '' }}</h3>
                            <div class="text-[13.5px] leading-[1.6] text-ink-soft prose prose-sm max-w-none">{!! $it['b'] ?? '' !!}</div>
                        </article>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    {{-- ============ Brands ============ --}}
    @if ($brands->isNotEmpty())
        <section class="bg-white">
            <div class="max-w-[1440px] mx-auto px-6 py-14 md:py-20">
                <div class="mb-8 max-w-3xl">
                    @if (! empty($d['brands_eyebrow']))
                        <p class="font-mono text-[11px] uppercase tracking-[0.18em] text-toco-red font-bold relative pl-4 before:content-[''] before:absolute before:left-0 before:top-1/2 before:-translate-y-1/2 before:w-2 before:h-0.5 before:bg-toco-red">{{ $d['brands_eyebrow'] }}</p>
                    @endif
                    <h2 class="font-extrabold text-toco-navy tracking-tight leading-[1.1] mt-3 text-[clamp(26px,3vw,38px)]">{{ $d['brands_headline'] ?? '' }}</h2>
                    @if (! empty($d['brands_body']))
                        <p class="text-ink-soft mt-3 text-[15px] leading-relaxed">{{ $d['brands_body'] }}</p>
                    @endif
                </div>
                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-2.5">
                    @foreach ($brands as $name)
                        <div class="group flex items-center gap-3.5 px-4 py-4 bg-white border border-line hover:border-ink hover:-translate-y-0.5 transition-all">
                            <div class="w-11 h-11 grid place-items-center bg-toco-navy text-white font-extrabold text-lg rounded-[2px] group-hover:bg-toco-red transition-colors">
                                {{ mb_strtoupper(mb_substr($name, 0, 1)) }}
                            </div>
                            <div class="font-bold text-ink text-[15px] tracking-tight">{{ $name }}</div>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    {{-- ============ Categories (navy band) ============ --}}
    @if ($categories->isNotEmpty())
        <section class="relative bg-toco-navy text-white overflow-hidden">
            <div class="pointer-events-none absolute -top-[10%] -right-10 w-44 h-[120%]"
                 style="background: linear-gradient(-115deg, transparent 0 38%, #E30613 38% 46%, transparent 46% 54%, #1A1A1A 54% 60%, transparent 60% 70%, #E30613 70% 74%, transparent 74%);"
                 aria-hidden="true"></div>
            <div class="relative max-w-[1440px] mx-auto px-6 py-12 md:py-20">
                <div class="grid grid-cols-1 lg:grid-cols-[1fr_1.4fr] gap-10 lg:gap-14 items-center">
                    <div>
                        @if (! empty($d['categories_eyebrow']))
                            <p class="font-mono text-[11px] uppercase tracking-[0.18em] font-bold text-[#FF4D58] relative pl-4 before:content-[''] before:absolute before:left-0 before:top-1/2 before:-translate-y-1/2 before:w-2 before:h-0.5 before:bg-[#FF4D58]">{{ $d['categories_eyebrow'] }}</p>
                        @endif
                        <h2 class="font-extrabold text-white tracking-tight leading-[1.1] mt-3 text-[clamp(26px,3.2vw,38px)]">{{ $d['categories_headline'] ?? '' }}</h2>
                    </div>
                    <ul class="grid grid-cols-1 sm:grid-cols-2 gap-x-8 gap-y-0">
                        @foreach ($categories as $c)
                            <li class="flex items-center gap-3 py-3.5 border-b border-white/10 text-[15px] font-semibold">
                                <span class="w-6 h-6 rounded-full bg-toco-red text-white grid place-items-center text-[12px] font-extrabold shrink-0">✓</span>
                                {{ $c }}
                            </li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </section>
    @endif

    {{-- ============ Gallery ============ --}}
    @if ($gallery->isNotEmpty())
        <section class="bg-toco-silver-2">
            <div class="max-w-[1440px] mx-auto px-6 py-14 md:py-20">
                <div class="mb-8 max-w-3xl">
                    @if (! empty($d['gallery_eyebrow']))
                        <p class="font-mono text-[11px] uppercase tracking-[0.18em] text-toco-red font-bold relative pl-4 before:content-[''] before:absolute before:left-0 before:top-1/2 before:-translate-y-1/2 before:w-2 before:h-0.5 before:bg-toco-red">{{ $d['gallery_eyebrow'] }}</p>
                    @endif
                    <h2 class="font-extrabold text-toco-navy tracking-tight leading-[1.1] mt-3 text-[clamp(26px,3vw,38px)]">{{ $d['gallery_headline'] ?? '' }}</h2>
                    @if (! empty($d['gallery_body']))
                        <p class="text-ink-soft mt-3 text-[15px] leading-relaxed">{{ $d['gallery_body'] }}</p>
                    @endif
                </div>
                <div class="grid grid-cols-1 lg:grid-cols-[2fr_1fr] gap-4">
                    @foreach ($gallery as $i => $g)
                        <figure class="bg-white border border-line overflow-hidden m-0">
                            <img src="{{ $img($g['image'] ?? '') }}" alt="{{ $g['caption'] ?? '' }}"
                                 class="block w-full h-full object-cover {{ $i === 0 ? 'aspect-[16/10]' : 'aspect-[4/3]' }}"
                                 loading="lazy">
                            @if (! empty($g['caption']))
                                <figcaption class="px-5 py-3.5 text-[12.5px] text-ink-soft border-t border-line">{{ $g['caption'] }}</figcaption>
                            @endif
                        </figure>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    {{-- ============ Company details card ============ --}}
    @if ($info->isNotEmpty())
        <section class="bg-white">
            <div class="max-w-[1440px] mx-auto px-6 py-14 md:py-20">
                <div class="bg-white border border-line max-w-[920px] mx-auto">
                    <div class="px-8 md:px-9 pt-8">
                        @if (! empty($d['info_eyebrow']))
                            <p class="font-mono text-[11px] uppercase tracking-[0.18em] text-toco-red font-bold relative pl-4 before:content-[''] before:absolute before:left-0 before:top-1/2 before:-translate-y-1/2 before:w-2 before:h-0.5 before:bg-toco-red">{{ $d['info_eyebrow'] }}</p>
                        @endif
                        <h2 class="font-extrabold text-ink tracking-tight leading-[1.1] mt-2.5 mb-7 text-[clamp(22px,2.4vw,30px)]">{{ $d['info_headline'] ?? '' }}</h2>
                    </div>
                    <table class="w-full border-collapse">
                        <tbody>
                            @foreach ($info as $row)
                                <tr class="border-t border-line first:border-t-0">
                                    <th scope="row" class="px-6 md:px-9 py-4 align-top text-left font-mono text-[11px] uppercase tracking-[0.12em] font-semibold text-ink-soft bg-toco-silver-2 w-[38%]">{{ $row['k'] ?? '' }}</th>
                                    <td class="px-6 md:px-9 py-4 align-top text-[14px] text-ink font-semibold">{{ $row['v'] ?? '' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    @endif

    {{-- ============ Branches ============ --}}
    @if ($branches->isNotEmpty())
        <section class="bg-toco-silver-2">
            <div class="max-w-[1440px] mx-auto px-6 py-14 md:py-20">
                <div class="mb-8 max-w-3xl">
                    @if (! empty($d['branches_eyebrow']))
                        <p class="font-mono text-[11px] uppercase tracking-[0.18em] text-toco-red font-bold relative pl-4 before:content-[''] before:absolute before:left-0 before:top-1/2 before:-translate-y-1/2 before:w-2 before:h-0.5 before:bg-toco-red">{{ $d['branches_eyebrow'] }}</p>
                    @endif
                    <h2 class="font-extrabold text-toco-navy tracking-tight leading-[1.1] mt-3 text-[clamp(26px,3vw,38px)]">{{ $d['branches_headline'] ?? 'Branches' }}</h2>
                    @if (! empty($d['branches_body']))
                        <p class="text-ink-soft mt-3 text-[15px] leading-relaxed">{{ $d['branches_body'] }}</p>
                    @endif
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    @foreach ($branches as $b)
                        @php
                            $cc = strtolower(trim((string) ($b['country_code'] ?? '')));
                            $flagSrc = $cc !== '' ? '/legacy/uploads/2023/11/'.$cc.'.svg' : null;
                            $rows = array_filter([
                                'Address' => $b['address'] ?? null,
                                'Phone' => $b['phone'] ?? null,
                                'Email' => $b['email'] ?? null,
                                'Company registration no.' => $b['registration_no'] ?? null,
                            ], fn ($v) => $v !== null && trim((string) $v) !== '');
                        @endphp
                        <article class="bg-white border border-line p-6 md:p-7 flex flex-col">
                            <div class="flex items-center gap-3">
                                @if ($flagSrc)
                                    <img src="{{ $flagSrc }}" alt="" width="28" height="20" class="rounded-[2px] border border-line shrink-0" loading="lazy">
                                @endif
                                <h3 class="font-extrabold text-toco-navy tracking-tight text-[18px] leading-tight">{{ $b['name'] }}</h3>
                            </div>
                            @if (! empty($rows))
                                <dl class="mt-4 border-t border-line">
                                    @foreach ($rows as $label => $value)
                                        <div class="grid grid-cols-[140px_minmax(0,1fr)] gap-3 py-3 border-b border-line last:border-b-0">
                                            <dt class="font-mono text-[11px] uppercase tracking-[0.12em] font-semibold text-ink-soft">{{ $label }}</dt>
                                            <dd class="text-[13.5px] font-semibold text-ink break-words">
                                                @if ($label === 'Email')
                                                    <a href="mailto:{{ $value }}" class="hover:text-toco-red">{{ $value }}</a>
                                                @elseif ($label === 'Phone')
                                                    <a href="tel:{{ preg_replace('/[^\d+]/', '', $value) }}" class="hover:text-toco-red">{{ $value }}</a>
                                                @else
                                                    {{ $value }}
                                                @endif
                                            </dd>
                                        </div>
                                    @endforeach
                                </dl>
                            @else
                                <p class="mt-4 text-[12.5px] text-ink-soft italic">Contact details coming soon.</p>
                            @endif
                        </article>
                    @endforeach
                </div>
            </div>
        </section>
    @endif
</x-layouts.cms>
