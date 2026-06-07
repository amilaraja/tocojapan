<x-layouts.cms :page="$page">
    @php($d = $page->data ?? [])

    <section class="bg-gradient-to-b from-toco-navy to-toco-navy-deep text-white">
        <div class="max-w-[1280px] mx-auto px-6 py-12 md:py-16">
            @if (! empty($d['kicker']))
                <p class="font-mono text-[11px] uppercase tracking-[0.2em] text-toco-red font-bold">{{ $d['kicker'] }}</p>
            @endif
            <h1 class="text-3xl md:text-5xl font-extrabold mt-2 leading-tight">
                {{ $d['headline'] ?? $page->title }}
            </h1>
        </div>
    </section>

    <section class="max-w-[1280px] mx-auto px-6 py-10">
        @if (! empty($d['body']))
            <div class="prose max-w-none mb-8">{!! $d['body'] !!}</div>
        @endif

        <div class="grid grid-cols-1 lg:grid-cols-[1fr_420px] xl:grid-cols-[1fr_460px] gap-8">
            {{-- LEFT: form (fields unchanged) --}}
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

                        {{-- File upload — drag & drop with a visible upload button.
                             The native input keeps the same name (attachments[]) so
                             the backend doesn't care that the UI is custom. --}}
                        <div>
                            <span class="block text-xs font-semibold text-toco-navy mb-1.5">Reference photos <span class="text-ink-soft font-normal">(optional, up to 2 files · 5 MB each · jpg/png/webp/pdf)</span></span>
                            <div
                                x-data="spareUpload()"
                                @dragover.prevent="dragging = true"
                                @dragleave.prevent="dragging = false"
                                @drop.prevent="onDrop($event)"
                                :class="dragging ? 'border-toco-red bg-toco-red/5' : 'border-line bg-toco-silver-2/40 hover:border-toco-red/60 hover:bg-white'"
                                class="relative border-2 border-dashed rounded-sm p-5 text-center transition cursor-pointer"
                                @click="$refs.fileInput.click()"
                                role="button" tabindex="0"
                                @keydown.enter.prevent="$refs.fileInput.click()"
                                @keydown.space.prevent="$refs.fileInput.click()">
                                <input x-ref="fileInput" id="sp-files" name="attachments[]" type="file"
                                    accept=".jpg,.jpeg,.png,.webp,.pdf" multiple
                                    class="sr-only" @change="onChange($event)">
                                <div class="flex flex-col items-center gap-2 pointer-events-none">
                                    <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="text-toco-navy/70">
                                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                                        <polyline points="17 8 12 3 7 8"/>
                                        <line x1="12" y1="3" x2="12" y2="15"/>
                                    </svg>
                                    <p class="text-sm text-toco-navy font-semibold">
                                        <span class="text-toco-red underline">Click to upload</span>
                                        <span class="text-ink-soft font-normal">or drag and drop</span>
                                    </p>
                                    <p class="text-[11px] text-ink-soft">JPG, PNG, WEBP, PDF — max 5 MB each, up to 2 files</p>
                                </div>
                            </div>
                            <ul x-data x-show="$refs.fileInput?.files?.length" class="mt-2 text-xs text-ink-soft space-y-1" x-cloak>
                                <template x-for="f in Array.from($refs.fileInput?.files || [])" :key="f.name">
                                    <li class="flex items-center justify-between gap-3 bg-toco-silver-2/60 border border-line rounded-sm px-3 py-1.5">
                                        <span class="truncate" x-text="f.name"></span>
                                        <span class="text-[10px] shrink-0" x-text="(f.size/1024).toFixed(0) + ' KB'"></span>
                                    </li>
                                </template>
                            </ul>
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

            {{-- RIGHT: hero image + "How to Order Vehicle Parts" steps --}}
            <aside class="space-y-6">
                <div class="bg-white border border-line rounded-sm overflow-hidden">
                    <img src="{{ asset('img/spareparts-hero.webp') }}"
                         alt="Japanese vehicle parts and accessories"
                         width="600" height="400"
                         class="w-full h-auto object-cover">
                </div>

                <div class="bg-white border border-line rounded-sm p-6">
                    <p class="font-mono text-[10px] uppercase tracking-widest text-toco-red font-bold">How it works</p>
                    <h2 class="text-xl font-extrabold text-toco-navy mt-1 mb-5">How to Order Vehicle Parts</h2>

                    <ol class="space-y-5">
                        @foreach ([
                            ['Request a Quotation', 'Send us your parts inquiry with the vehicle details (Chassis Number / VIN, model, year, or part information). We will check availability and provide a detailed quotation, including part prices, shipping costs, and estimated delivery time.'],
                            ['Confirm Your Order & Make Payment', 'Once you are satisfied with the quotation, you can complete your payment via Bank Transfer (T/T), Credit Card, or PayPal. Orders will be processed immediately after payment confirmation is received.'],
                            ['Order Processing & Shipping', 'After payment confirmation, we carefully prepare and package your order. The parts will then be shipped through a reliable shipping service, and tracking information will be provided whenever available.'],
                            ['Customer Support', 'If you have any questions before or after placing your order, we are always ready to assist you. We are committed to providing quality Japanese vehicle parts with fast, reliable service and worldwide shipping.'],
                        ] as $i => $step)
                            <li class="flex gap-3.5">
                                <span class="shrink-0 inline-flex items-center justify-center w-8 h-8 rounded-full bg-toco-red text-white font-extrabold text-sm">{{ $i + 1 }}</span>
                                <div class="min-w-0">
                                    <h3 class="font-bold text-toco-navy text-sm leading-snug">{{ $step[0] }}</h3>
                                    <p class="text-[13px] text-ink-soft leading-relaxed mt-1">{{ $step[1] }}</p>
                                </div>
                            </li>
                        @endforeach
                    </ol>
                </div>
            </aside>
        </div>
    </section>

    @push('scripts')
    <script>
        window.spareUpload = function () {
            return {
                dragging: false,
                onDrop(e) {
                    this.dragging = false;
                    if (!e.dataTransfer?.files?.length) return;
                    // Only the first 2 files are kept — backend caps at 2 anyway.
                    const dt = new DataTransfer();
                    Array.from(e.dataTransfer.files).slice(0, 2).forEach(f => dt.items.add(f));
                    this.$refs.fileInput.files = dt.files;
                },
                onChange() {
                    // No-op; the x-show on the file list re-reads input.files.
                },
            };
        };
    </script>
    @endpush
</x-layouts.cms>
