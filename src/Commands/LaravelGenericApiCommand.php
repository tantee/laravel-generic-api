<?php

namespace TaNteE\LaravelGenericApi\Commands;

use Illuminate\Console\Command;

class LaravelGenericApiCommand extends Command
{
    public $signature = 'laravel-generic-api';

    public $description = 'My command';

    public function handle()
    {
        $this->comment('All done');
    }
}
