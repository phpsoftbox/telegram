<?php

declare(strict_types=1);

namespace PhpSoftBox\Telegram\Update;

enum MessageTypeEnum: string
{
    case CONTACT  = 'contact';
    case TEXT     = 'text';
    case PHOTO    = 'photo';
    case VIDEO    = 'video';
    case AUDIO    = 'audio';
    case VOICE    = 'voice';
    case DOCUMENT = 'document';
    case UNKNOWN  = 'unknown';
}
