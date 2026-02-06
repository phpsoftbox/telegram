<?php

declare(strict_types=1);

namespace PhpSoftBox\Telegram\Tests\Runtime;

use PhpSoftBox\Telegram\Bot\BotContext;
use PhpSoftBox\Telegram\Runtime\ActionHandlerRegistry;
use PhpSoftBox\Telegram\Runtime\ActionRegistry;
use PhpSoftBox\Telegram\Runtime\ButtonGroupMenuComposer;
use PhpSoftBox\Telegram\Runtime\ButtonGroupProvider;
use PhpSoftBox\Telegram\Runtime\ScreenButtonsProvider;
use PhpSoftBox\Telegram\Runtime\ScreenKeyboardFactory;
use PhpSoftBox\Telegram\Runtime\ScreenProvider;
use PhpSoftBox\Telegram\Runtime\Text\ContentVariableRegistry;
use PhpSoftBox\Telegram\Runtime\Text\TextFormatter;
use PhpSoftBox\Telegram\Tests\Support\FakeTelegramClient;
use PhpSoftBox\Telegram\Update\Update;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class RuntimeDefinitionsTest extends TestCase
{
    #[Test]
    public function testButtonGroupMenuComposerBuildsMenuFromPresetGroup(): void
    {
        $groups = new ButtonGroupProvider([
            'instructions.platform' => [
                ['name' => 'help', 'label' => '🆘 Помощь', 'action' => 'help.open', 'row' => 3, 'position' => 1],
                ['name' => 'subscription', 'label' => '🔑 Моя подписка', 'action' => 'start_open', 'row' => 3, 'position' => 2],
            ],
        ]);
        $actions = new ActionRegistry([
            'help.open'  => ['type' => 'callback', 'value' => 'main.help.open'],
            'start_open' => ['type' => 'callback', 'value' => 'start_open'],
        ]);

        $composer = new ButtonGroupMenuComposer($groups, $actions);

        $menu = $composer
            ->forGroup('instructions.platform')
            ->withoutNames('help')
            ->appendCallback('ios', '📱 iOS', 'instruction.platform.ios', row: 1, position: 1)
            ->appendCallback('android', '🤖 Android', 'instruction.platform.android', row: 1, position: 2)
            ->appendCallback('skip', '⏭ Пропустить инструкции', 'start_open', row: 2, position: 1)
            ->build();

        $this->assertSame('instruction.platform.ios', $menu['reply_markup']['inline_keyboard'][0][0]['callback_data']);
        $this->assertSame('instruction.platform.android', $menu['reply_markup']['inline_keyboard'][0][1]['callback_data']);
        $this->assertSame('start_open', $menu['reply_markup']['inline_keyboard'][1][0]['callback_data']);
        $this->assertSame('start_open', $menu['reply_markup']['inline_keyboard'][2][0]['callback_data']);
    }

    #[Test]
    public function testButtonGroupMenuBuilderSupportsRowOffset(): void
    {
        $groups = new ButtonGroupProvider([
            'common.footer' => [
                ['name' => 'support', 'label' => '🆘 Помощь', 'action' => 'help.open', 'row' => 1, 'position' => 1],
            ],
        ]);
        $actions = new ActionRegistry([
            'help.open' => ['type' => 'callback', 'value' => 'main.help.open'],
        ]);

        $composer = new ButtonGroupMenuComposer($groups, $actions);

        $menu = $composer
            ->forGroup('common.footer')
            ->withRowOffset(1)
            ->appendCallback('primary', '🎁 Попробовать 3 дня бесплатно', 'trial_start', row: 1, position: 1)
            ->build();

        $this->assertSame('trial_start', $menu['reply_markup']['inline_keyboard'][0][0]['callback_data']);
        $this->assertSame('main.help.open', $menu['reply_markup']['inline_keyboard'][1][0]['callback_data']);
    }

    #[Test]
    public function testScreenProviderRendersTextAndImage(): void
    {
        $provider = new ScreenProvider([
            's08' => [
                'title' => 'Trial',
                'text'  => 'Привет, {first_name}!',
                'image' => 'https://cdn.example.com/{cover}.jpg',
            ],
        ]);

        $this->assertSame('Привет, Anton!', $provider->render('s08', ['first_name' => 'Anton']));
        $this->assertSame('https://cdn.example.com/main.jpg', $provider->renderImage('s08', ['cover' => 'main']));
    }

    #[Test]
    public function testScreenProviderSupportsConditionalTextBlocks(): void
    {
        $provider = new ScreenProvider([
            'subscription.payment_methods' => [
                'title' => 'Payment methods',
                'text'  => "Тип подписки: {purchase_mode_label}\n"
                    . "{% if !empty({purchase_quantity_line}) %}{purchase_quantity_line}\n{% endif %}"
                    . "{% if !empty({error_notice}) %}{error_notice}\n{% endif %}"
                    . 'Выберите способ оплаты:',
            ],
        ]);

        $this->assertSame(
            "Тип подписки: Личная\nВыберите способ оплаты:",
            $provider->render('subscription.payment_methods', [
                'purchase_mode_label'    => 'Личная',
                'purchase_quantity_line' => '',
                'error_notice'           => '',
            ]),
        );
    }

    #[Test]
    public function testProvidersSupportArbitraryScreenNames(): void
    {
        $screenProvider = new ScreenProvider([
            'welcome_new_user' => [
                'title' => 'Welcome',
                'text'  => 'Привет',
            ],
        ]);
        $buttonsProvider = new ScreenButtonsProvider([
            'welcome_new_user' => [
                ['label' => 'Помощь', 'action' => 'help_open', 'row' => 1, 'position' => 1],
            ],
        ]);

        $this->assertSame('Привет', $screenProvider->render('welcome_new_user'));
        $this->assertCount(1, $buttonsProvider->forScreen('welcome_new_user'));
    }

    #[Test]
    public function testScreenKeyboardFactoryBuildsInlineKeyboard(): void
    {
        $buttons = new ScreenButtonsProvider([
            's08' => [
                ['label' => 'Открыть', 'action' => 'open_link', 'row' => 1, 'position' => 1],
                ['label' => 'Помощь', 'action' => 'help_open', 'row' => 1, 'position' => 2],
            ],
        ]);
        $actions = new ActionRegistry([
            'open_link' => ['type' => 'url', 'value' => 'https://example.com'],
            'help_open' => ['type' => 'callback', 'value' => 'main.help.open'],
        ]);

        $factory = new ScreenKeyboardFactory($buttons, $actions);

        $menu = $factory->inlineMenu('s08');

        $this->assertSame('https://example.com', $menu['reply_markup']['inline_keyboard'][0][0]['url']);
        $this->assertSame('main.help.open', $menu['reply_markup']['inline_keyboard'][0][1]['callback_data']);
    }

    #[Test]
    public function testTextFormatterSupportsVariableRegistry(): void
    {
        $registry = new ContentVariableRegistry()
            ->register('support_link', static fn (array $context): string => 'https://t.me/support');

        $formatter = new TextFormatter($registry);

        $this->assertSame(
            'Помощь: https://t.me/support',
            $formatter->format('Помощь: {support_link}'),
        );
    }

    #[Test]
    public function testTextFormatterSupportsIfEmptyAndComparisons(): void
    {
        $formatter = new TextFormatter();

        $this->assertSame(
            'QTY',
            $formatter->format(
                '{% if !empty({purchase_quantity_line}) %}QTY{% else %}NONE{% endif %}',
                ['purchase_quantity_line' => '2 человека'],
            ),
        );

        $this->assertSame(
            'BEST',
            $formatter->format(
                '{% if plan_months >= 6 %}BEST{% else %}OK{% endif %}',
                ['plan_months' => 12],
            ),
        );

        $this->assertSame(
            'PERSONAL',
            $formatter->format(
                '{% if purchase_mode === "personal" %}PERSONAL{% else %}FAMILY{% endif %}',
                ['purchase_mode' => 'personal'],
            ),
        );
    }

    #[Test]
    public function testActionHandlerRegistryDispatchesResolvedHandler(): void
    {
        RuntimeActionHandlerSpy::$calls = 0;

        $registry = new ActionHandlerRegistry([
            'subscription.personal.open' => [
                'class'  => RuntimeActionHandlerSpy::class,
                'method' => '__invoke',
            ],
        ]);

        $update = Update::fromArray([
            'callback_query' => [
                'id'      => 'cbq-runtime-1',
                'from'    => ['id' => 1001],
                'data'    => 'subscription.type.personal_open',
                'message' => [
                    'chat' => ['id' => 1001],
                ],
            ],
        ]);
        $context = new BotContext(new FakeTelegramClient());

        $this->assertTrue($registry->dispatch('subscription.personal.open', $update, $context));
        $this->assertSame(1, RuntimeActionHandlerSpy::$calls);
    }

    #[Test]
    public function testActionHandlerRegistryReturnsFalseForUnknownAction(): void
    {
        $registry = new ActionHandlerRegistry();
        $update   = Update::fromArray([
            'callback_query' => [
                'id'      => 'cbq-runtime-2',
                'from'    => ['id' => 1002],
                'data'    => 'unknown',
                'message' => [
                    'chat' => ['id' => 1002],
                ],
            ],
        ]);
        $context = new BotContext(new FakeTelegramClient());

        $this->assertFalse($registry->dispatch('unknown.action', $update, $context));
    }
}

final class RuntimeActionHandlerSpy
{
    public static int $calls = 0;

    public function __invoke(Update $update, BotContext $context): void
    {
        self::$calls++;
    }
}
