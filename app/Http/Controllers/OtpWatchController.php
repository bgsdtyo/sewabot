<?php

namespace App\Http\Controllers;

use App\Services\OtpOrderWatcher;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class OtpWatchController extends Controller
{
    public function __invoke(Request $request, OtpOrderWatcher $watcher): Response
    {
        return $this->run($request, $watcher);
    }

    public function batch(Request $request, OtpOrderWatcher $watcher): Response
    {
        return $this->run($request, $watcher);
    }

    protected function run(Request $request, OtpOrderWatcher $watcher): Response
    {
        ignore_user_abort(true);
        @set_time_limit(180);

        $ids = collect(explode(',', (string) $request->query('ids', '')))
            ->map(fn ($id) => (int) trim($id))
            ->filter()
            ->values()
            ->all();

        $routeOrder = $request->route('order');
        if ($ids === [] && $routeOrder) {
            $ids = [(int) (is_object($routeOrder) ? $routeOrder->id : $routeOrder)];
        }

        if ($ids === []) {
            return response('empty', 200);
        }

        if (count($ids) === 1) {
            $watcher->runWatchCycle($ids[0]);
        } else {
            $watcher->runWatchBatchCycle($ids);
        }

        return response('ok', 200);
    }
}
