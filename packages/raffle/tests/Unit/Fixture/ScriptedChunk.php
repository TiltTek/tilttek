<?php

declare(strict_types=1);

namespace Tilttek\Raffle\Tests\Unit\Fixture;

use Override;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Contracts\HttpClient\ChunkInterface;

/**
 * A chunk of tests: its content, whether it is the last, and whether reading it fails.
 */
final readonly class ScriptedChunk implements ChunkInterface
{
    public function __construct(
        private string $content,
        private bool $last = false,
        private bool $broken = false,
    ) {
    }

    #[Override]
    public function isTimeout(): bool
    {
        return false;
    }

    #[Override]
    public function isFirst(): bool
    {
        return false;
    }

    #[Override]
    public function isLast(): bool
    {
        return $this->last;
    }

    /**
     * @return array{0: int, 1: array<string, list<string>>}|null
     */
    #[Override]
    public function getInformationalStatus(): ?array
    {
        return null;
    }

    #[Override]
    public function getContent(): string
    {
        return $this->broken ? throw new TransportException('The connection was reset.') : $this->content;
    }

    #[Override]
    public function getOffset(): int
    {
        return 0;
    }

    #[Override]
    public function getError(): ?string
    {
        return null;
    }
}
