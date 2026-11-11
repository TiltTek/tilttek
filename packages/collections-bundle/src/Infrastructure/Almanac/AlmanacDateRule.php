<?php

declare(strict_types=1);

namespace Tilttek\CollectionsBundle\Infrastructure\Almanac;

use DateTimeImmutable;
use Override;
use Phil\Almanac\Exception\InvalidRule;
use Phil\Almanac\Exception\OutOfRange;
use Phil\Almanac\Rule\Rule;
use Phil\Almanac\Rule\RuleParser;
use Tilttek\Collections\Daily\DateRule;
use Tilttek\Collections\Exception\InvalidCollection;
use Tilttek\Collections\Names;

/**
 * An item put forward on the days `phil/almanac` computes, in its words — `easter`, `fourth thursday of november`,
 * `saturday on or after 10-25` —, for one day or several from that day:
 *
 * ```yaml
 * computed: [{ rule: easter, item: egg }, { rule: 'fourth thursday of november', item: turkey, days: 4 }]
 * ```
 */
final readonly class AlmanacDateRule implements DateRule
{
    public const int MAX_DAYS = 366;

    private Rule $rule;

    public function __construct(
        string $rule,
        public string $item,
        public int $days = 1,
    ) {
        Names::check($item, 'item');

        if ($days < 1 || $days > self::MAX_DAYS) {
            throw InvalidCollection::because(\sprintf('An item of computed dates is put forward 1 to %d days; "%s" is %d.', self::MAX_DAYS, $item, $days));
        }

        try {
            $this->rule = RuleParser::parse($rule);
        } catch (InvalidRule $invalidRule) {
            throw InvalidCollection::because($invalidRule->getMessage());
        }
    }

    #[Override]
    public function itemOn(DateTimeImmutable $day): ?string
    {
        $year = (int) $day->format('Y');

        try {
            $starts = [...$this->rule->in($year - 1, $day->getTimezone()), ...$this->rule->in($year, $day->getTimezone())];
        } catch (OutOfRange) {
            $starts = [];
        }

        foreach ($starts as $start) {
            if ($start->format('Y-m-d') <= $day->format('Y-m-d') && $day->format('Y-m-d') <= $start->modify(\sprintf('+%d days', $this->days - 1))->format('Y-m-d')) {
                return $this->item;
            }
        }

        return null;
    }
}
