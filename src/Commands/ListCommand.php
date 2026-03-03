<?php

namespace Amjadiqbal\Laralink\Commands;

use Amjadiqbal\Laralink\Laralink;
use Illuminate\Console\Command;

class ListCommand extends Command
{
    protected $signature = 'laralink:list';

    protected $description = 'List all packages showing which are Linked (local) vs Remote (from Packagist)';

    public function handle(Laralink $laralink): int
    {
        $linked = $laralink->linkedPackages();
        $required = $laralink->requiredPackages();

        if (empty($linked) && empty($required)) {
            $this->info('No packages found.');
            return self::SUCCESS;
        }

        $rows = [];

        foreach ($linked as $name) {
            $version = $required[$name] ?? 'N/A';
            $rows[] = [$name, "<fg=green>Linked (local)</fg=green>", $version];
        }

        $linkedNames = array_flip($linked);

        foreach ($required as $name => $version) {
            if (isset($linkedNames[$name])) {
                continue;
            }

            if (str_starts_with($name, 'php') || str_starts_with($name, 'ext-')) {
                continue;
            }

            $rows[] = [$name, "<fg=yellow>Remote (Packagist)</fg=yellow>", $version];
        }

        if (empty($rows)) {
            $this->info('No packages found.');
            return self::SUCCESS;
        }

        $this->table(['Package', 'Status', 'Version'], $rows);

        $linkedCount = count($linked);
        $remoteCount = count($rows) - $linkedCount;

        $this->line('');
        $this->line("  <fg=green>Linked</>: {$linkedCount}   <fg=yellow>Remote</>: {$remoteCount}");

        return self::SUCCESS;
    }
}
