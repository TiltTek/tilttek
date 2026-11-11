<?php

declare(strict_types=1);

namespace Tilttek\Collections\Daily;

use DateTimeImmutable;
use Override;
use Tilttek\Activity\Entry;
use Tilttek\Activity\Filter;
use Tilttek\Activity\Projection\Projection;
use Tilttek\Collections\Exception\InvalidCollection;

/**
 * The projection that gives the item of the day: the first activity of a member on a day, among the verbs the site
 * names — `page.viewed`, `member.signed_in` —, gives them the item of that day. The cancellation of that activity
 * takes it back if the member still has it and never traded it. Before a rebuild, the items of the day given in the
 * period and never traded are forgotten, then given again as the activity is replayed.
 */
final readonly class DailyProjection implements Projection
{
    public const string NAME = 'collections.daily';

    /**
     * @param list<string> $verbs the verb patterns of the activity that counts as coming by
     */
    public function __construct(
        private Daily $daily,
        private array $verbs,
        private string $name = self::NAME,
    ) {
        if ([] === $verbs) {
            throw InvalidCollection::because('The item of the day is claimed on at least one verb.');
        }
    }

    #[Override]
    public function name(): string
    {
        return $this->name;
    }

    #[Override]
    public function filter(): Filter
    {
        return Filter::all()->verbs(...$this->verbs);
    }

    #[Override]
    public function apply(Entry $entry): void
    {
        $activity = $entry->activity;

        if ($entry->isCancellation()) {
            $this->daily->takeBack($activity->member, $activity->occurredAt);

            return;
        }

        $this->daily->claim($activity->member, $activity->occurredAt);
    }

    #[Override]
    public function reset(?DateTimeImmutable $since): void
    {
        $this->daily->forget($since);
    }
}
