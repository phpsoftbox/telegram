<?php

declare(strict_types=1);

namespace PhpSoftBox\Telegram\Loader;

use PhpSoftBox\Telegram\Attributes\TelegramCommand;
use PhpSoftBox\Telegram\Attributes\TelegramConversation;
use PhpSoftBox\Telegram\Builder\TelegramBotBuilder;
use ReflectionClass;

use function array_diff;
use function array_values;
use function class_exists;
use function get_declared_classes;
use function glob;
use function is_dir;
use function is_file;
use function sort;

final readonly class TelegramAttributeLoader
{
    public function __construct(
        private string $path,
    ) {
    }

    public function load(TelegramBotBuilder $builder): void
    {
        foreach ($this->resolveFiles() as $file) {
            $this->loadFile($file, $builder);
        }
    }

    private function loadFile(string $file, TelegramBotBuilder $builder): void
    {
        $before = get_declared_classes();
        require_once $file;
        $after = get_declared_classes();

        $newClasses = array_values(array_diff($after, $before));
        foreach ($newClasses as $class) {
            if (!class_exists($class)) {
                continue;
            }

            $ref = new ReflectionClass($class);

            if ($ref->isAbstract() || $ref->isInterface()) {
                continue;
            }

            foreach ($ref->getAttributes(TelegramCommand::class) as $attribute) {
                $command = $attribute->newInstance();
                $builder->command($command->name, $class, $command->method);
            }

            foreach ($ref->getAttributes(TelegramConversation::class) as $attribute) {
                $conversation = $attribute->newInstance();
                $builder->conversation($conversation->name, $class, $conversation->method);
            }
        }
    }

    /**
     * @return list<string>
     */
    private function resolveFiles(): array
    {
        if (is_file($this->path)) {
            return [$this->path];
        }

        if (!is_dir($this->path)) {
            return [];
        }

        $files = glob($this->path . '/*.php');
        if ($files === false) {
            return [];
        }

        sort($files);

        return $files;
    }
}
