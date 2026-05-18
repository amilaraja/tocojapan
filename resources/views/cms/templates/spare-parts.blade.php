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
        </div>
    </section>

    <section class="max-w-[1100px] mx-auto px-6 py-10">
        @if (! empty($d['body']))
            <div class="prose max-w-none mb-8">{!! $d['body'] !!}</div>
        @endif

        <div id="spareparts-form" class="bg-white border border-line rounded-sm p-6 md:p-8">
            <p class="font-mono text-[10px] uppercase tracking-widest text-toco-red font-bold">Spare-part request</p>
            <h2 class="text-xl font-extrabold text-toco-navy mt-1">Tell us what you need</h2>
            <p class="text-sm text-ink-soft mt-2">Fill in your vehicle details and the parts you need. We check availability and reply with a quotation including shipping. <span class="text-toco-red font-semibold">Red-marked fields are required.</span></p>

            @if (session('spareparts_success'))
                <div class="mt-5 bg-green-50 border border-green-200 text-green-800 text-sm rounded-sm px-4 py-3">
                    {{ session('spareparts_success') }}
                </div>
            @endif
            @if ($errors->any())
                <div class="mt-5 bg-red-50 border border-red-200 text-red-700 text-sm rounded-sm px-4 py-3">
                    Please correct the highlighted fields and try again.
                </div>
            @endif

            <form method="POST" action="{{ route('spareparts.submit') }}#spareparts-form" enctype="multipart/form-data" class="mt-5">
                @csrf

                <p class="font-mono text-[10px] uppercase tracking-widest text-ink-soft mb-2 mt-2">Contact info</p>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label for="sp-name" class="block text-xs font-semibold text-toco-navy mb-1">Name <span class="text-toco-red">*</span></label>
                        <input id="sp-name" name="name" type="text" required value="{{ old('name') }}" class="w-full text-sm @error('name') border-red-400 @enderror">
                        @error('name')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="sp-email" class="block text-xs font-semibold text-toco-navy mb-1">Email <span class="text-toco-red">*</span></label>
                        <input id="sp-email" name="email" type="email" required value="{{ old('email') }}" class="w-full text-sm @error('email') border-red-400 @enderror">
                        @error('email')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="sp-country" class="block text-xs font-semibold text-toco-navy mb-1">Country <span class="text-toco-red">*</span></label>
                        <select id="sp-country" name="country" required class="w-full text-sm">
                            <option value="">Select country</option>
                            @foreach ($countries as $c)
                                <option value="{{ $c }}" @selected(old('country') === $c)>{{ $c }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="sp-phone" class="block text-xs font-semibold text-toco-navy mb-1">Phone / WhatsApp <span class="text-toco-red">*</span></label>
                        <input id="sp-phone" name="phone" type="tel" required value="{{ old('phone') }}" class="w-full text-sm @error('phone') border-red-400 @enderror">
                        @error('phone')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div class="md:col-span-2">
                        <label for="sp-address" class="block text-xs font-semibold text-toco-navy mb-1">Address <span class="text-toco-red">*</span></label>
                        <input id="sp-address" name="address" type="text" required value="{{ old('address') }}" class="w-full text-sm">
                    </div>
                </div>

                <p class="font-mono text-[10px] uppercase tracking-widest text-ink-soft mb-2 mt-6">Vehicle details</p>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label for="sp-model" class="block text-xs font-semibold text-toco-navy mb-1">Model name <span class="text-toco-red">*</span></label>
                        <input id="sp-model" name="model_name" type="text" required value="{{ old('model_name') }}" class="w-full text-sm">
                    </div>
                    <div>
                        <label for="sp-chassis" class="block text-xs font-semibold text-toco-navy mb-1">Chassis no. <span class="text-toco-red">*</span></label>
                        <input id="sp-chassis" name="chassis_no" type="text" required value="{{ old('chassis_no') }}" class="w-full text-sm">
                    </div>
                    <div>
                        <label for="sp-year" class="block text-xs font-semibold text-toco-navy mb-1">Year <span class="text-toco-red">*</span></label>
                        <input id="sp-year" name="year" type="text" required value="{{ old('year') }}" class="w-full text-sm">
                    </div>
                    <div>
                        <label for="sp-engine" class="block text-xs font-semibold text-toco-navy mb-1">Engine model <span class="text-toco-red">*</span></label>
                        <input id="sp-engine" name="engine_model" type="text" required value="{{ old('engine_model') }}" class="w-full text-sm">
                    </div>
                </div>

                <p class="font-mono text-[10px] uppercase tracking-widest text-ink-soft mb-2 mt-6">Parts required</p>
                <div class="grid grid-cols-1 gap-4">
                    <div>
                        <span class="block text-xs font-semibold text-toco-navy mb-1.5">Condition <span class="text-toco-red">*</span></span>
                        <div class="flex gap-5">
                            @foreach (['New', 'Used'] as $opt)
                                <label class="inline-flex items-center gap-2 text-sm">
                                    <input type="radio" name="condition" value="{{ $opt }}" required @checked(old('condition') === $opt)>
                                    {{ $opt }}
                                </label>
                            @endforeach
                        </div>
                    </div>
                    <div>
                        <label for="sp-desc" class="block text-xs font-semibold text-toco-navy mb-1">Parts needed <span class="text-toco-red">*</span></label>
                        <textarea id="sp-desc" name="parts_description" rows="4" required placeholder="Description, quantity, part numbers, etc." class="w-full text-sm @error('parts_description') border-red-400 @enderror">{{ old('parts_description') }}</textarea>
                        @error('parts_description')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="sp-files" class="block text-xs font-semibold text-toco-navy mb-1">Reference photos <span class="text-ink-soft font-normal">(optional, up to 2 files · 5 MB each)</span></label>
                        <input id="sp-files" name="attachments[]" type="file" accept=".jpg,.jpeg,.png,.webp,.pdf" multiple class="w-full text-sm @error('attachments.*') border-red-400 @enderror">
                        @error('attachments.*')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <span class="block text-xs font-semibold text-toco-navy mb-1.5">Preferred shipping <span class="text-toco-red">*</span></span>
                        <div class="flex flex-wrap gap-5">
                            @foreach (['Any', 'DHL', 'FedEx', 'EMS'] as $opt)
                                <label class="inline-flex items-center gap-2 text-sm">
                                    <input type="radio" name="shipping_method" value="{{ $opt }}" required @checked(old('shipping_method') === $opt)>
                                    {{ $opt }}
                                </label>
                            @endforeach
                        </div>
                    </div>
                </div>

                <div class="mt-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                    <x-turnstile class="!mt-0" />
                    <button type="submit" class="bg-toco-red hover:bg-toco-red-deep text-white font-bold uppercase tracking-widest text-xs px-6 py-3 rounded-sm shrink-0">
                        Submit request
                    </button>
                </div>
            </form>
        </div>
    </section>
</x-layouts.cms>
