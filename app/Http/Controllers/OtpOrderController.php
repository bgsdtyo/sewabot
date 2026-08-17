<?php

namespace App\Http\Controllers;

use App\Models\BotMember;
use App\Models\OtpOrder;
use App\Models\OtpService;
use App\Models\TelegramBot;
use App\Services\OtpOrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class OtpOrderController extends Controller
{
    public function index(Request $request): View
    {
        $user = auth()->user();
        $bots = $user->telegramBots()->get();
        $botIds = $bots->pluck('id')->all();

        // Base query restricted to user's bots
        $query = OtpOrder::query()
            ->whereIn('telegram_bot_id', $botIds)
            ->with(['telegramBot', 'botMember', 'otpService']);

        // Filter by specific Bot
        if ($request->filled('bot_id')) {
            $query->where('telegram_bot_id', $request->integer('bot_id'));
        }

        // Filter by Bot Member / User
        if ($request->filled('member_id')) {
            $query->where('bot_member_id', $request->integer('member_id'));
        }

        // Filter by Status
        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        // Filter by OTP Service
        if ($request->filled('service_id')) {
            $query->where('otp_service_id', $request->integer('service_id'));
        }

        // Filter by Date range
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        // Global Search
        if ($request->filled('search')) {
            $search = trim((string) $request->search);
            $query->where(function ($q) use ($search) {
                $q->where('phone_number', 'like', "%{$search}%")
                    ->orWhere('otp_code', 'like', "%{$search}%")
                    ->orWhere('provider_order_id', 'like', "%{$search}%")
                    ->orWhere('full_text', 'like', "%{$search}%")
                    ->orWhereHas('botMember', function ($mq) use ($search) {
                        $mq->where('telegram_username', 'like', "%{$search}%")
                            ->orWhere('telegram_name', 'like', "%{$search}%")
                            ->orWhere('telegram_chat_id', 'like', "%{$search}%");
                    });
            });
        }

        // Summary metrics
        $statsBase = OtpOrder::query()->whereIn('telegram_bot_id', $botIds);
        if ($request->filled('bot_id')) {
            $statsBase->where('telegram_bot_id', $request->integer('bot_id'));
        }
        if ($request->filled('member_id')) {
            $statsBase->where('bot_member_id', $request->integer('member_id'));
        }

        $totalOrders = (clone $statsBase)->count();
        $completedCount = (clone $statsBase)->where('status', 'completed')->count();
        $pendingCount = (clone $statsBase)->where('status', 'pending')->count();
        $cancelledCount = (clone $statsBase)->whereIn('status', ['cancelled', 'expired'])->count();
        $totalRevenue = (clone $statsBase)->where('status', 'completed')->sum('sell_price');
        $totalCost = (clone $statsBase)->where('status', 'completed')->sum('provider_price');
        $totalProfit = max(0, $totalRevenue - $totalCost);

        // Paginate results
        $orders = $query->latest('id')->paginate(15)->withQueryString();

        // Get members for filter dropdown and create modal
        $members = BotMember::query()
            ->whereIn('telegram_bot_id', $botIds)
            ->orderBy('telegram_name')
            ->get();

        // Get active services
        $services = OtpService::sellable()->orderBy('name')->get();

        return view('otp-orders.index', compact(
            'orders',
            'bots',
            'members',
            'services',
            'totalOrders',
            'completedCount',
            'pendingCount',
            'cancelledCount',
            'totalRevenue',
            'totalProfit'
        ));
    }

    public function store(Request $request): RedirectResponse
    {
        $user = auth()->user();
        $botIds = $user->telegramBots()->pluck('id')->all();

        $data = $request->validate([
            'telegram_bot_id' => ['required', 'integer', 'in:'.implode(',', $botIds)],
            'bot_member_id' => ['required', 'integer'],
            'otp_service_id' => ['required', 'integer', 'exists:otp_services,id'],
            'phone_number' => ['required', 'string', 'max:30'],
            'otp_code' => ['nullable', 'string', 'max:20'],
            'sell_price' => ['required', 'integer', 'min:0'],
            'provider_price' => ['nullable', 'integer', 'min:0'],
            'status' => ['required', 'in:pending,completed,cancelled,expired'],
            'wallet_status' => ['required', 'in:none,held,charged,refunded'],
            'full_text' => ['nullable', 'string', 'max:1000'],
        ]);

        $member = BotMember::where('id', $data['bot_member_id'])
            ->where('telegram_bot_id', $data['telegram_bot_id'])
            ->firstOrFail();

        $service = OtpService::findOrFail($data['otp_service_id']);

        $order = OtpOrder::create([
            'telegram_bot_id' => $data['telegram_bot_id'],
            'bot_member_id' => $member->id,
            'otp_service_id' => $service->id,
            'idempotency_key' => (string) Str::uuid(),
            'phone_number' => $data['phone_number'],
            'otp_code' => $data['otp_code'] ?: null,
            'sell_price' => $data['sell_price'],
            'provider_price' => $data['provider_price'] ?? $service->provider_price,
            'status' => $data['status'],
            'wallet_status' => $data['wallet_status'],
            'full_text' => $data['full_text'] ?: null,
            'completed_at' => $data['status'] === 'completed' ? now() : null,
            'cancelled_at' => in_array($data['status'], ['cancelled', 'expired'], true) ? now() : null,
        ]);

        return redirect()->route('otp-orders.index')
            ->with('success', 'Riwayat OTP #'.$order->id.' berhasil ditambahkan.');
    }

    public function update(Request $request, OtpOrder $otpOrder): RedirectResponse
    {
        $this->authorizeOrderOwner($otpOrder);

        $data = $request->validate([
            'phone_number' => ['required', 'string', 'max:30'],
            'otp_code' => ['nullable', 'string', 'max:20'],
            'sell_price' => ['required', 'integer', 'min:0'],
            'provider_price' => ['nullable', 'integer', 'min:0'],
            'status' => ['required', 'in:pending,completed,cancelled,expired'],
            'wallet_status' => ['required', 'in:none,held,charged,refunded'],
            'full_text' => ['nullable', 'string', 'max:1000'],
        ]);

        $updates = [
            'phone_number' => $data['phone_number'],
            'otp_code' => $data['otp_code'] ?: null,
            'sell_price' => $data['sell_price'],
            'provider_price' => $data['provider_price'] ?? $otpOrder->provider_price,
            'status' => $data['status'],
            'wallet_status' => $data['wallet_status'],
            'full_text' => $data['full_text'] ?: null,
        ];

        if ($data['status'] === 'completed' && ! $otpOrder->completed_at) {
            $updates['completed_at'] = now();
        } elseif (in_array($data['status'], ['cancelled', 'expired'], true) && ! $otpOrder->cancelled_at) {
            $updates['cancelled_at'] = now();
        }

        $otpOrder->update($updates);

        return redirect()->route('otp-orders.index')
            ->with('success', 'Data Riwayat OTP #'.$otpOrder->id.' berhasil diperbarui.');
    }

    public function destroy(OtpOrder $otpOrder): RedirectResponse
    {
        $this->authorizeOrderOwner($otpOrder);

        $id = $otpOrder->id;
        $otpOrder->delete();

        return redirect()->route('otp-orders.index')
            ->with('success', 'Riwayat OTP #'.$id.' berhasil dihapus.');
    }

    public function refreshStatus(OtpOrder $otpOrder, OtpOrderService $otpService): RedirectResponse
    {
        $this->authorizeOrderOwner($otpOrder);

        if (! $otpOrder->provider_order_id) {
            return back()->withErrors(['error' => 'Order ini tidak memiliki ID Provider untuk dicek statusnya.']);
        }

        try {
            $refreshed = $otpService->refreshOrder($otpOrder);
            $otpStr = filled($refreshed->otp_code) ? $refreshed->otp_code : 'Belum ada';

            return back()->with('success', "Status diperbarui: [Status: {$refreshed->status}, OTP: {$otpStr}]");
        } catch (\Throwable $err) {
            return back()->withErrors(['error' => 'Gagal cek status ke provider: '.$err->getMessage()]);
        }
    }

    public function cancel(OtpOrder $otpOrder, OtpOrderService $otpService): RedirectResponse
    {
        $this->authorizeOrderOwner($otpOrder);

        if ($otpOrder->status !== 'pending') {
            return back()->withErrors(['error' => 'Hanya order berstatus Pending yang dapat dibatalkan.']);
        }

        try {
            $otpService->cancelOrder($otpOrder);

            return back()->with('success', 'Order #'.$otpOrder->id.' berhasil dibatalkan dan saldo ditahan telah di-refund.');
        } catch (\Throwable $err) {
            return back()->withErrors(['error' => 'Gagal membatalkan order: '.$err->getMessage()]);
        }
    }

    protected function authorizeOrderOwner(OtpOrder $order): void
    {
        $user = auth()->user();
        $botIds = $user->telegramBots()->pluck('id')->all();

        abort_unless(
            in_array($order->telegram_bot_id, $botIds, true) || $user->is_admin,
            403,
            'Anda tidak memiliki akses ke data pesanan ini.'
        );
    }
}