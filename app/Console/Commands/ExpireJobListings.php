<?php

namespace App\Console\Commands;

use App\Models\JobListing;
use Illuminate\Console\Command;

class ExpireJobListings extends Command
{
    protected $signature = 'jobs:expire';

    protected $description = 'Transition approved job listings past their application deadline to expired.';

    public function handle(): int
    {
        $count = JobListing::query()
            ->where('status', 'approved')
            ->whereNotNull('application_deadline')
            ->whereDate('application_deadline', '<', now())
            ->update(['status' => 'expired']);

        $this->info("Expired {$count} job listing(s).");

        return self::SUCCESS;
    }
}