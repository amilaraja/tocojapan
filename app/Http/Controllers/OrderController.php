<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Contracts\View\View;
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

        return view('orders.show', compact('order'));
    }
}
