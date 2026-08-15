<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Setting;
use Illuminate\View\View;

class LandingController extends Controller
{
    public function __invoke(): View
    {
        $product = Product::query()->where('is_active', true)->first();

        return view('landing.index', [
            'product' => $product,
            'merchant' => Setting::get('merchant_name', 'SewaBot'),
        ]);
    }
}
