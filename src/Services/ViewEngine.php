<?php

declare(strict_types=1);

namespace VelvetCMS\Services;

/**
 * Unified view rendering engine with namespace support.
 *
 * Resolution order: user/views → namespaced paths → fallback
 * Syntax: {{ $var }} escaped, {!! $var !!} raw, @if/@foreach/@include directives
 */
class ViewEngine
{
    private string $userPath;
    private string $cachePath;
    private array $namespaces = [];
    private array $shared = [];
    private array $sections = [];
    private array $sectionStack = [];
    private ?string $extends = null;
    private array $stacks = [];
    private array $directives = [];

    public function __construct(?string $userPath = null, ?string $cachePath = null)
    {
        $this->userPath = $userPath ?? view_path();
        $this->cachePath = $cachePath ?? storage_path(config('view.compiled', 'cache/views'));

        if (!is_dir($this->cachePath)) {
            mkdir($this->cachePath, 0755, true);
        }

        $this->initShared();
    }

    private function initShared(): void
    {
        $this->shared['asset'] = fn (string $path) => asset($path);
        $this->shared['url'] = fn (string $path = '') => tenant_url($path);
    }

    public function namespace(string $name, string $path): void
    {
        $this->namespaces[$name] = rtrim($path, '/');
    }

    public function share(string $key, mixed $value): void
    {
        $this->shared[$key] = $value;
    }

    public function directive(string $name, callable $compiler): void
    {
        $this->directives[$name] = $compiler;
    }

    public function render(string $view, array $data = []): string
    {
        $this->sections = [];
        $this->sectionStack = [];
        $this->stacks = [];
        $this->extends = null;

        return $this->renderPartial($view, $data);
    }

    public function renderPartial(string $view, array $data = []): string
    {
        $path = $this->resolve($view);
        if ($path === null) {
            throw new \RuntimeException("View '{$view}' not found");
        }

        $vars = array_merge($this->shared, $data);
        $compiled = $this->compileFile($path);
        $content = $this->evaluate($compiled, $vars);

        if ($this->extends !== null && debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2)[1]['function'] === 'render') {
            $parentPath = $this->resolve($this->extends);
            if ($parentPath === null) {
                throw new \RuntimeException("Layout '{$this->extends}' not found");
            }
            $vars['content'] = $content;
            $parentCompiled = $this->compileFile($parentPath);
            return $this->evaluate($parentCompiled, $vars);
        }

