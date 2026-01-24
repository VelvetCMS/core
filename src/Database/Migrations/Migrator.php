<?php

declare(strict_types=1);

namespace VelvetCMS\Database\Migrations;

use VelvetCMS\Database\Connection;
use VelvetCMS\Database\Schema\Schema;

class Migrator
{
    public function __construct(
        private readonly Connection $connection,
        private readonly MigrationRepository $repository
    ) {}

    public function run(string $path): void
    {
        if (!$this->repository->repositoryExists()) {
            $this->repository->createRepository();
        }

        $ran = $this->repository->getRan();

        $files = $this->getMigrationFiles($path);

        $pending = array_diff(array_keys($files), $ran);

        if (empty($pending)) {
            return;
        }

        $batch = $this->repository->getNextBatchNumber();

        foreach ($pending as $name) {
            $file = $files[$name];
            $this->runMigration($name, $file, $batch);
        }
    }

    private function runMigration(string $name, string $file, int $batch): void
    {
        echo "Migrating: {$name}\n";

        if (str_ends_with($file, '.php')) {
            $this->runPhpMigration($file);
        } else {
            $this->runSqlMigration($file);
        }

        $this->repository->log($name, $batch);
        
        echo "Migrated:  {$name}\n";
    }

    private function runPhpMigration(string $file): void
    {
        $migration = require $file;
        
        if (method_exists($migration, 'up')) {
            $migration->up();
        }
    }

    private function runSqlMigration(string $file): void
    {
        $sql = file_get_contents($file);
        
        $statements = [];
        $buffer = '';
        $inSingleQuote = false;
        $inDoubleQuote = false;
        $escaped = false;
        
        $length = strlen($sql);
        
        for ($i = 0; $i < $length; $i++) {
            $char = $sql[$i];
            
            if ($escaped) {
                $buffer .= $char;
                $escaped = false;
                continue;
            }
            
            if ($char === '\\') {
                $escaped = true;
                $buffer .= $char;
                continue;
            }
            
            if ($char === "'" && !$inDoubleQuote) {
                $inSingleQuote = !$inSingleQuote;
            } elseif ($char === '"' && !$inSingleQuote) {
                $inDoubleQuote = !$inDoubleQuote;
            }
            
            if ($char === ';' && !$inSingleQuote && !$inDoubleQuote) {
                $stmt = trim($buffer);
                if ($stmt !== '') {
                    $statements[] = $stmt;
                }
                $buffer = '';
            } else {
                $buffer .= $char;
            }
        }
        
        $stmt = trim($buffer);
        if ($stmt !== '') {
            $statements[] = $stmt;
        }

        foreach ($statements as $statement) {
            $this->connection->statement($statement);
        }
    }

    private function getMigrationFiles(string $path): array
    {
        $files = [];
        $driver = $this->connection->getPdo()->getAttribute(\PDO::ATTR_DRIVER_NAME);

        $allFiles = glob(rtrim($path, '/') . '/*');
        
        $groups = [];
        foreach ($allFiles as $file) {
            $basename = basename($file);
            
            if (preg_match('/^(.+?)(?:\.(?:php|sql|up\.sql|' . $driver . '\.up\.sql))$/', $basename, $matches)) {
                $name = $this->extractMigrationName($basename);
                if ($name) {
                    $groups[$name][] = $file;
                }
            }
        }
        
        ksort($groups);

        foreach ($groups as $name => $candidates) {
            $files[$name] = $this->resolveBestCandidate($candidates, $driver);
        }

        return $files;
    }

    private function extractMigrationName(string $basename): ?string
    {
        $name = $basename;
        $name = preg_replace('/\.php$/', '', $name);
        $name = preg_replace('/\.sql$/', '', $name);
        $name = preg_replace('/\.up$/', '', $name);
        $name = preg_replace('/\.down$/', '', $name);
        $name = preg_replace('/\.(sqlite|mysql|pgsql)$/', '', $name);
        
        return $name === $basename ? null : $name;
    }

    private function resolveBestCandidate(array $candidates, string $driver): string
    {
        foreach ($candidates as $file) {
            if (str_contains($file, ".{$driver}.up.sql")) {
                return $file;
            }
        }

        foreach ($candidates as $file) {
            if (str_contains($file, '.up.sql') && !str_contains($file, '.sqlite.') && !str_contains($file, '.mysql.')) {
                return $file;
            }
        }

        foreach ($candidates as $file) {
            if (str_ends_with($file, '.php')) {
                return $file;
            }
        }

        return $candidates[0];
    }
}
