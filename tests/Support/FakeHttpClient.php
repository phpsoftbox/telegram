<?php

declare(strict_types=1);

namespace PhpSoftBox\Telegram\Tests\Support;

use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;

use function array_key_last;
use function array_shift;
use function array_values;
use function is_array;

final class FakeHttpClient implements ClientInterface
{
    private ?RequestInterface $lastRequest = null;

    public function __construct(
        ResponseInterface|array $response,
    ) {
        $this->responses = is_array($response) ? array_values($response) : [$response];
        $this->fallback  = $this->responses !== [] ? $this->responses[array_key_last($this->responses)] : null;
    }

    /** @var list<ResponseInterface> */
    private array $responses;
    private ?ResponseInterface $fallback = null;

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $this->lastRequest = $request;

        if ($this->responses !== []) {
            return array_shift($this->responses);
        }

        if ($this->fallback !== null) {
            return $this->fallback;
        }

        throw new RuntimeException('No response configured for FakeHttpClient.');
    }

    public function lastRequest(): ?RequestInterface
    {
        return $this->lastRequest;
    }
}
