<?php

declare(strict_types=1);

namespace Tilttek\SessionScheduler\Tests\Unit\Fixture;

use DateTimeInterface;
use Override;
use Tilttek\SessionScheduler\Contract\SessionStore;
use Tilttek\SessionScheduler\Series;
use Tilttek\SessionScheduler\Session;
use Tilttek\SessionScheduler\SessionQuery;

/**
 * A store that counts the series it reads, by identifier, and leaves the rest to another.
 */
final class CountingStore implements SessionStore
{
    /** @var array<string, int> */
    public array $seriesRead = [];

    public function __construct(private readonly SessionStore $store)
    {
    }

    #[Override]
    public function series(string $id): ?Series
    {
        $this->seriesRead[$id] = ($this->seriesRead[$id] ?? 0) + 1;

        return $this->store->series($id);
    }

    #[Override]
    public function saveSeries(Series $series): Series
    {
        return $this->store->saveSeries($series);
    }

    #[Override]
    public function session(string $id): ?Session
    {
        return $this->store->session($id);
    }

    #[Override]
    public function save(Session $session): Session
    {
        return $this->store->save($session);
    }

    #[Override]
    public function sessions(SessionQuery $query, int $limit): iterable
    {
        return $this->store->sessions($query, $limit);
    }

    #[Override]
    public function countComing(string $member, DateTimeInterface $after): int
    {
        return $this->store->countComing($member, $after);
    }
}
