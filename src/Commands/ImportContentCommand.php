<?php

declare(strict_types=1);

namespace VelvetCMS\Commands;

use VelvetCMS\Core\Application;
use VelvetCMS\Database\Connection;
use VelvetCMS\Drivers\Content\DBDriver;
use VelvetCMS\Drivers\Content\FileDriver;
use VelvetCMS\Services\ContentParser;

class ImportContentCommand extends Command
{
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
        return 'content:import';
    }

    public function description(): string
    {
        return 'Import content from files to database';
    }

    public function handle(): int
    {
        $this->info('Importing content from files to database...');

        // We need to manually instantiate drivers because the container
        // is bound to the active driver (which is now DB).
        $parser = $this->app->make(ContentParser::class);
        $fileDriver = new FileDriver($parser);
        $db = $this->app->make(Connection::class);
        $dbDriver = new DBDriver($db);

        try {
            $pages = $fileDriver->list([]);

            $count = 0;
            foreach ($pages as $page) {
                $this->line("Importing: {$page->title} ({$page->slug})");

                if ($dbDriver->exists($page->slug)) {
                    $this->warning('  - Skipped (Already exists)');
                    continue;
                }

                $dbDriver->save($page);
                $count++;
            }

            $this->success("Successfully imported {$count} pages.");
            return 0;

        } catch (\Exception $e) {
            $this->error('Import failed: ' . $e->getMessage());
            return 1;
        }
    }
}
