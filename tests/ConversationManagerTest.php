<?php

declare(strict_types=1);

namespace PhpSoftBox\Telegram\Tests;

use PhpSoftBox\Telegram\Conversation\ArrayConversationStore;
use PhpSoftBox\Telegram\Conversation\ConversationDefinition;
use PhpSoftBox\Telegram\Conversation\ConversationManager;
use PhpSoftBox\Telegram\Conversation\QuestionStep;
use PhpSoftBox\Telegram\Support\MessageCleaner;
use PhpSoftBox\Telegram\Tests\Support\FakeTelegramClient;
use PhpSoftBox\Telegram\Update\Update;
use PHPUnit\Framework\TestCase;

final class ConversationManagerTest extends TestCase
{
    /**
     * Проверяем прохождение шагов и сбор данных.
     */
    public function testConversationFlow(): void
    {
        $store  = new ArrayConversationStore();
        $client = new FakeTelegramClient();

        $manager = new ConversationManager($store, $client);

        $result = [];

        $definition = new ConversationDefinition('workspace.create', [
            new QuestionStep('name', 'Название?'),
            new QuestionStep('description', 'Описание?'),
        ])->onFinish(static function ($context) use (&$result): void {
            $result = $context->data();
        });

        $manager->register($definition);

        $manager->start('workspace.create', $this->makeUpdate('start'));
        $manager->handle($this->makeUpdate('Workspace'));
        $manager->handle($this->makeUpdate('Some description'));

        $this->assertSame(['name' => 'Workspace', 'description' => 'Some description'], $result);
        $this->assertNull($store->get('1'));
    }

    /**
     * Проверяем отмену диалога.
     */
    public function testConversationCancel(): void
    {
        $store  = new ArrayConversationStore();
        $client = new FakeTelegramClient();

        $manager = new ConversationManager($store, $client);

        $cancelled = false;

        $definition = new ConversationDefinition('workspace.cancel', [
            new QuestionStep('name', 'Название?'),
        ])
            ->withCancelKeywords(['/cancel'])
            ->onCancel(static function () use (&$cancelled): void {
                $cancelled = true;
            });

        $manager->register($definition);

        $manager->start('workspace.cancel', $this->makeUpdate('start'));
        $manager->handle($this->makeUpdate('/cancel'));

        $this->assertTrue($cancelled);
        $this->assertNull($store->get('1'));
    }

    /**
     * Проверяем очистку предыдущих сообщений.
     */
    public function testCleanupMessages(): void
    {
        $store  = new ArrayConversationStore();
        $client = new FakeTelegramClient();

        $cleaner = new MessageCleaner($client);

        $manager = new ConversationManager($store, $client, $cleaner);

        $definition = new ConversationDefinition('workspace.clean', [
            new QuestionStep('name', 'Название?'),
            new QuestionStep('description', 'Описание?'),
        ])->withCleanupMessages(true);

        $manager->register($definition);

        $manager->start('workspace.clean', $this->makeUpdate('start'));
        $manager->handle($this->makeUpdate('Workspace'));

        $this->assertNotEmpty($client->deletedMessages());
    }

    private function makeUpdate(string $text): Update
    {
        return Update::fromArray([
            'message' => [
                'text' => $text,
                'chat' => ['id' => 1],
            ],
        ]);
    }
}
