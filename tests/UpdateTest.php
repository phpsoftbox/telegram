<?php

declare(strict_types=1);

namespace PhpSoftBox\Telegram\Tests;

use PhpSoftBox\Telegram\Update\MessageTypeEnum;
use PhpSoftBox\Telegram\Update\Update;
use PHPUnit\Framework\TestCase;

final class UpdateTest extends TestCase
{
    /**
     * Проверяем извлечение текста и типа сообщения.
     */
    public function testTextMessage(): void
    {
        $update = Update::fromArray([
            'update_id' => 1,
            'message'   => [
                'message_id' => 10,
                'text'       => 'hello',
                'chat'       => ['id' => 100],
                'from'       => ['id' => 200],
            ],
        ]);

        $this->assertSame(1, $update->updateId());
        $this->assertSame('hello', $update->text());
        $this->assertSame(100, $update->chatId());
        $this->assertSame(200, $update->fromId());
        $this->assertSame(MessageTypeEnum::TEXT, $update->type());
    }

    /**
     * Проверяем определение типа фото и file_id.
     */
    public function testPhotoMessage(): void
    {
        $update = Update::fromArray([
            'update_id' => 2,
            'message'   => [
                'message_id' => 11,
                'chat'       => ['id' => 101],
                'photo'      => [
                    ['file_id' => 'x1'],
                    ['file_id' => 'x2'],
                ],
            ],
        ]);

        $message = $update->message();
        $this->assertNotNull($message);
        $this->assertSame(MessageTypeEnum::PHOTO, $message->type());
        $this->assertSame('x2', $message->photoFileId());
        $this->assertSame('x2', $message->value());
    }

    /**
     * Проверяем чтение контакта и номера телефона.
     */
    public function testContactMessage(): void
    {
        $update = Update::fromArray([
            'update_id' => 3,
            'message'   => [
                'message_id' => 12,
                'chat'       => ['id' => 102],
                'from'       => ['id' => 300],
                'contact'    => [
                    'phone_number' => '+79990001122',
                    'user_id'      => 300,
                    'first_name'   => 'Test',
                ],
            ],
        ]);

        $message = $update->message();
        $this->assertNotNull($message);
        $this->assertSame(MessageTypeEnum::CONTACT, $message->type());
        $this->assertSame('+79990001122', $message->contactPhone());
        $this->assertSame('+79990001122', $message->value());
        $this->assertSame(300, $message->contactUserId());
    }
}
