<?php

declare(strict_types=1);

namespace Tilttek\LuckTokens\Tests\Unit\Fixture;

use Phil\Resource\ResourceReference;
use Symfony\Component\Clock\MockClock;
use Tilttek\LuckTokens\Contract\HolderAccess;
use Tilttek\LuckTokens\Contract\TokenLedger;
use Tilttek\LuckTokens\LuckTokens;
use Tilttek\LuckTokens\Store\InMemoryTokenLedger;
use Tilttek\LuckTokens\Store\InMemoryTokenRules;
use Tilttek\LuckTokens\Store\SelfAccess;
use Tilttek\LuckTokens\TableRules;

/**
 * A table with three kinds of tokens — inspiration, bennies and fate points — and one game master, wired in memory,
 * at a time in Montréal.
 */
final readonly class Table
{
    public const string RULES = <<<'JSON'
        {
            "kinds": [
                {"key": "inspiration", "name": "Inspiration", "cap": 1, "transferable": true},
                {"key": "bennies", "name": "Bennies", "cap": 5, "scope": "player", "refresh": "each-session", "refresh_to": 3, "carry_over": 0, "grants": "anyone", "transferable": true},
                {"key": "fate", "name": "Fate points", "cap": 10, "refresh": "each-session", "refresh_to": 3}
            ],
            "game_masters": ["member:gm"]
        }
        JSON;

    public ResourceReference $table;

    public InMemoryTokenRules $rules;

    public MockClock $clock;

    public Recorder $events;

    public LuckTokens $tokens;

    public function __construct(
        public TokenLedger $ledger = new InMemoryTokenLedger(),
        HolderAccess $access = new SelfAccess(),
        int $maxEntries = LuckTokens::MAX_ENTRIES,
    ) {
        $this->table = self::ref('table:1');
        $this->rules = new InMemoryTokenRules();
        $this->rules->save(TableRules::fromJson($this->table, self::RULES));

        $this->clock = new MockClock('2026-11-13 19:30:00.250000', 'America/Toronto');
        $this->events = new Recorder();
        $this->tokens = new LuckTokens($this->rules, $this->ledger, $this->events, $access, $this->clock, $maxEntries);
    }

    public static function ref(string $reference): ResourceReference
    {
        return ResourceReference::fromString($reference);
    }

    public static function gm(): ResourceReference
    {
        return self::ref('member:gm');
    }
}
