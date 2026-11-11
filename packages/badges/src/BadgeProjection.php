<?php

declare(strict_types=1);

namespace Tilttek\Badges;

use DateTimeImmutable;
use Override;
use Phil\Resource\ResourceReference;
use Tilttek\Activity\Activity;
use Tilttek\Activity\Entry;
use Tilttek\Activity\Filter;
use Tilttek\Activity\Projection\Projection;
use Tilttek\Badges\Exception\InvalidBadge;
use Tilttek\Badges\Store\AwardStore;
use Tilttek\Badges\Store\ProgressStore;

/**
 * The projection that awards the badges: each recorded activity brings a contribution to every leaf of a criterion
 * that accepts it, once per badge, path and activity, and the badges it touches are evaluated for the member — awarded
 * when their target is reached, again for a repeatable badge each time it is reached again. A cancellation removes the
 * activity's contributions and withdraws the awards of a revocable badge whose target is no longer reached.
 *
 * When a rule changes, `Projector::rebuild()` forgets the contributions of the period and the awards derived from it,
 * and replays its activity under the new catalog. An award earned again during the same rebuild is not announced to
 * the observer a second time; the awards a rebuild withdraws are withdrawn silently. Awards made by hand are kept.
 *
 * One activity brings at most `$maxAwardsPerActivity` awards of a badge, however far past its target it goes: a single
 * large quantity cannot fill the award store nor flood the observer. The awards still due come with the member's next
 * activities for that badge, the same number at most each time.
 */
final class BadgeProjection implements Projection
{
    public const string NAME = 'badges';

    /**
     * How many awards of a badge one activity brings, at most, by default.
     */
    public const int MAX_AWARDS_PER_ACTIVITY = 10;

    /**
     * The awards the last reset forgot, by badge, member and number: earned again, they are not announced again.
     *
     * @var array<string, array<string, array<int, Award>>>
     */
    private array $forgotten = [];

    public function __construct(
        private readonly Evaluator $evaluator,
        private readonly ProgressStore $progress,
        private readonly AwardStore $awards,
        private readonly ?AwardObserver $observer = null,
        private readonly string $name = self::NAME,
        private readonly int $maxAwardsPerActivity = self::MAX_AWARDS_PER_ACTIVITY,
    ) {
        if ($maxAwardsPerActivity < 1) {
            throw InvalidBadge::because(\sprintf('An activity brings at least one award; %d is not a bound.', $maxAwardsPerActivity));
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
        return Filter::all()->verbs(...$this->evaluator->catalog()->patterns());
    }

    #[Override]
    public function apply(Entry $entry): void
    {
        $activity = $entry->activity;
        $catalog = $this->evaluator->catalog();

        if ($entry->isCancellation()) {
            $touched = [];

            foreach ($this->progress->remove($activity->key->value) as $contribution) {
                $touched[$contribution->badge] = $contribution->member;
            }

            foreach ($touched as $code => $member) {
                if ($catalog->has($code)) {
                    $this->evaluate($catalog->get($code), $member, $activity);
                }
            }

            return;
        }

        foreach ($catalog->all() as $badge) {
            $accepted = false;

            foreach ($catalog->leaves($badge) as $path => $leaf) {
                if ($leaf->accepts($activity)) {
                    $this->progress->add(new Contribution($badge->code, $path, $activity->member, $activity->key->value, $activity->quantity, $activity->occurredAt));
                    $accepted = true;
                }
            }

            if ($accepted) {
                $this->evaluate($badge, $activity->member, $activity);
            }
        }
    }

    #[Override]
    public function reset(?DateTimeImmutable $since): void
    {
        $this->progress->forget($since);

        foreach ($this->awards->forgetDerived($since) as $award) {
            $this->forgotten[$award->badge][$award->member->toString()][$award->number] = $award;
        }
    }

    /**
     * Forgets what the last reset kept for its replay, the awards it forgot. A rebuild resets and replays in one call; a
     * long-running process, such as a worker that serves one request after another, calls this between two of them,
     * so that an award earned in the next is announced.
     */
    public function clearReplay(): void
    {
        $this->forgotten = [];
    }

    private function evaluate(Badge $badge, ResourceReference $member, Activity $activity): void
    {
        $earned = $this->evaluator->progress($badge, $member)->times($badge->repeatable);
        $held = $this->awards->of($member, $badge->code);
        $derived = array_filter($held, static fn (Award $award): bool => !$award->manual);

        // A badge held by hand, and not repeatable, is held: the activity neither adds to it nor withdraws it.
        if (!$badge->repeatable && \count($held) > \count($derived)) {
            return;
        }

        $number = max([0, ...array_map(static fn (Award $award): int => $award->number, $held)]);

        $due = min($earned, \count($derived) + $this->maxAwardsPerActivity);

        while (\count($derived) < $due) {
            $award = new Award($badge->code, $member, ++$number, $activity->occurredAt, $activity->key->value);
            $this->awards->add($award);
            $derived[] = $award;
            $this->announce($award, $badge);
        }

        if (!$badge->revocable) {
            return;
        }

        foreach (\array_slice($derived, $earned) as $award) {
            $this->awards->remove($award);
            $this->observer?->revoked($award, $badge);
        }
    }

    private function announce(Award $award, Badge $badge): void
    {
        $member = $award->member->toString();

        if (isset($this->forgotten[$award->badge][$member][$award->number])) {
            unset($this->forgotten[$award->badge][$member][$award->number]);

            return;
        }

        $this->observer?->awarded($award, $badge);
    }
}
