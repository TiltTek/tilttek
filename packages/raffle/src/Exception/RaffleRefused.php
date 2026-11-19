<?php

declare(strict_types=1);

namespace Tilttek\Raffle\Exception;

use DateTimeImmutable;
use DomainException;

/**
 * A step a raffle does not allow in its state: an entry once the entries are closed or twice from one entrant, a draw
 * with a prize without a beacon, a raffle sealed without entrants, a draw before its beacon round.
 */
final class RaffleRefused extends DomainException implements RaffleException
{
    public static function unknown(string $raffle): self
    {
        return new self(\sprintf('No raffle has the identifier %s.', $raffle));
    }

    public static function exists(string $raffle): self
    {
        return new self(\sprintf('A raffle has the identifier %s already.', $raffle));
    }

    public static function prizeNeedsBeacon(): self
    {
        return new self('A raffle with a prize takes its seed from a public beacon round, never from a seed of its organiser.');
    }

    public static function noBeacon(): self
    {
        return new self('A raffle seeded by a beacon needs the beacon: none is configured.');
    }

    public static function notOpen(string $raffle): self
    {
        return new self(\sprintf('The entries of the raffle %s are closed.', $raffle));
    }

    public static function alreadyEntered(string $raffle, string $entrant): self
    {
        return new self(\sprintf('%s has entered the raffle %s already: an entrant holds one chance, never more.', $entrant, $raffle));
    }

    public static function full(string $raffle, int $entrants): self
    {
        return new self(\sprintf('The raffle %s holds %d entrants, the most it takes.', $raffle, $entrants));
    }

    public static function noEntrants(string $raffle): self
    {
        return new self(\sprintf('The raffle %s has no entrant to draw from.', $raffle));
    }

    public static function notSealed(string $raffle): self
    {
        return new self(\sprintf('The raffle %s is not sealed: close its entries before the draw.', $raffle));
    }

    public static function alreadyDrawn(string $raffle): self
    {
        return new self(\sprintf('The raffle %s was drawn already; a raffle is drawn once.', $raffle));
    }

    public static function notDrawn(string $raffle): self
    {
        return new self(\sprintf('The raffle %s is not drawn yet: it has no proof.', $raffle));
    }

    public static function roundNotReached(string $raffle, int $round, DateTimeImmutable $at): self
    {
        return new self(\sprintf('The raffle %s draws from the beacon round %d, published at %s: wait for it.', $raffle, $round, $at->format('Y-m-d\TH:i:s\Z')));
    }
}
