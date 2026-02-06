<?php

declare(strict_types=1);

namespace PhpSoftBox\Telegram\Webhook;

use PhpSoftBox\Telegram\Bot\UpdateHandlerInterface;
use PhpSoftBox\Telegram\Update\Update;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Throwable;

use function json_encode;

use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;

final class WebhookHandler
{
    public function __construct(
        private readonly UpdateHandlerInterface $handler,
        private readonly ResponseFactoryInterface $responseFactory,
        private readonly StreamFactoryInterface $streamFactory,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $body = (string) $request->getBody();

        try {
            $update = Update::fromJson($body);
        } catch (Throwable $exception) {
            return $this->errorResponse(400, 'Invalid update payload.');
        }

        $this->handler->handle($update);

        return $this->jsonResponse(['ok' => true]);
    }

    /**
     * @param array<string, mixed> $payload Полезная нагрузка ответа.
     */
    private function jsonResponse(array $payload): ResponseInterface
    {
        $body   = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $stream = $this->streamFactory->createStream($body === false ? '' : $body);

        return $this->responseFactory
            ->createResponse(200)
            ->withHeader('Content-Type', 'application/json; charset=utf-8')
            ->withBody($stream);
    }

    private function errorResponse(int $status, string $message): ResponseInterface
    {
        $body   = json_encode(['ok' => false, 'message' => $message], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $stream = $this->streamFactory->createStream($body === false ? '' : $body);

        return $this->responseFactory
            ->createResponse($status)
            ->withHeader('Content-Type', 'application/json; charset=utf-8')
            ->withBody($stream);
    }
}
