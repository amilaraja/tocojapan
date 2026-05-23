@php
    // Navy strip with INQUIRY + SUBSCRIBE — final block before the footer.
    $inquiryUrl = route('cms.page', 'contact');
@endphp
<section class="bg-toco-navy-deep text-white" x-data="subscribeWidget()">
    <div class="max-w-[1440px] mx-auto px-6 py-6 md:py-7 flex flex-col md:flex-row items-center justify-between gap-4">
        <p class="text-lg md:text-xl font-bold text-center md:text-left">
            Vehicle Inquiry Form Or Subscribe for New Vehicle Updates
        </p>
        <div class="flex flex-wrap items-center justify-center gap-3">
            <a href="{{ $inquiryUrl }}"
               class="bg-toco-red hover:bg-toco-red-deep text-white font-bold uppercase tracking-widest text-xs px-6 py-3 rounded-sm inline-flex items-center gap-2">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M4 4h16v12H5l-1 4z"/></svg>
                Inquiry
            </a>
            <button type="button" @click="open = true"
                    class="bg-toco-red hover:bg-toco-red-deep text-white font-bold uppercase tracking-widest text-xs px-6 py-3 rounded-sm inline-flex items-center gap-2">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M4 4h16v16H4z"/><path d="m4 6 8 7 8-7"/></svg>
                Subscribe
            </button>
        </div>
    </div>

    {{-- Subscribe modal --}}
    <div x-show="open" x-cloak @keydown.escape.window="open = false"
         class="fixed inset-0 z-50 grid place-items-center bg-black/60 px-4"
         x-transition.opacity>
        <div @click.outside="open = false"
             class="bg-white text-ink w-full max-w-md rounded-sm shadow-2xl overflow-hidden"
             x-transition>
            <div class="px-5 py-4 bg-toco-navy text-white flex items-center justify-between">
                <h3 class="font-extrabold tracking-tight text-lg">Get new stock alerts</h3>
                <button type="button" @click="open = false" aria-label="Close" class="text-white/70 hover:text-white">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M6 6l12 12M6 18 18 6"/></svg>
                </button>
            </div>
            <form @submit.prevent="submit()" class="p-5">
                <p class="text-sm text-ink-soft mb-3">We'll email you when fresh stock matching popular searches lands. Unsubscribe any time.</p>
                <label for="subscribe-email" class="font-mono text-[10px] uppercase tracking-widest text-ink-soft">Email address</label>
                <input id="subscribe-email" x-model="email" type="email" required placeholder="you@example.com"
                       class="w-full mt-1 text-sm" :disabled="loading">
                <p x-show="message" x-text="message" :class="ok ? 'text-emerald-700' : 'text-toco-red'" class="text-sm mt-2"></p>
                <button type="submit" :disabled="loading"
                        class="mt-4 w-full bg-toco-red hover:bg-toco-red-deep disabled:bg-toco-silver-2 disabled:text-ink-soft text-white font-bold uppercase tracking-widest text-xs px-5 py-3 rounded-sm">
                    <span x-show="!loading">Subscribe</span>
                    <span x-show="loading">Sending…</span>
                </button>
            </form>
        </div>
    </div>
</section>

@once
@push('scripts')
<script>
    function subscribeWidget() {
        return {
            open: false,
            email: '',
            loading: false,
            ok: false,
            message: '',
            async submit() {
                this.loading = true; this.message = '';
                try {
                    const r = await fetch('{{ route("subscribe.store") }}', {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        },
                        body: JSON.stringify({ email: this.email, source: 'homepage_cta' }),
                    });
                    const j = await r.json();
                    if (r.ok && j.ok) {
                        this.ok = true; this.message = j.message;
                        this.email = '';
                        setTimeout(() => { this.open = false; this.message = ''; this.ok = false; }, 2400);
                    } else {
                        this.ok = false;
                        this.message = (j.errors?.email?.[0]) || 'Please check that email and try again.';
                    }
                } catch (e) {
                    this.ok = false; this.message = 'Network error. Please try again.';
                } finally { this.loading = false; }
            },
        };
    }
</script>
@endpush
@endonce
