<?php

namespace App\Http\Controllers;

use App\Http\Requests\QuoteStoreRequest;
use App\Models\Country;
use App\Models\Quote;
use App\Models\Vehicle;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class QuoteController extends Controller
{
    public function index(Request $request): View
    {
        $quotes = $request->user()->quotes()
            ->with(['vehicle', 'country', 'port'])
            ->latest()
            ->paginate(20);

        return view('quotes.index', ['quotes' => $quotes]);
    }

    public function show(Request $request, Quote $quote): View
    {
        $this->authorizeOwner($request, $quote);

        $quote->load(['vehicle', 'country', 'port', 'messages.user']);

        // Mark admin replies as read for this customer.
        $quote->messages()
            ->where('is_internal', false)
            ->whereNull('read_at')
            ->whereNot('user_id', $request->user()->id)
            ->update(['read_at' => now()]);

        return view('quotes.show', ['quote' => $quote]);
    }

    public function store(QuoteStoreRequest $request, string $slug): RedirectResponse
    {
        $vehicle = Vehicle::query()->published()->where('slug', $slug)->firstOrFail();
        $data = $request->validated();

        // Validate port belongs to country if both supplied.
        if (! empty($data['country_id']) && ! empty($data['port_id'])) {
            $belongs = Country::find($data['country_id'])
                ?->ports()->where('id', $data['port_id'])->exists();
            if (! $belongs) {
                return back()->withErrors(['port_id' => 'Selected port does not belong to that country.'])->withInput();
            }
        }

        $quote = $request->user()->quotes()->create([
            'vehicle_id' => $vehicle->id,
            'country_id' => $data['country_id'] ?? null,
            'port_id' => $data['port_id'] ?? null,
            'contact_name' => $data['contact_name'],
            'contact_email' => $data['contact_email'],
            'contact_phone' => $data['contact_phone'] ?? null,
            'message' => $data['message'] ?? null,
            'status' => 'submitted',
            'last_customer_reply_at' => now(),
        ]);

        if (! empty($data['message'])) {
            $quote->messages()->create([
                'user_id' => $request->user()->id,
                'body' => $data['message'],
                'is_internal' => false,
            ]);
        }

        return redirect()->route('quotes.show', $quote)
            ->with('flash', "Quote {$quote->reference} submitted. We'll be in touch shortly.");
    }

    public function reply(Request $request, Quote $quote): RedirectResponse
    {
        $this->authorizeOwner($request, $quote);

        $data = $request->validate([
            'body' => ['required', 'string', 'max:4000'],
        ]);

        $quote->messages()->create([
            'user_id' => $request->user()->id,
            'body' => $data['body'],
            'is_internal' => false,
        ]);

        $quote->update(['last_customer_reply_at' => now()]);

        return redirect()->route('quotes.show', $quote)->with('flash', 'Reply sent.');
    }

    private function authorizeOwner(Request $request, Quote $quote): void
    {
        if ($quote->user_id !== $request->user()->id) {
            throw new AuthorizationException('You do not have access to this quote.');
        }
    }
}
