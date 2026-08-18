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

        $watcher->runWatchCycle((int) $order->id, continueChain: true);

        return response('ok', 200);
    }
}
