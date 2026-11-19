<?php

declare(strict_types=1);

namespace Tilttek\LuckTokensBundle\Tests\Unit\Fixture;

use Phil\Resource\ResourceReference;
use Tilttek\LuckTokens\TableRules;

/**
 * The references and the rules the tests share: a table with inspiration, bennies and fate points, and one game master.
 */
final class Table
{
    public const string RULES = <<<'JSON'
        {
            "kinds": [
                {"key": "inspiration", "name": "Inspiration", "cap": 1, "transferable": true},
                {"key": "bennies", "name": "Bennies", "cap": 5, "scope": "player", "refresh": "each-session", "refresh_to": 3, "carry_over": 0, "grants": "anyone", "transferable": true},
                {"key": "fate", "name": "Fate points", "cap": 1000, "refresh": "each-session", "refresh_to": 3}
            ],
            "game_masters": ["member:gm"]
        }
        JSON;

    private function __construct()
    {
    }

    public static function ref(string $reference): ResourceReference
    {
        return ResourceReference::fromString($reference);
    }

    public static function gm(): ResourceReference
    {
        return self::ref('member:gm');
    }

    public static function rules(string $table = 'table:1'): TableRules
    {
        return TableRules::fromJson(self::ref($table), self::RULES);
    }
}
