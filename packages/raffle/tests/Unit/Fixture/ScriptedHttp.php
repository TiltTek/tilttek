<?php

declare(strict_types=1);

namespace Tilttek\Raffle\Tests\Unit\Fixture;

use Generator;
use Override;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\HttpClient\Response\ResponseStream;
use Symfony\Contracts\HttpClient\ChunkInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Symfony\Contracts\HttpClient\ResponseStreamInterface;

/**
 * An HTTP client of tests that answers each request with a response, then streams the chunks it was given, whatever
 * order and shape: the moments a real transport may produce and a mock never does.
 */
final class ScriptedHttp implements HttpClientInterface
{
    /** @var list<MockResponse> */
    public array $responses = [];

    /**
     * @param list<array{0: int, 1: ChunkInterface}> $chunks the index of the request each chunk belongs to, and the chunk
     */
    public function __construct(private readonly array $chunks)
    {
    }

    /**
     * @param array<mixed> $options
     */
    #[Override]
    public function request(string $method, string $url, array $options = []): ResponseInterface
    {
        return $this->responses[] = new MockResponse('');
    }

    #[Override]
    public function stream(ResponseInterface|iterable $responses, ?float $timeout = null): ResponseStreamInterface
    {
        return new ResponseStream($this->play());
    }

    /**
     * @param array<mixed> $options
     */
    #[Override]
    public function withOptions(array $options): static
    {
        return $this;
    }

    /**
     * @return Generator<MockResponse, ChunkInterface>
     */
    private function play(): Generator
    {
        foreach ($this->chunks as [$index, $chunk]) {
            yield $this->responses[$index] => $chunk;
        }
    }
}
