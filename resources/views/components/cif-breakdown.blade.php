{{-- CIF breakdown panel — driven by Alpine `result` from the parent. --}}
<div class="bg-white border border-line rounded-sm">
    <div class="border-b-4 border-toco-red px-5 py-4">
        <p class="font-mono text-[10px] uppercase tracking-widest text-ink-soft">Estimated</p>
        <h3 class="font-extrabold text-toco-navy text-lg">Landed cost (CIF)</h3>
    </div>
    <div class="p-5">
        <div x-show="!result && !loading" class="text-sm text-ink-soft">
            Pick a destination port and a vehicle — your CIF estimate will appear here.
        </div>

        <div x-show="loading" x-cloak class="text-sm text-ink-soft">Calculating…</div>

        <template x-if="result">
            <div>
                <p class="font-mono text-[10px] uppercase tracking-widest text-ink-soft" x-text="'To ' + result.port.name + ', ' + result.port.country"></p>
                <p class="font-extrabold text-4xl text-toco-red mt-1 leading-none">
                    <span x-text="'$' + Number(result.cif_total).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})"></span>
                    <span class="text-xs text-ink-soft font-bold ml-1" x-text="result.currency"></span>
                </p>
                <p class="font-mono text-[10px] uppercase tracking-widest text-ink-soft mt-3">CIF — cost, insurance &amp; freight included</p>

                <p class="mt-4 text-[11px] text-ink-soft leading-snug">Estimate only. Final CIF varies with shipping schedule, currency rate, and customs at port. Land charges in destination country are not included.</p>

                <a href="{{ route('register') }}" class="block text-center mt-4 bg-toco-red hover:bg-toco-red-deep text-white font-bold uppercase tracking-widest text-xs px-4 py-2.5 rounded-sm">Convert to a real quote</a>
            </div>
        </template>
    </div>
</div>
