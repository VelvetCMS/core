<?php

declare(strict_types=1);

namespace VelvetCMS\Commands\Page;

use VelvetCMS\Commands\Command;
use VelvetCMS\Models\Page;
use VelvetCMS\Services\PageService;

class MakeCommand extends Command
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
        return 'page:make [slug] [--title=] [--interactive]';
    }

    public function description(): string
    {
        return 'Create a new page';
    }

    public function handle(): int
    {
        if ($this->option('interactive') || !$this->arguments) {
            return $this->interactive();
        }

        $slug = $this->argument(0);

        if (!$slug) {
            $this->error('Page slug is required');
            $this->line('Usage: velvet page:make <slug> [--title="Page Title"]');
            return 1;
        }

        if ($this->pageService->exists($slug)) {
            $this->error("Page '{$slug}' already exists");
            return 1;
        }

        $title = $this->option('title', ucwords(str_replace(['-', '_'], ' ', $slug)));

        $page = new Page(
            slug: $slug,
            title: $title,
            content: "# {$title}\n\nYour content here...",
            status: 'draft'
        );

        $this->pageService->save($page);

        $this->success("Page '{$slug}' created successfully!");
        $this->line("Edit: content/pages/{$slug}.md");

        return 0;
    }

    private function interactive(): int
    {
        $this->line();
        $this->line("\033[1mCreate New Page\033[0m");
        $this->line();

        $slug = $this->ask('Page slug (e.g., about-us)');

        if (!$slug) {
            $this->error('Slug is required');
            return 1;
        }

        if ($this->pageService->exists($slug)) {
            $this->error("Page '{$slug}' already exists");
            return 1;
        }

        $title = $this->ask('Page title', ucwords(str_replace(['-', '_'], ' ', $slug)));
        $status = $this->choice('Status', ['draft', 'published'], '0');
        $layout = $this->ask('Layout', 'default');

        $page = new Page(
            slug: $slug,
            title: $title,
            content: "# {$title}\n\nYour content here...",
            status: $status,
            layout: $layout
        );

        $this->line();
        $this->info('Creating page...');

        $this->pageService->save($page);

        $this->success("Page '{$slug}' created successfully!");
        $this->line();
        $this->line("  URL: /{$slug}");
        $this->line("  File: content/pages/{$slug}.md");
        $this->line();

        return 0;
    }
}
