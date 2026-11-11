<?php

declare(strict_types=1);

namespace Tilttek\Collections\Daily;

use DateTimeImmutable;
use DateTimeZone;
use Random\Engine\Xoshiro256StarStar;
use Random\Randomizer;
use Tilttek\Collections\CatalogProvider;
use Tilttek\Collections\Exception\InvalidCollection;

/**
 * The calendar of the item of the day, computed: a day's item is the one the site chose or generated for it, else
 * the one the first rule that speaks of the day puts forward — fixed dates, computed dates —, else the one a draw
 * weighted by rarity gives for that day among the items of the calendar's sets. The draw of a day depends on the day
 * and the calendar's salt only: the same day always gives the same item, and the calendar of a year can be generated
 * ahead and corrected.
 */
final readonly class DailyCalendar
{
    /**
     * The most days `generate()` writes at once.
     */
    public const int MAX_DAYS = 3660;

    /**
     * @param list<DateRule> $rules
     * @param list<string>   $sets  the sets the free days draw from; every item when none is named
     */
    public function __construct(
        private CatalogProvider $catalog,
        private DailyStore $store,
        public DateTimeZone $timezone,
        private string $salt,
        private array $rules = [],
        private array $sets = [],
    ) {
    }

    /**
     * The day a moment falls on, in the calendar's timezone.
     */
    public function dayOf(DateTimeImmutable $moment): string
    {
        return $moment->setTimezone($this->timezone)->format('Y-m-d');
    }

    /**
     * The item of a day: kept, else computed.
     */
    public function pick(string $day): DailyPick
    {
        return $this->store->find($day) ?? $this->compute($day);
    }

    /**
     * The item of a day by the rules and the draw, whatever is kept for it.
     */
    public function compute(string $day): DailyPick
    {
        $midnight = Days::midnight($day, $this->timezone);

        foreach ($this->rules as $rule) {
            $item = $rule->itemOn($midnight);

            if (null !== $item) {
                return new DailyPick($day, $this->catalog->catalog()->item($item)->code, PickOrigin::Rule);
            }
        }

        $randomizer = new Randomizer(new Xoshiro256StarStar(hash('sha256', $this->salt."\0".$day, true)));

        return new DailyPick($day, $this->catalog->catalog()->odds($this->sets)->draw($randomizer)->code, PickOrigin::Drawn);
    }

    /**
     * Writes the computed items of the days from one to another, both included, and returns the calendar of those
     * days. A day the site chose keeps its item; a day already generated keeps its item unless `$regenerate` is true.
     *
     * @return list<DailyPick>
     */
    public function generate(string $from, string $until, bool $regenerate = false): array
    {
        $day = Days::midnight($from, $this->timezone);
        $last = Days::midnight($until, $this->timezone)->format('Y-m-d');
        $picks = [];

        while ($day->format('Y-m-d') <= $last) {
            if (self::MAX_DAYS === \count($picks)) {
                throw InvalidCollection::because(\sprintf('A calendar is generated %d days at a time at most.', self::MAX_DAYS));
            }

            $kept = $this->store->find($day->format('Y-m-d'));

            if (!$kept instanceof DailyPick || (PickOrigin::Chosen !== $kept->origin && $regenerate)) {
                $kept = $this->compute($day->format('Y-m-d'));
                $this->store->save($kept);
            }

            $picks[] = $kept;
            $day = $day->modify('+1 day');
        }

        return $picks;
    }

    /**
     * The site's choice for a day, in place of what the calendar computes.
     */
    public function choose(string $day, string $item): DailyPick
    {
        $pick = new DailyPick($day, $this->catalog->catalog()->item($item)->code, PickOrigin::Chosen);
        $this->store->save($pick);

        return $pick;
    }

    /**
     * Forgets what is kept for a day, which goes back to what the calendar computes; returns whether something was.
     */
    public function release(string $day): bool
    {
        return $this->store->remove($day);
    }
}
