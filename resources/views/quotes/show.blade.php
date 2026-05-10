@php
    $title = $quote->reference.' — Toco Japan';
    $statusLabels = \App\Models\Quote::STATUSES;
@endphp

<x-layouts.site :title="$title">
    <div class="bg-toco-silver-2 border-b border-line">
        <div class="w-full px-6 2xl:px-8 py-3 text-[12px] font-mono uppercase tracking-widest text-ink-soft">
            <a href="{{ route('dashboard') }}" class="hover:text-toco-red">Dashboard</a>
            <span class="mx-1.5">/</span>
            <a href="{{ route('quotes.index') }}" class="hover:text-toco-red">My quotes</a>
            <span class="mx-1.5">/</span>
            <span class="text-ink">{{ $quote->reference }}</span>
        </div>
    </div>

    <section class="w-full px-6 2xl:px-8 py-8">
        @if (session('flash'))
            <div class="bg-toco-silver-2 border border-toco-navy/20 text-toco-navy px-4 py-2 rounded-sm text-sm mb-4 font-mono">{{ session('flash') }}</div>
        @endif

        <div class="grid grid-cols-1 lg:grid-cols-[2fr_1fr] gap-6">
            {{-- Main thread --}}
            <div class="space-y-4">
                <div class="bg-white border border-line rounded-sm">
                    <div class="border-b-4 border-toco-red px-5 py-4">
                        <p class="font-mono text-[10px] uppercase tracking-widest text-ink-soft">{{ $quote->reference }}</p>
                        <h1 class="text-xl font-extrabold text-toco-navy leading-tight mt-1">
                            {{ $quote->vehicle?->title ?? 'General enquiry' }}
                        </h1>
                        @if ($quote->vehicle)
                            <a href="{{ route('vehicles.show', $quote->vehicle->slug) }}" class="text-[12px] font-semibold text-toco-red hover:underline">View vehicle →</a>
                        @endif
                    </div>
                </div>

                {{-- Messages --}}
                <div class="space-y-3">
                    @forelse ($quote->messages as $msg)
                        @php
                            $mine = $msg->user_id === Auth::id();
                            $admin = $msg->user_id !== $quote->user_id;
                        @endphp
                        @if (! $msg->is_internal)
                            <div @class([
                                'bg-white border border-line rounded-sm p-4',
                                'border-l-4 border-l-toco-red' => $admin,
                            ])>
                                <p class="font-mono text-[10px] uppercase tracking-widest text-ink-soft">
                                    {{ $admin ? 'Toco Japan team' : ($mine ? 'You' : $msg->user->name) }} · {{ $msg->created_at->diffForHumans() }}
                                </p>
                                <div class="text-sm leading-relaxed mt-2 whitespace-pre-line">{{ $msg->body }}</div>
                            </div>
                        @endif
                    @empty
                        <div class="bg-white border border-line rounded-sm p-6 text-center text-ink-soft text-sm">
                            No messages yet. Submit a reply below to start the conversation.
                        </div>
                    @endforelse
                </div>

                {{-- Reply --}}
                <form method="POST" action="{{ route('quotes.reply', $quote) }}" class="bg-white border border-line rounded-sm p-4">
                    @csrf
                    <p class="font-mono text-[10px] uppercase tracking-widest text-toco-red font-bold mb-2">Reply</p>
                    <textarea name="body" rows="4" required maxlength="4000" placeholder="Write a reply…" class="w-full border-line rounded-sm text-sm">{{ old('body') }}</textarea>
                    @error('body')<p class="text-toco-red text-xs mt-1">{{ $message }}</p>@enderror
                    <div class="flex justify-end mt-2">
                        <button type="submit" class="bg-toco-red hover:bg-toco-red-deep text-white font-bold uppercase tracking-widest text-xs px-4 py-2.5 rounded-sm">Send reply</button>
                    </div>
                </form>
            </div>

            {{-- Aside --}}
            <aside class="space-y-4">
                <div class="bg-white border border-line rounded-sm p-4 text-sm">
                    <p class="font-mono text-[10px] uppercase tracking-widest text-toco-red font-bold">Status</p>
                    <p class="mt-2">
                        <span class="font-mono text-[10px] uppercase tracking-widest px-2 py-1 rounded-sm
                            @class([
                                'bg-toco-silver text-toco-navy' => in_array($quote->status, ['submitted', 'archived']),
                                'bg-yellow-100 text-yellow-900' => $quote->status === 'in_progress',
                                'bg-green-100 text-green-900' => $quote->status === 'quoted',
                                'bg-toco-red text-white' => $quote->status === 'accepted',
                                'bg-red-100 text-red-900' => $quote->status === 'declined',
                            ])">
                            {{ $statusLabels[$quote->status] ?? $quote->status }}
                        </span>
                    </p>

                    @if ($quote->price_quoted)
                        <div class="mt-4 pt-3 border-t border-line">
                            <p class="font-mono text-[10px] uppercase tracking-widest text-ink-soft">Quoted price</p>
                            <p class="font-extrabold text-2xl text-toco-red mt-0.5">${{ number_format((float) $quote->price_quoted) }} <span class="text-xs text-ink-soft font-bold">{{ $quote->currency }}</span></p>
                            @if ($quote->cif_total)
                                <p class="text-[12px] text-ink-soft mt-1">CIF total: ${{ number_format((float) $quote->cif_total) }}</p>
                            @endif
                            @if ($quote->valid_until)
                                <p class="text-[12px] text-ink-soft mt-1">Valid until {{ $quote->valid_until->toFormattedDateString() }}</p>
                            @endif
                        </div>
                    @endif
                </div>

                <div class="bg-white border border-line rounded-sm p-4 text-sm">
                    <p class="font-mono text-[10px] uppercase tracking-widest text-toco-red font-bold mb-2">Details</p>
                    <dl class="grid grid-cols-2 gap-y-1.5">
                        <dt class="text-ink-soft font-mono text-[10px] uppercase tracking-widest pt-1">Contact</dt>
                        <dd class="text-right font-semibold pt-1">{{ $quote->contact_name }}</dd>
                        <dt class="text-ink-soft font-mono text-[10px] uppercase tracking-widest pt-1">Email</dt>
                        <dd class="text-right font-semibold pt-1 truncate">{{ $quote->contact_email }}</dd>
                        @if ($quote->contact_phone)
                            <dt class="text-ink-soft font-mono text-[10px] uppercase tracking-widest pt-1">Phone</dt>
                            <dd class="text-right font-semibold pt-1">{{ $quote->contact_phone }}</dd>
                        @endif
                        <dt class="text-ink-soft font-mono text-[10px] uppercase tracking-widest pt-1">Destination</dt>
                        <dd class="text-right font-semibold pt-1">
                            {{ $quote->port?->name ?? '—' }}{{ $quote->country ? ', '.$quote->country->name : '' }}
                        </dd>
                        <dt class="text-ink-soft font-mono text-[10px] uppercase tracking-widest pt-1">Submitted</dt>
                        <dd class="text-right font-semibold pt-1">{{ $quote->created_at->toFormattedDateString() }}</dd>
                    </dl>
                </div>
            </aside>
        </div>
    </section>
</x-layouts.site>
