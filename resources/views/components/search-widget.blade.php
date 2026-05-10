@props([
    'makes' => collect(),
    'bodyTypes' => collect(),
    'currentYear' => (int) date('Y'),
])

<div
    x-data="{
        makeSlug: '',
        modelSlug: '',
        models: [],
        loadingModels: false,
        yearFrom: '',
        yearTo: '',
        bodyType: '',
        async loadModels(slug) {
            this.modelSlug = '';
            this.models = [];
            if (!slug) return;
            this.loadingModels = true;
            try {
                const r = await fetch(`/api/v1/makes/${slug}/models`, { headers: { Accept: 'application/json' } });
                const j = await r.json();
                this.models = j.data || [];
            } finally {
                this.loadingModels = false;
            }
        },
        submit() {
            const params = new URLSearchParams();
            if (this.makeSlug) params.set('make', this.makeSlug);
            if (this.modelSlug) params.set('vehicle_model', this.modelSlug);
            if (this.yearFrom) params.set('year_from', this.yearFrom);
            if (this.yearTo) params.set('year_to', this.yearTo);
            if (this.bodyType) params.set('body_type', this.bodyType);
            window.location = '/vehicles?' + params.toString();
        }
    }"
    {{ $attributes->merge(['class' => 'bg-white border border-line border-t-4 border-t-toco-red rounded-md p-5 shadow-sm']) }}
>
    <div class="flex items-center gap-2 mb-3">
        <span class="inline-flex items-center justify-center w-7 h-7 rounded bg-toco-red text-white text-xs font-bold">1</span>
        <h3 class="font-bold text-toco-navy">Find your vehicle</h3>
    </div>

    <form @submit.prevent="submit()" class="space-y-3 text-sm">
        <div>
            <label class="block font-medium mb-1">Make</label>
            <select x-model="makeSlug" @change="loadModels(makeSlug)" class="w-full border-line rounded">
                <option value="">— Select a make —</option>
                @foreach ($makes as $m)
                    <option value="{{ $m->slug }}">{{ $m->name }}</option>
                @endforeach
            </select>
        </div>

        <div x-show="makeSlug" x-transition>
            <label class="block font-medium mb-1">Model
                <span x-show="loadingModels" class="text-ink-soft text-xs">(loading…)</span>
            </label>
            <select x-model="modelSlug" class="w-full border-line rounded" :disabled="loadingModels">
                <option value="">— Any model —</option>
                <template x-for="m in models" :key="m.slug">
                    <option :value="m.slug" x-text="m.name"></option>
                </template>
            </select>
        </div>

        <div x-show="makeSlug" x-transition class="grid grid-cols-2 gap-2">
            <div>
                <label class="block font-medium mb-1">Year from</label>
                <select x-model="yearFrom" class="w-full border-line rounded">
                    <option value="">— Any —</option>
                    @for ($y = $currentYear; $y >= 1990; $y--)
                        <option value="{{ $y }}">{{ $y }}</option>
                    @endfor
                </select>
            </div>
            <div>
                <label class="block font-medium mb-1">Year to</label>
                <select x-model="yearTo" class="w-full border-line rounded">
                    <option value="">— Any —</option>
                    @for ($y = $currentYear; $y >= 1990; $y--)
                        <option value="{{ $y }}">{{ $y }}</option>
                    @endfor
                </select>
            </div>
        </div>

        <div x-show="makeSlug" x-transition>
            <label class="block font-medium mb-1">Body type</label>
            <select x-model="bodyType" class="w-full border-line rounded">
                <option value="">— Any —</option>
                @foreach ($bodyTypes as $b)
                    <option value="{{ $b->slug }}">{{ $b->name }}</option>
                @endforeach
            </select>
        </div>

        <button type="submit" class="w-full bg-toco-red hover:bg-toco-red-deep text-white font-semibold uppercase tracking-widest text-xs px-4 py-2 rounded">
            Search vehicles
        </button>
    </form>
</div>
