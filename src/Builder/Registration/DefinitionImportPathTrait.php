<?php

declare(strict_types=1);

namespace PhpSoftBox\Telegram\Builder\Registration;

use RuntimeException;

use function array_filter;
use function array_values;
use function glob;
use function is_dir;
use function is_file;
use function rtrim;
use function sort;
use function str_ends_with;
use function trim;

use const GLOB_NOSORT;
use const SORT_STRING;

trait DefinitionImportPathTrait
{
    /**
     * @return list<string>
     */
    private function resolveImportPaths(string $path): array
    {
        $path = trim($path);
        if ($path === '') {
            throw new RuntimeException('Import path must be non-empty.');
        }

        if (is_file($path)) {
            return [$path];
        }

        if (!str_ends_with($path, '.php') && is_file($path . '.php')) {
            return [$path . '.php'];
        }

        $files = [];

        if (is_dir($path)) {
            $files = glob(rtrim($path, '/') . '/*.php', GLOB_NOSORT) ?: [];
        } else {
            $files = glob($path, GLOB_NOSORT) ?: [];
        }

        $files = array_values(array_filter(
            $files,
            static fn (string $file): bool => is_file($file) && str_ends_with($file, '.php'),
        ));
        sort($files, SORT_STRING);

        if ($files !== []) {
            return $files;
        }

        throw new RuntimeException('Definition import files not found: ' . $path);
    }
}
