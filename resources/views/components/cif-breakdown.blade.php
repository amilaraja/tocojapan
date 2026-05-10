{{-- CIF breakdown panel — driven by Alpine `result` from the parent. --}}
<div class="bg-white border border-line rounded-sm">
    <div class="border-b-4 border-toco-red px-5 py-4">
        <p class="font-mono text-[10px] uppercase tracking-widest text-ink-soft">Estimated</p>
        <h3 class="font-extrabold text-toco-navy text-lg">Landed cost (CIF)</h3>
    </div>
    <div class="p-5">
        <div x-show="!result && !loading" class="text-sm text-ink-soft">
            Pick a destination port and a vehicle (or enter your own price &amp; m³) — your CIF estimate will appear here.
        </div>

        <div x-show="loading" x-cloak class="text-sm text-ink-soft">Calculating…</div>

        <template x-if="result">
            <div>
                <p class="font-mono text-[10px] uppercase tracking-widest text-ink-soft" x-text="'To ' + result.port.name + ', ' + result.port.country"></p>
                <p class="font-extrabold text-3xl text-toco-red mt-1 leading-none">
                    <span x-text="'$' + Number(result.cif_total).toLocaleString()"></span>
                    <span class="text-xs text-ink-soft font-bold ml-1" x-text="result.currency"></span>
                </p>

                <dl class="mt-5 space-y-2 text-sm">
                    <div class="flex justify-between"><dt class="text-ink-soft">FOB price</dt><dd class="font-semibold tabular-nums" x-text="'$' + Number(result.price_fob).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})"></dd></div>
                    <div class="flex justify-between border-t border-line pt-2">
                        <dt class="text-ink-soft">Freight (<span x-text="result.m3"></span> m³ × $<span x-text="result.rate_per_m3"></span>)</dt>
                        <dd class="font-semibold tabular-nums" x-text="'$' + Number(result.freight).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})"></dd>
                    </div>
                    <div class="flex justify-between border-t border-line pt-2">
                        <dt class="text-ink-soft">Insurance (<span x-text="(result.insurance_pct * 100).toFixed(2)"></span>%)</dt>
                        <dd class="font-semibold tabular-nums" x-text="'$' + Number(result.insurance).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})"></dd>
                    </div>
                    <div class="flex justify-between border-t-2 border-toco-navy pt-2 mt-2">
                        <dt class="font-bold text-toco-navy">CIF total</dt>
                        <dd class="font-extrabold text-toco-navy tabular-nums" x-text="'$' + Number(result.cif_total).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})"></dd>
                    </div>
                </dl>

                <p class="mt-4 text-[11px] text-ink-soft leading-snug">Estimate only. Final CIF varies with shipping schedule, currency rate, and customs at port. Land charges in destination country are not included.</p>

                <a href="{{ route('register') }}" class="block text-center mt-4 bg-toco-red hover:bg-toco-red-deep text-white font-bold uppercase tracking-widest text-xs px-4 py-2.5 rounded-sm">Convert to a real quote</a>
            </div>
        </template>
    </div>
</div>
