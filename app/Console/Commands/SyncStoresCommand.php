<?php

namespace App\Console\Commands;

use App\Jobs\SyncOrdersJob;
use App\Jobs\SyncProductsJob;
use App\Jobs\SyncShipmentsJob;
use App\Models\Store;
use Illuminate\Console\Command;

class SyncStoresCommand extends Command
{
    protected $signature = 'dfoms:sync {--store=} {--resource=all}';
    protected $description = 'Dispatch sync jobs for one or all active stores';

    public function handle(): int
    {
        $query = Store::query()->where('is_active', true);
        if ($id = $this->option('store')) {
            $query->where('id', $id);
        }

        $resource = $this->option('resource');

        $query->each(function (Store $store) use ($resource) {
            if (in_array($resource, ['all', 'orders'], true))    SyncOrdersJob::dispatch($store->id)->onQueue('sync');
            if (in_array($resource, ['all', 'products'], true))  SyncProductsJob::dispatch($store->id)->onQueue('sync');
            if (in_array($resource, ['all', 'shipments'], true)) SyncShipmentsJob::dispatch($store->id)->onQueue('sync');
            if (in_array($resource, ['all', 'returns'], true))   \App\Jobs\SyncReturnsJob::dispatch($store->id)->onQueue('sync');
            $this->info("Queued sync for store #{$store->id} ({$store->name})");
        });

        return self::SUCCESS;
    }
}
