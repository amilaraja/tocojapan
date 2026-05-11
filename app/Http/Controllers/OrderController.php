<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderMessage;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class OrderController extends Controller
{
    public function index(): View
    {
        $orders = Order::query()
            ->where('user_id', Auth::id())
            ->with(['vehicle.media'])
            ->latest()
            ->paginate(20);

        return view('orders.index', compact('orders'));
    }

    public function show(Order $order): View
    {
        if ($order->user_id !== Auth::id()) {
            abort(403);
        }
        $order->load(['vehicle.media', 'messages.user', 'messages.media']);

        // Mark any admin replies as read by the customer.
        OrderMessage::query()
            ->where('order_id', $order->id)
            ->where('from_admin', true)
            ->whereNull('read_by_customer_at')
            ->update(['read_by_customer_at' => now()]);

        return view('orders.show', compact('order'));
    }

    public function postMessage(Request $request, Order $order): RedirectResponse
    {
        if ($order->user_id !== Auth::id()) {
            abort(403);
        }

        $data = $request->validate([
            'body' => ['nullable', 'string', 'max:5000'],
            'attachments' => ['nullable', 'array', 'max:5'],
            'attachments.*' => ['file', 'max:8192'],
        ]);

        if (empty($data['body']) && empty($data['attachments'])) {
            return back()->withErrors(['body' => 'Add a message or an attachment.']);
        }

        $message = OrderMessage::create([
            'order_id' => $order->id,
            'user_id' => Auth::id(),
            'from_admin' => false,
            'body' => $data['body'] ?? '',
            'read_by_customer_at' => now(),
        ]);

        foreach ($request->file('attachments') ?? [] as $file) {
            $message->addMedia($file)->toMediaCollection('attachments');
        }

        return redirect()->route('orders.show', $order)->with('status', 'Message sent.');
    }
}
