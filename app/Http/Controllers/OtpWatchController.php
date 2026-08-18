<?php

namespace App\Http\Controllers;

use App\Models\OtpOrder;
use App\Services\OtpOrderWatcher;
use Illuminate\Http\Response;

class OtpWatchController extends Controller
{
    public function __invoke(OtpOrder $order, OtpOrderWatcher $watcher): Response
    {
        if ($order->status !== 'pending') {
            return response('done', 200);
        }

        if ($order->isPartOfBatch()) {
            $ids = $order->getBatchOrders()
                ->where('status', 'pending')
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->values()
                ->all();

            if ($ids === []) {
                return response('done', 200);
            }

            $watcher->runWatchBatchCycle($ids, continueChain: true);

            return response('ok', 200);
        }

        $watcher->runWatchCycle((int) $order->id, continueChain: true);

        return response('ok', 200);
    }
}
