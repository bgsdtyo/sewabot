<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class PaymentHistoryController extends Controller
{
    public function __invoke(): View
    {
        $orders = auth()->user()
            ->orders()
            ->with('product')
            ->latest()
            ->paginate(15);

        return view('payments.index', compact('orders'));
    }
}
