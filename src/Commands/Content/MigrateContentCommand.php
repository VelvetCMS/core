<?php

declare(strict_types=1);

namespace VelvetCMS\Commands\Content;

use VelvetCMS\Commands\Command;
use VelvetCMS\Contracts\ContentDriver;
use VelvetCMS\Core\Application;
use VelvetCMS\Database\Connection;
use VelvetCMS\Drivers\Content\DBDriver;
use VelvetCMS\Drivers\Content\FileDriver;
use VelvetCMS\Drivers\Content\HybridDriver;
use VelvetCMS\Services\ContentParser;

class MigrateContentCommand extends Command
{
    private const VALID_DRIVERS = ['file', 'db', 'hybrid'];

    public function __construct(
        private readonly Application $app
    ) {
    }

    public static function category(): string
    {
        return 'Content';
    }

    public function signature(): string
    {
        return 'content:migrate {target} [--from=] [--force] [--dry-run]';
    }

    public function description(): string
    {
        return 'Migrate content between drivers (file, hybrid, db)';
    }

    public function handle(): int
    {
        $target = $this->argument(0);
        $from = $this->option('from');
        $force = $this->option('force');
        $dryRun = $this->option('dry-run');

        if (!in_array($target, self::VALID_DRIVERS, true)) {
            $this->error("Invalid target driver: {$target}");
            $this->line('Valid drivers: ' . implode(', ', self::VALID_DRIVERS));
            return 1;
        }

        // Detect source driver if not specified
        if ($from === null) {
            $from = config('content.driver', 'file');
            if ($from === 'auto') {
                $from = 'file'; // Auto starts with file
            }
        }

        if (!in_array($from, self::VALID_DRIVERS, true)) {
            $this->error("Invalid source driver: {$from}");
            return 1;
        }

        if ($from === $target) {
            $this->warning("Source and target are the same ({$from}). Nothing to migrate.");
            return 0;
        }

        $this->info("Migrating content: {$from} -> {$target}");

        if ($dryRun) {
            $this->warning('[DRY RUN] No changes will be made.');
        }

        if (!$force && !$dryRun) {
            $this->warning('This will copy all content to the new driver.');
            if (!$this->confirm('Continue?', false)) {
                $this->info('Migration cancelled.');
                return 0;
            }
        }

        try {
            $sourceDriver = $this->makeDriver($from);
            $targetDriver = $this->makeDriver($target);

            return $this->migrate($sourceDriver, $targetDriver, $from, $target, $dryRun);
        } catch (\Exception $e) {
            $this->error("Migration failed: {$e->getMessage()}");
            return 1;
        }
    }

    private function makeDriver(string $name): ContentDriver
    {
        $parser = $this->app->make(ContentParser::class);
        $contentPath = content_path('pages');

        return match ($name) {
            'file' => new FileDriver($parser, $contentPath),
            'db' => $this->makeDbDriver(),
            'hybrid' => new HybridDriver(
                $parser,
                $this->makeConnection(),
                $contentPath
            ),
        };
    }

    private function makeDbDriver(): DBDriver
    {
        return new DBDriver($this->makeConnection());
    }

    private function makeConnection(): Connection
    {
        try {
            $connection = $this->app->make(Connection::class);

            // Verify the pages table exists
            $connection->query('SELECT 1 FROM pages LIMIT 1');

            return $connection;
        } catch (\PDOException $e) {
            if (str_contains($e->getMessage(), 'no such table')) {
                throw new \RuntimeException(
                    "Database table 'pages' does not exist.\n" .
                    "Run: ./velvet migrate"
                );
            }
            throw new \RuntimeException(
                "Database connection failed: {$e->getMessage()}\n" .
                "Check config/db.php settings."
            );
        }
    }

    private function migrate(
        ContentDriver $source,
        ContentDriver $target,
        string $sourceName,
        string $targetName,
        bool $dryRun
    ): int {
        $pages = $source->list([]);
        $total = $pages->count();

        if ($total === 0) {
            $this->warning('No pages found in source driver.');
            return 0;
        }

        $this->line("Found {$total} pages to migrate.");
        $this->line('');

        $migrated = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($pages as $page) {
            $status = $dryRun ? '[DRY]' : '     ';

            if ($target->exists($page->slug)) {
                $this->line("{$status} <fg=yellow>SKIP</> {$page->slug} (exists in target)");
                $skipped++;
                continue;
            }

            if (!$dryRun) {
                try {
                    $target->save($page);
                    $this->line("{$status} <fg=green>OK</> {$page->slug}");
                    $migrated++;
                } catch (\Exception $e) {
                    $this->line("{$status} <fg=red>FAIL</> {$page->slug}: {$e->getMessage()}");
                    $failed++;
                }
            } else {
                $this->line("{$status} <fg=blue>WOULD MIGRATE</> {$page->slug}");
                $migrated++;
            }
        }

        $this->line('');
        $this->line("Results: {$migrated} migrated, {$skipped} skipped, {$failed} failed");

        if (!$dryRun && $migrated > 0) {
            $this->line('');
            $this->success("Migration complete: {$sourceName} -> {$targetName}");
            $this->line('');
            $this->info('Next steps:');
            $this->line("  1. Update config/content.php: 'driver' => '{$targetName}'");
            $this->line('  2. Test your site thoroughly');
            $this->line("  3. Once verified, you can remove old {$sourceName} data");
        }

        return $failed > 0 ? 1 : 0;
    }
}