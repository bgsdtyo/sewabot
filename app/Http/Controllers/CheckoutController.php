<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Product;
use App\Models\Setting;
use App\Models\Subscription;
use App\Services\OrderService;
use App\Services\QrisDinamisService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class CheckoutController extends Controller
{
    public function __construct(protected OrderService $orderService) {}

    public function selectBot(Request $request, Product $product): View|RedirectResponse
    {
        if (! $product->is_active) {
            abort(404);
        }

        if (auth()->user()->activeSubscription()) {
            return redirect()->route('dashboard')->with('info', 'Anda sudah memiliki subscription aktif.');
        }

        $step = (int) $request->query('step', 1);
        if (! in_array($step, [1, 2], true)) {
            $step = 1;
        }

        $periods = (int) $request->session()->get('checkout.periods.'.$product->id, old('periods', 1));
        $periods = max(1, min(4, $periods));

        if ($step === 2 && ! $request->session()->has('checkout.periods.'.$product->id) && ! old('bot_name')) {
            return redirect()->route('checkout.select-bot', ['product' => $product, 'step' => 1]);
        }

        if ($errors = $request->session()->get('errors')) {
            if ($errors->has('bot_name')) {
                $step = 2;
            }
        }

        $periodOptions = collect(range(1, 4))->map(function (int $p) use ($product) {
            return [
                'periods' => $p,
                'days' => $product->daysForPeriods($p),
                'amount' => $product->priceForPeriods($p, false),
                'label' => $product->daysForPeriods($p).' Hari',
                'breakdown' => $p === 1
                    ? 'Aktivasi + sewa 30 hari'
                    : 'Aktivasi + '.($p - 1).'x perpanjangan (+'.$product->formatRp($product->price_renewal).'/30 hari)',
            ];
        });

        return view('checkout.select-bot', [
            'product' => $product,
            'periodOptions' => $periodOptions,
            'step' => $step,
            'periods' => $periods,
            'selectedAmount' => $product->priceForPeriods($periods, false),
            'selectedDays' => $product->daysForPeriods($periods),
        ]);
    }

    public function saveDuration(Request $request, Product $product): RedirectResponse
    {
        $data = $request->validate([
            'periods' => ['required', 'integer', 'min:1', 'max:4'],
        ], [
            'periods.max' => 'Durasi maksimal 120 hari.',
        ]);

        $request->session()->put('checkout.periods.'.$product->id, (int) $data['periods']);

        return redirect()->route('checkout.select-bot', ['product' => $product, 'step' => 2]);
    }

    public function start(Request $request, Product $product): RedirectResponse
    {
        $periods = (int) $request->session()->get('checkout.periods.'.$product->id, $request->input('periods', 1));

        $data = $request->validate([
            'periods' => ['nullable', 'integer', 'min:1', 'max:4'],
            'bot_name' => ['required', 'string', 'min:3', 'max:60'],
        ], [
            'bot_name.required' => 'Nama bot wajib diisi.',
            'bot_name.min' => 'Nama bot minimal 3 karakter.',
        ]);

        $periods = max(1, min(4, (int) ($data['periods'] ?? $periods)));

        $order = $this->orderService->createNewSubscriptionOrder(auth()->user(), $product, [
            'periods' => $periods,
            'bot_name' => $data['bot_name'],
            'bot_notes' => null,
        ]);

        $request->session()->forget('checkout.periods.'.$product->id);

        return redirect()->route('checkout.show', $order);
    }

    public function show(Order $order): View|RedirectResponse
    {
        $this->authorizeOrder($order);

        if ($order->status === 'paid' || ($order->status === 'pending' && $order->payment_proof)) {
            return redirect()->route('checkout.success', $order);
        }

        $order->load(['product', 'telegramBot']);
        $payment = Setting::payment();
        $qrisDynamicReady = filled($payment['qris_static_payload'] ?? null);

        return view('checkout.show', compact('order', 'payment', 'qrisDynamicReady'));
    }

    public function qrisImage(Order $order, QrisDinamisService $qris): Response
    {
        $this->authorizeOrder($order);

        $static = (string) Setting::get('qris_static_payload', '');
        abort_unless($static !== '', 404);

        try {
            $png = $qris->png($static, (int) $order->amount, 480);
        } catch (\Throwable $e) {
            abort(422, 'Gagal generate QRIS dinamis.');
        }

        return response($png, 200, [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'private, max-age=300',
        ]);
    }

    public function success(Order $order): View
    {
        $this->authorizeOrder($order);
        $order->load(['product', 'telegramBot']);

        return view('checkout.success', compact('order'));
    }

    public function uploadProof(Request $request, Order $order): RedirectResponse
    {
        $this->authorizeOrder($order);

        $request->validate([
            'payment_proof' => ['required', 'image', 'max:5120'],
        ]);

        $this->orderService->uploadPaymentProof($order, $request->file('payment_proof'));

        return redirect()
            ->route('checkout.success', $order)
            ->with('success', 'Bukti pembayaran berhasil dikirim');
    }

    public function renewForm(Subscription $subscription): View|RedirectResponse
    {
        abort_unless((int) $subscription->user_id === (int) auth()->id() || auth()->user()?->is_admin, 403);

        $subscription->load('product');
        $product = $subscription->product;

        $periodOptions = collect(range(1, 4))->map(function (int $periods) use ($product) {
            return [
                'periods' => $periods,
                'days' => $product->daysForPeriods($periods),
                'amount' => $product->priceForPeriods($periods, true),
                'label' => $product->daysForPeriods($periods).' Hari',
            ];
        });

        return view('checkout.renew', compact('subscription', 'periodOptions'));
    }

    public function renew(Request $request, Subscription $subscription): RedirectResponse
    {
        abort_unless((int) $subscription->user_id === (int) auth()->id() || auth()->user()?->is_admin, 403);

        $data = $request->validate([
            'periods' => ['required', 'integer', 'min:1', 'max:4'],
        ], [
            'periods.max' => 'Durasi maksimal 120 hari.',
        ]);

        $order = $this->orderService->createRenewalOrder(
            auth()->user(),
            $subscription,
            (int) $data['periods']
        );

        return redirect()->route('checkout.show', $order);
    }

    protected function authorizeOrder(Order $order): void
    {
        $user = auth()->user();

        abort_unless(
            $user
            && (
                (int) $order->user_id === (int) $user->id
                || (bool) $user->is_admin
            ),
            403,
            'Anda tidak memiliki akses ke invoice ini.'
        );
    }
}
