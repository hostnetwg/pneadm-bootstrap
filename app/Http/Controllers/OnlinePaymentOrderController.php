<?php

namespace App\Http\Controllers;

use App\Models\OnlinePaymentOrder;
use App\Models\WebhookLog;
use Illuminate\Http\Request;

class OnlinePaymentOrderController extends Controller
{
    public function index(Request $request)
    {
        $perPage = $request->get('per_page', 25);
        $search = $request->get('search', '');
        $statusFilter = $request->get('status', '');
        $courseId = $request->get('course_id', '');

        $query = OnlinePaymentOrder::with('course')->orderByDesc('id');

        if ($statusFilter) {
            $query->where('status', $statusFilter);
        }

        if ($courseId !== '' && $courseId !== null) {
            $query->where('course_id', $courseId);
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

        $statusOptions = [
            OnlinePaymentOrder::STATUS_PENDING => 'Oczekujące',
            OnlinePaymentOrder::STATUS_CREATED => 'Utworzone (przekierowanie)',
            OnlinePaymentOrder::STATUS_PAID => 'Opłacone',
            OnlinePaymentOrder::STATUS_CANCELLED => 'Anulowane',
            OnlinePaymentOrder::STATUS_FAILED => 'Nieudane',
        ];

        return view('online-payment-orders.index', compact('orders', 'search', 'statusFilter', 'courseId', 'perPage', 'statusCounts', 'statusOptions'));
    }

    public function show(int $id)
    {
        $order = OnlinePaymentOrder::with('course')->findOrFail($id);
        $webhookLogs = WebhookLog::where('online_payment_order_id', $id)
            ->orderByDesc('created_at')
            ->get();
        
        return view('online-payment-orders.show', compact('order', 'webhookLogs'));
    }
}
