<?php

declare(strict_types=1);

namespace Tilttek\EscapeRoomBundle\Infrastructure\Memory;

use Override;
use Phil\Resource\ResourceReference;
use Symfony\Contracts\Service\ResetInterface;
use Tilttek\EscapeRoomBundle\Application\Contract\ScreenStore;
use Tilttek\EscapeRoomBundle\Application\Screen;

/**
 * The hint screens in the memory of the process, for tests; `reset()`, tagged `kernel.reset`, forgets them between two
 * requests of a worker.
 */
final class InMemoryScreenStore implements ScreenStore, ResetInterface
{
    /** @var array<string, Screen> */
    private array $screens = [];

    #[Override]
    public function show(string $game, Screen $screen): void
    {
        $this->screens[$game] = $screen;
    }

    #[Override]
    public function screen(string $game): ?Screen
    {
        return $this->screens[$game] ?? null;
    }

    #[Override]
    public function ofHost(ResourceReference $host): array
    {
        return array_filter($this->screens, static fn (Screen $screen): bool => $screen->host->equals($host));
    }

    #[Override]
    public function eraseHost(ResourceReference $host, ResourceReference $pseudonym): int
    {
        $screens = $this->ofHost($host);

        foreach ($screens as $game => $screen) {
            $this->screens[$game] = new Screen($screen->kind, $pseudonym, $screen->at, $screen->puzzle, $screen->tier, $screen->penalty, $screen->message);
        }

        return \count($screens);
    }

    #[Override]
    public function forget(string ...$games): int
    {
        $known = array_intersect_key($this->screens, array_flip($games));
        $this->screens = array_diff_key($this->screens, $known);

        return \count($known);
    }

    #[Override]
    public function reset(): void
    {
        $this->screens = [];
    }
}
