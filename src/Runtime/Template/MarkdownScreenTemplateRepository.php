<?php

declare(strict_types=1);

namespace PhpSoftBox\Telegram\Runtime\Template;

use RuntimeException;

use function array_key_exists;
use function array_values;
use function file_get_contents;
use function is_dir;
use function is_file;
use function is_string;
use function ltrim;
use function preg_replace;
use function rtrim;
use function str_contains;
use function str_ends_with;
use function str_replace;
use function trim;

final class MarkdownScreenTemplateRepository implements ScreenTemplateRepositoryInterface
{
    /**
     * @var list<string>
     */
    private array $directories;

    /**
     * @var array<string, string>
     */
    private array $cache = [];

    /**
     * @param list<string> $directories
     */
    public function __construct(array $directories)
    {
        $resolved = [];
        foreach ($directories as $directory) {
            if (!is_string($directory)) {
                continue;
            }

            $path = rtrim(trim($directory), '/');
            if ($path === '') {
                continue;
            }

            if (!is_dir($path)) {
                throw new RuntimeException('Screen template directory not found: ' . $path);
            }

            $resolved[] = $path;
        }

        if ($resolved === []) {
            throw new RuntimeException('At least one screen template directory is required.');
        }

        $this->directories = array_values($resolved);
    }

    public function get(string $templateId): string
    {
        $templateId = $this->normalizeTemplateId($templateId);

        if (array_key_exists($templateId, $this->cache)) {
            return $this->cache[$templateId];
        }

        $path = $this->resolveTemplatePath($templateId);
        if ($path === null) {
            throw new RuntimeException('Screen template not found: ' . $templateId);
        }

        $contents = file_get_contents($path);
        if (!is_string($contents)) {
            throw new RuntimeException('Unable to read screen template: ' . $path);
        }

        $contents                 = (string) preg_replace("/\r\n?/", "\n", $contents);
        $this->cache[$templateId] = $contents;

        return $contents;
    }

    private function normalizeTemplateId(string $templateId): string
    {
        $templateId = trim($templateId);
        if ($templateId === '') {
            throw new RuntimeException('Screen template id must not be empty.');
        }

        if (str_contains($templateId, '..')) {
            throw new RuntimeException('Screen template id must not contain "..".');
        }

        return $templateId;
    }

    private function resolveTemplatePath(string $templateId): ?string
    {
        $relative = ltrim(str_replace('.', '/', $templateId), '/');
        if ($relative === '') {
            return null;
        }

        $candidates = [];
        if (str_ends_with($relative, '.md')) {
            $candidates[] = $relative;
        } else {
            $candidates[] = $relative . '.md';
        }
        $candidates[] = $relative . '/index.md';

        foreach ($this->directories as $directory) {
            foreach ($candidates as $candidate) {
                $path = $directory . '/' . $candidate;
                if (is_file($path)) {
                    return $path;
                }
            }
        }

        return null;
    }
}