        return $content;
    }

    public function exists(string $view): bool
    {
        return $this->resolve($view) !== null;
    }

    public function compileString(string $template, array $data = []): string
    {
        $content = $this->compileEchos($template);
        $content = $this->compileDirectives($content);
        return $this->evaluateString($content, array_merge($this->shared, $data));
    }

    public function safe(string $template, array $data = []): string
    {
        $template = preg_replace('/@php\s*.*?@endphp/s', '', $template);
        $template = preg_replace('/\{!!\s*(.+?)\s*!!\}/', '{{ $1 }}', $template);

        $content = $this->compileEchos($template);
        $content = $this->compileSafeDirectives($content);
        return $this->evaluateString($content, array_merge($this->shared, $data));
    }

    private function resolve(string $view): ?string
    {
        $view = str_replace('.', '/', $view);

        // Check for namespace (blog::posts.index)
        if (str_contains($view, '::')) {
            [$ns, $path] = explode('::', $view, 2);
            $path = str_replace('.', '/', $path);

            // User override first
            $userFile = $this->userPath . '/' . $ns . '/' . $path . '.velvet.php';
            if (file_exists($userFile)) {
                return $userFile;
            }

            // Then namespace path
            if (isset($this->namespaces[$ns])) {
                $nsFile = $this->namespaces[$ns] . '/' . $path . '.velvet.php';
                if (file_exists($nsFile)) {
                    return $nsFile;
                }
            }

            return null;
        }

        // User views first
        $userFile = $this->userPath . '/' . $view . '.velvet.php';
        if (file_exists($userFile)) {
            return $userFile;
        }

        // Check all namespaces as fallback
        foreach ($this->namespaces as $nsPath) {
            $nsFile = $nsPath . '/' . $view . '.velvet.php';
            if (file_exists($nsFile)) {
                return $nsFile;
            }
        }

        return null;
    }

    private function compileFile(string $path): string
    {
        $cacheKey = md5($path . filemtime($path));
        $cacheFile = $this->cachePath . '/' . $cacheKey . '.php';

        if (file_exists($cacheFile)) {
            return $cacheFile;
        }

        $content = file_get_contents($path);
        $content = $this->compileEchos($content);
        $content = $this->compileDirectives($content);

        file_put_contents($cacheFile, $content);
        return $cacheFile;
    }

    private function compileEchos(string $content): string
    {
        $content = preg_replace('/(?<!@)\{!!\s*(.+?)\s*!!\}/', '<?php echo $1; ?>', $content);
        $content = preg_replace('/(?<!@)\{\{\s*(.+?)\s*\}\}/', '<?php echo e($1); ?>', $content);
        $content = str_replace(['@{{', '@{!!'], ['{{', '{!!'], $content);
        return $content;
    }

    private function compileDirectives(string $content): string
    {
        // @extends
        $content = preg_replace_callback(
            '/@extends\s*\(\s*[\'"](.+?)[\'"]\s*\)/',
            fn ($m) => "<?php \$__engine->extend('{$m[1]}'); ?>",
            $content
        );

        // @section / @endsection
        $content = preg_replace_callback(
            '/@section\s*\(\s*[\'"](.+?)[\'"]\s*\)/',
            fn ($m) => "<?php \$__engine->startSection('{$m[1]}'); ?>",
            $content
        );
        $content = preg_replace('/@endsection/', '<?php $__engine->endSection(); ?>', $content);

        // @yield
        $content = preg_replace_callback(
            '/@yield\s*\(\s*[\'"](.+?)[\'"]\s*(?:,\s*[\'"](.+?)[\'"]\s*)?\)/',
            fn ($m) => "<?php echo \$__engine->yieldSection('{$m[1]}', '" . ($m[2] ?? '') . "'); ?>",
            $content
        );

        // Control structures
        $content = preg_replace_callback('/@if\s*\(((?:[^()]|\([^()]*\))*)\)/', fn ($m) => '<?php if (' . $m[1] . '): ?>', $content);
        $content = preg_replace_callback('/@elseif\s*\(((?:[^()]|\([^()]*\))*)\)/', fn ($m) => '<?php elseif (' . $m[1] . '): ?>', $content);
        $content = preg_replace('/@else/', '<?php else: ?>', $content);
        $content = preg_replace('/@endif/', '<?php endif; ?>', $content);

        $content = preg_replace_callback('/@foreach\s*\(((?:[^()]|\([^()]*\))*)\)/', fn ($m) => '<?php foreach (' . $m[1] . '): ?>', $content);
        $content = preg_replace('/@endforeach/', '<?php endforeach; ?>', $content);

        $content = preg_replace_callback('/@for\s*\(((?:[^()]|\([^()]*\))*)\)/', fn ($m) => '<?php for (' . $m[1] . '): ?>', $content);
        $content = preg_replace('/@endfor/', '<?php endfor; ?>', $content);

        $content = preg_replace_callback('/@while\s*\(((?:[^()]|\([^()]*\))*)\)/', fn ($m) => '<?php while (' . $m[1] . '): ?>', $content);
        $content = preg_replace('/@endwhile/', '<?php endwhile; ?>', $content);

        // @include
        $content = preg_replace_callback(
            '/@include\s*\(\s*[\'"]([^\'"]+)[\'"]\s*(?:,\s*((?:\[(?>(?:[^\[\]]|(?2))*)\])))?\s*\)/s',
            fn ($m) => "<?php echo \$__engine->renderPartial('{$m[1]}', array_merge(\$__vars, " . ($m[2] ?? '[]') . ')); ?>',
            $content
        );

        // @php / @endphp
        $content = preg_replace('/@php/', '<?php ', $content);
        $content = preg_replace('/@endphp/', ' ?>', $content);

        // @csrf / @method
        $content = preg_replace('/@csrf/', '<?php echo csrf_field(); ?>', $content);
        $content = preg_replace('/@method\s*\(\s*[\'"](.+?)[\'"]\s*\)/', '<?php echo method_field(\'$1\'); ?>', $content);

        // @push / @endpush / @stack
        $content = preg_replace_callback(
            '/@push\s*\(\s*[\'"](.+?)[\'"]\s*\)/',
            fn ($m) => "<?php \$__engine->startPush('{$m[1]}'); ?>",
            $content
        );
        $content = preg_replace('/@endpush/', '<?php $__engine->endPush(); ?>', $content);
        $content = preg_replace_callback(
            '/@stack\s*\(\s*[\'"](.+?)[\'"]\s*\)/',
            fn ($m) => "<?php echo \$__engine->yieldStack('{$m[1]}'); ?>",
            $content
        );

        // @isset / @endisset
        $content = preg_replace_callback(
            '/@isset\s*\(((?:[^()]|\([^()]*\))*)\)/',
            fn ($m) => '<?php if (isset(' . $m[1] . ')): ?>',
            $content
        );
        $content = preg_replace('/@endisset/', '<?php endif; ?>', $content);

        // @empty / @endempty
        $content = preg_replace_callback(
            '/@empty\s*\(((?:[^()]|\([^()]*\))*)\)/',
            fn ($m) => '<?php if (empty(' . $m[1] . ')): ?>',
            $content
        );
        $content = preg_replace('/@endempty/', '<?php endif; ?>', $content);

        // @unless / @endunless
        $content = preg_replace_callback(
            '/@unless\s*\(((?:[^()]|\([^()]*\))*)\)/',
            fn ($m) => '<?php if (!(' . $m[1] . ')): ?>',
            $content
        );
        $content = preg_replace('/@endunless/', '<?php endif; ?>', $content);

        // Custom directives
        foreach ($this->directives as $name => $compiler) {
            $content = preg_replace_callback(
                '/@' . preg_quote($name, '/') . '\s*\(((?:[^()]|\([^()]*\))*)\)/',
                fn ($m) => $compiler($m[1]),
                $content
            );
        }

        return $content;
    }

    private function compileSafeDirectives(string $content): string
    {
        $content = preg_replace_callback('/@if\s*\(((?:[^()]|\([^()]*\))*)\)/', fn ($m) => '<?php if (' . $m[1] . '): ?>', $content);
        $content = preg_replace_callback('/@elseif\s*\(((?:[^()]|\([^()]*\))*)\)/', fn ($m) => '<?php elseif (' . $m[1] . '): ?>', $content);
        $content = preg_replace('/@else/', '<?php else: ?>', $content);
        $content = preg_replace('/@endif/', '<?php endif; ?>', $content);
        $content = preg_replace_callback('/@foreach\s*\(((?:[^()]|\([^()]*\))*)\)/', fn ($m) => '<?php foreach (' . $m[1] . '): ?>', $content);
        $content = preg_replace('/@endforeach/', '<?php endforeach; ?>', $content);
        return $content;
    }

    private function evaluate(string $path, array $vars): string
    {
        extract($vars);
        $__engine = $this;
        $__vars = $vars;

        ob_start();
        include $path;
        return ob_get_clean();
    }

    private function evaluateString(string $code, array $vars): string
    {
        extract($vars);
        $__engine = $this;
        $__vars = $vars;

        ob_start();
        try {
            eval('?>' . $code);
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        }
        return ob_get_clean();
    }

    public function extend(string $layout): void
    {
        $this->extends = str_replace('.', '/', $layout);
    }

    public function startSection(string $name): void
    {
        $this->sectionStack[] = ['type' => 'section', 'name' => $name];
        ob_start();
    }

    public function endSection(): void
    {
        if (empty($this->sectionStack)) {
            throw new \RuntimeException('@endsection without @section');
        }

        $entry = array_pop($this->sectionStack);
        if ($entry['type'] !== 'section') {
            throw new \RuntimeException('@endsection without matching @section');
        }

        $this->sections[$entry['name']] = ob_get_clean();
    }

    public function yieldSection(string $name, string $default = ''): string
    {
        return $this->sections[$name] ?? $default;
    }

    public function startPush(string $name): void
    {
        $this->sectionStack[] = ['type' => 'push', 'name' => $name];
        ob_start();
    }

    public function endPush(): void
    {
        if (empty($this->sectionStack)) {
            throw new \RuntimeException('@endpush without @push');
        }

        $entry = array_pop($this->sectionStack);
        if ($entry['type'] !== 'push') {
            throw new \RuntimeException('@endpush without matching @push');
        }

        $this->stacks[$entry['name']][] = ob_get_clean();
    }

    public function yieldStack(string $name): string
    {
        if (!isset($this->stacks[$name])) {
            return '';
        }

        return implode("\n", $this->stacks[$name]);
    }

    public function clearCache(): void
    {
        foreach (glob($this->cachePath . '/*.php') as $file) {
            unlink($file);
        }
    }
}
