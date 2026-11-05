<?php

declare(strict_types=1);

namespace PayamBridge\Laravel\Commands;

use Illuminate\Console\Command;
use PayamBridge\Laravel\ConnectionManager;

final class CapabilitiesCommand extends Command
{
    protected $signature = 'payam:capabilities {connection?}';

    protected $description = 'List implemented capabilities without making an API request';

    public function handle(ConnectionManager $manager): int
    {
        $connection = $manager->connection($this->argument('connection'));
        $this->line($connection->id()->key());
        foreach ($connection->capabilities()->values() as $capability) {
            $this->line($capability);
        }

        return self::SUCCESS;
    }
}
