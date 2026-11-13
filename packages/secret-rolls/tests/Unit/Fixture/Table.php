<?php

declare(strict_types=1);

namespace Tilttek\SecretRolls\Tests\Unit\Fixture;

use DateTimeImmutable;
use Override;
use Random\Engine\Xoshiro256StarStar;
use Random\Randomizer;
use Tilttek\SecretRolls\Contract\JournalPublisher;
use Tilttek\SecretRolls\Journal\JournalEntry;
use Tilttek\SecretRolls\SecretRolls;
use Tilttek\SecretRolls\Store\InMemorySecretRollStore;

/**
 * A table of tests: a store in memory, a seeded randomizer, and the entries published, in order.
 */
final class Table implements JournalPublisher
{
    public InMemorySecretRollStore $store;

    public SecretRolls $rolls;

    /** @var list<JournalEntry> */
    public array $published = [];

    public function __construct(int $seed = 7)
    {
        $this->store = new InMemorySecretRollStore();
        $this->rolls = new SecretRolls($this->store, $this, secure: new Randomizer(new Xoshiro256StarStar($seed)));
    }

    public static function at(string $time = '2026-11-12 20:00:00'): DateTimeImmutable
    {
        return new DateTimeImmutable($time.' UTC');
    }

    #[Override]
    public function publish(JournalEntry $entry): void
    {
        $this->published[] = $entry;
    }
}
