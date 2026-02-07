<?php

namespace App\Http\Controllers;

use App\Models\OnlinePaymentOrder;
use Illuminate\Http\Request;

class OnlinePaymentOrderController extends Controller
{
    public function index(Request $request)
    {
        $perPage = $request->get('per_page', 25);
        $search = $request->get('search', '');
        $statusFilter = $request->get('status', '');

        $query = OnlinePaymentOrder::with('course')->orderByDesc('id');

        if ($statusFilter) {
            $query->where('status', $statusFilter);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('ident', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('payu_order_id', 'like', "%{$search}%");
            });
        }

        $orders = $query->paginate($perPage)->withQueryString();

        $statusCounts = [
            'pending' => OnlinePaymentOrder::where('status', 'pending')->count(),
            'created' => OnlinePaymentOrder::where('status', 'created')->count(),
            'paid' => OnlinePaymentOrder::where('status', 'paid')->count(),
            'cancelled' => OnlinePaymentOrder::where('status', 'cancelled')->count(),
        ];

        return view('online-payment-orders.index', compact('orders', 'search', 'statusFilter', 'perPage', 'statusCounts'));
    }

    public function show(int $id)
    {
        $order = OnlinePaymentOrder::with('course')->findOrFail($id);
        return view('online-payment-orders.show', compact('order'));
    }
}
