<?php

declare(strict_types=1);

namespace VelvetCMS\Commands\Page;

use VelvetCMS\Commands\Command;
use VelvetCMS\Services\PageService;

class ListCommand extends Command
{
    public static function category(): string
    {
        return 'Pages';
    }

    public function __construct(
        private readonly PageService $pageService
    ) {
    }

    public function signature(): string
    {
        return 'page:list [--status=]';
    }

    public function description(): string
    {
        return 'List all pages';
    }

    public function handle(): int
    {
        $filters = [];

        if ($status = $this->option('status')) {
            $filters['status'] = $status;
        }

        $this->info('Loading pages...');
        $pages = $this->pageService->list($filters);

        if ($pages->isEmpty()) {
            $this->warning('No pages found');
            return 0;
        }

        $this->line();

        $rows = [];
        foreach ($pages as $page) {
            $rows[] = [
                $page->slug,
                $page->title,
                $page->status,
                $page->updatedAt?->format('Y-m-d H:i') ?? 'N/A',
            ];
        }

        $this->table(
            ['Slug', 'Title', 'Status', 'Updated'],
            $rows
        );

        $this->line();
        $this->info("Total: {$pages->count()} pages");

        return 0;
    }
}
