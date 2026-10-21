<?php

namespace AmjadIqbal\Laralink\Commands;

use AmjadIqbal\Laralink\Laralink;
use Illuminate\Console\Command;
use RuntimeException;

class ListCommand extends Command
{
    protected $signature = 'laralink:list
                            {--json : Output as JSON}
                            {--filter=all : Show only local, remote or all packages}';

    protected $description = 'List the project\'s packages and which ones are linked for local development';

    public function handle(Laralink $laralink): int
    {
        $filter = (string) $this->option('filter');

        if (! in_array($filter, ['all', 'local', 'remote'], true)) {
            $this->components->error('--filter must be local, remote or all.');

            return self::FAILURE;
        }

        try {
            $rows = $this->rows($laralink);
        } catch (RuntimeException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        if ($filter !== 'all') {
            $rows = array_values(array_filter($rows, fn ($row) => $row['local'] === ($filter === 'local')));
        }

        if ($this->option('json')) {
            $this->line((string) json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        if ($rows === []) {
            $this->components->info('No packages found. Link one with: php artisan laralink:dev');

            return self::SUCCESS;
        }

        $this->table(
            ['Package', 'Status', 'Constraint', 'Installed', 'Location'],
            array_map(fn ($row) => [
                $row['name'].($row['section'] === 'require-dev' ? ' <fg=gray>(dev)</>' : ''),
                $this->label($row['status']),
                $row['constraint'] ?? '-',
                $row['installed'] ?? '-',
                $row['location'],
            ], $rows)
        );

        $local = count(array_filter($rows, fn ($row) => $row['local']));
        $this->components->twoColumnDetail('Linked locally', (string) count(array_filter($rows, fn ($row) => $row['status'] === 'linked')));
        $this->components->twoColumnDetail('Other local copies', (string) ($local - count(array_filter($rows, fn ($row) => $row['status'] === 'linked'))));
        $this->components->twoColumnDetail('Remote', (string) (count($rows) - $local));

        return self::SUCCESS;
    }

    /**
     * @return array<int, array{name: string, status: string, local: bool, section: string|null, constraint: string|null, installed: string|null, location: string, source: string|null}>
     */
    private function rows(Laralink $laralink): array
    {
        $composer = $laralink->readComposerJson();
        $installed = $laralink->installedVersions();
        $state = $laralink->state();
        $rows = [];

        foreach (['require', 'require-dev'] as $section) {
            foreach ($composer[$section] ?? [] as $name => $constraint) {
                if ($name === 'php' || preg_match('/^(ext|lib)-/', $name) || isset($rows[$name])) {
                    continue;
                }

                $rows[$name] = $this->row($laralink, $name, $section, (string) $constraint, $installed[$name] ?? null, $state[$name] ?? []);
            }
        }

        foreach ($laralink->linkedPackages() as $name) {
            $rows[$name] ??= $this->row($laralink, $name, null, null, $installed[$name] ?? null, $state[$name] ?? []);
        }

        uasort($rows, fn ($a, $b) => [! $a['local'], $a['name']] <=> [! $b['local'], $b['name']]);

        return array_values($rows);
    }

    /**
     * @param  array<string, mixed>  $state
     * @return array{name: string, status: string, local: bool, section: string|null, constraint: string|null, installed: string|null, location: string, source: string|null}
     */
    private function row(Laralink $laralink, string $name, ?string $section, ?string $constraint, ?string $installed, array $state): array
    {
        $parts = explode('/', $name, 2);
        $local = count($parts) === 2 && $laralink->isLinked($parts[0], $parts[1]);

        if (! $local) {
            $status = 'remote';
        } elseif (! $laralink->hasPathRepository($parts[0], $parts[1])) {
            $status = 'unlinked';
        } else {
            $status = $section === null ? 'not-required' : 'linked';
        }

        $path = $local ? $laralink->localPath($parts[0], $parts[1]) : null;

        return [
            'name' => $name,
            'status' => $status,
            'local' => $local,
            'section' => $section,
            'constraint' => $constraint,
            'installed' => $installed,
            'location' => $local ? "./packages/{$name}" : "vendor/{$name}",
            'source' => $path !== null && is_link($path) ? (string) readlink($path) : ($state['source'] ?? null),
        ];
    }

    private function label(string $status): string
    {
        return match ($status) {
            'linked' => '<fg=green>Linked</>',
            'unlinked' => '<fg=yellow>Local files, not linked</>',
            'not-required' => '<fg=yellow>Linked, not required</>',
            default => 'Remote',
        };
    }
}
