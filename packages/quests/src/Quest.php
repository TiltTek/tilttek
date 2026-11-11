<?php

declare(strict_types=1);

namespace Tilttek\Quests;

use DateInterval;
use DateTimeImmutable;
use Exception;
use Tilttek\Activity\Activity;
use Tilttek\Quests\Exception\InvalidQuest;
use Tilttek\Quests\Schedule\Permanent;
use Tilttek\Quests\Schedule\Schedule;

/**
 * A quest: steps a member — the community, a team, or each side of a duel — accomplishes, in order or in any order,
 * while the quest is open; the quest it follows in a chain, if any; and the rewards it declares.
 *
 * - **The steps** read the journal of activity; at most 50, each with its own code.
 * - **Ordered steps** count an activity for a step only once the steps before it are done, from the moment the last
 *   of them was.
 * - **The schedule** says when the quest is open; it is accomplished once per occurrence.
 * - **`after`** names the quest that opens this one: only the activity after the subject accomplished it counts.
 *   A duel is never chained.
 * - **The admission** says whether members follow it as soon as it is open or once they accept it; a team quest and a
 *   duel are always accepted.
 * - **`duelLength`**, for a duel, how long it lasts once accepted, as an ISO 8601 duration; without it, until the
 *   quest closes.
 */
final readonly class Quest
{
    public const int MAX_STEPS = 50;

    public const int MAX_REWARDS = 20;

    public Admission $admission;

    public ?DateInterval $duelLength;

    /**
     * @param list<Step>   $steps
     * @param list<Reward> $rewards
     */
    public function __construct(
        public string $code,
        public array $steps,
        public QuestKind $kind = QuestKind::Personal,
        public bool $ordered = false,
        public Schedule $schedule = new Permanent(),
        ?Admission $admission = null,
        public ?string $after = null,
        public array $rewards = [],
        ?string $duelLength = null,
    ) {
        Names::check($code, 'quest');

        if ([] === $steps || \count($steps) > self::MAX_STEPS) {
            throw InvalidQuest::because(\sprintf('A quest has 1 to %d steps; "%s" has %d.', self::MAX_STEPS, $code, \count($steps)));
        }

        $codes = array_map(static fn (Step $step): string => $step->code, $steps);

        if (\count(array_unique($codes)) !== \count($codes)) {
            throw InvalidQuest::because(\sprintf('The steps of "%s" each have their own code.', $code));
        }

        if (\count($rewards) > self::MAX_REWARDS) {
            throw InvalidQuest::because(\sprintf('A quest declares at most %d rewards; "%s" declares %d.', self::MAX_REWARDS, $code, \count($rewards)));
        }

        $this->admission = $this->admission($admission);
        $this->duelLength = $this->duelLength($duelLength);

        if (null !== $after) {
            Names::check($after, 'quest');

            if ($after === $code || QuestKind::Duel === $kind) {
                throw InvalidQuest::because(\sprintf('"%s" cannot follow "%s": a quest follows another, and a duel none.', $code, $after));
            }
        }
    }

    /**
     * The steps that accept the activity.
     *
     * @return list<Step>
     */
    public function stepsAccepting(Activity $activity): array
    {
        return array_values(array_filter($this->steps, static fn (Step $step): bool => $step->accepts($activity)));
    }

    /**
     * The verbs its steps read, as patterns.
     *
     * @return list<string>
     */
    public function patterns(): array
    {
        return array_values(array_unique(array_merge(...array_map(static fn (Step $step): array => $step->scope->patterns, $this->steps))));
    }

    /**
     * Whether only the activity after the member accepted the quest counts.
     */
    public function needsAcceptance(): bool
    {
        return Admission::OnAcceptance === $this->admission;
    }

    private function admission(?Admission $admission): Admission
    {
        $joined = QuestKind::Team === $this->kind || QuestKind::Duel === $this->kind;

        if ($joined && Admission::Automatic === $admission) {
            throw InvalidQuest::because(\sprintf('A %s quest is joined by acceptance; "%s" cannot be followed automatically.', $this->kind->value, $this->code));
        }

        return $admission ?? ($joined ? Admission::OnAcceptance : Admission::Automatic);
    }

    private function duelLength(?string $length): ?DateInterval
    {
        if (null === $length) {
            return null;
        }

        if (QuestKind::Duel !== $this->kind) {
            throw InvalidQuest::because(\sprintf('Only a duel lasts for a length; "%s" is a %s quest.', $this->code, $this->kind->value));
        }

        try {
            $interval = new DateInterval($length);
        } catch (Exception) {
            throw InvalidQuest::because(\sprintf('"%s" is not an ISO 8601 duration like "P7D".', $length));
        }

        $now = new DateTimeImmutable();

        if ($now->add($interval) <= $now) {
            throw InvalidQuest::because(\sprintf('A duel lasts some time; "%s" is no time.', $length));
        }

        return $interval;
    }
}
