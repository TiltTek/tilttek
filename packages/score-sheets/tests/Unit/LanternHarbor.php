<?php

declare(strict_types=1);

namespace Tilttek\ScoreSheets\Tests\Unit;

use Tilttek\ScoreSheets\SheetDefinition;

/**
 * The sheet of Lantern Harbor, a game invented for the tests: boats and lanterns make the harbour, sets of lanterns
 * score 4 each, the largest fleet takes 6 points and the second 3, ten lanterns light the lighthouse for 5; coins
 * break ties, then the fewest boats.
 */
final class LanternHarbor
{
    public const string JSON = <<<'JSON'
        {
            "game": "lantern-harbor",
            "categories": [
                {"key": "boats", "label": "Boats moored", "counted": false, "min": 0, "max": 12},
                {"key": "lanterns", "label": "Lanterns lit", "counted": false, "min": 0, "max": 40},
                {"key": "harbor", "label": "Harbour", "operation": {"type": "sum", "of": ["boats", "lanterns"]}},
                {"key": "sets", "label": "Sets of lanterns", "counted": false, "min": 0, "max": 10},
                {"key": "set_points", "label": "Points for sets", "operation": {"type": "product", "of": ["sets"], "factor": 4}},
                {"key": "fleet", "label": "Largest fleet", "operation": {"type": "majority", "of": "boats", "points": [6, 3]}},
                {"key": "lighthouse", "label": "Lighthouse", "operation": {"type": "bonus", "of": "lanterns", "at_least": 10, "points": 5}},
                {"key": "coins", "label": "Coins", "counted": false, "min": 0}
            ],
            "tiebreakers": [{"category": "coins", "prefer": "most"}, {"category": "boats", "prefer": "fewest"}],
            "teams": "sum"
        }
        JSON;

    public static function sheet(): SheetDefinition
    {
        return SheetDefinition::fromJson(self::JSON);
    }
}
