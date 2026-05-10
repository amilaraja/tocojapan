<x-layouts.cms :page="$page">
    <section class="bg-gradient-to-b from-toco-navy to-toco-navy-deep text-white">
        <div class="max-w-[1100px] mx-auto px-6 py-12 md:py-16">
            @if (! empty($page->data['kicker']))
                <p class="font-mono text-[11px] uppercase tracking-[0.2em] text-toco-red font-bold">{{ $page->data['kicker'] }}</p>
            @endif
            <h1 class="text-3xl md:text-5xl font-extrabold mt-2 leading-tight">
                {{ $page->data['headline'] ?? $page->title }}
            </h1>
            @if (! empty($page->data['intro']))
                <div class="mt-4 text-white/80 max-w-2xl prose prose-invert">{!! $page->data['intro'] !!}</div>
            @endif
        </div>
    </section>

    <section class="max-w-[1100px] mx-auto px-6 py-10">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <div class="bg-white border border-line rounded-sm p-6 space-y-4">
                <p class="font-mono text-[10px] uppercase tracking-widest text-toco-red font-bold">Contact details</p>
                <dl class="text-sm space-y-3">
                    <div>
                        <dt class="font-mono text-[10px] uppercase tracking-widest text-ink-soft">Email</dt>
                        <dd class="font-semibold text-toco-navy"><a href="mailto:{{ $general->contact_email }}" class="hover:text-toco-red">{{ $general->contact_email }}</a></dd>
                    </div>
                    @if ($general->contact_phone)
                        <div>
                            <dt class="font-mono text-[10px] uppercase tracking-widest text-ink-soft">Phone</dt>
                            <dd class="font-semibold text-toco-navy">{{ $general->contact_phone }}</dd>
                        </div>
                    @endif
                    @if ($general->whatsapp_number)
                        <div>
                            <dt class="font-mono text-[10px] uppercase tracking-widest text-ink-soft">WhatsApp</dt>
                            <dd class="font-semibold text-toco-navy">{{ $general->whatsapp_number }}</dd>
                        </div>
                    @endif
                    @if (! empty($page->data['address_line_1']))
                        <div>
                            <dt class="font-mono text-[10px] uppercase tracking-widest text-ink-soft">Address</dt>
                            <dd class="font-semibold text-toco-navy">
                                {{ $page->data['address_line_1'] }}
                                @if (! empty($page->data['address_line_2']))
                                    <br>{{ $page->data['address_line_2'] }}
                                @endif
                            </dd>
                        </div>
                    @endif
                </dl>
            </div>

            @if (! empty($page->data['map_embed_url']))
                <div class="bg-white border border-line rounded-sm overflow-hidden">
                    <iframe src="{{ $page->data['map_embed_url'] }}" class="w-full aspect-square" style="border:0" loading="lazy" referrerpolicy="no-referrer-when-downgrade" title="Map"></iframe>
                </div>
            @else
                <div class="bg-toco-silver-2 border border-line rounded-sm flex items-center justify-center text-ink-soft">
                    <p class="font-mono text-[10px] uppercase tracking-widest">Map embed not configured</p>
                </div>
            @endif
        </div>
    </section>
</x-layouts.cms>
