<?php

namespace App\Console\Commands;

use App\Models\Demand;
use Illuminate\Console\Command;

class ExpireDemands extends Command
{
    protected $signature   = 'demands:expire';
    protected $description = 'Süresi dolan talepleri otomatik olarak kapatır';

    public function handle(): void
    {
        $count = Demand::where('status', 'active')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->update(['status' => 'expired']);

        $this->info("$count talep süresi doldu ve kapatıldı.");
    }
}
