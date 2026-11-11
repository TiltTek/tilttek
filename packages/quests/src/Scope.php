<?php

declare(strict_types=1);

namespace Tilttek\Quests;

use Tilttek\Activity\Activity;
use Tilttek\Activity\Context;
use Tilttek\Activity\Exception\InvalidActivity;
use Tilttek\Activity\Verbs;
use Tilttek\Quests\Exception\InvalidQuest;

/**
 * The activities a step reads: verbs by pattern — at least one — and the facts their context must hold, such as
 * `system: 'dnd5e'`. When they count is the quest's business: its schedule, its chain and the member's enrolment.
 */
final readonly class Scope
{
    /**
     * @var list<string>
     */
    public array $patterns;

    public Verbs $verbs;

    public Context $conditions;

    /**
     * @param array<array-key, string>       $verbs
     * @param array<string, bool|int|string> $context
     */
    public function __construct(array $verbs, array $context = [])
    {
        if ([] === $verbs) {
            throw InvalidQuest::because('A step reads at least one verb.');
        }

        try {
            $this->verbs = Verbs::of($verbs);
            $this->conditions = new Context($context);
        } catch (InvalidActivity $invalidActivity) {
            throw InvalidQuest::because($invalidActivity->getMessage());
        }

        $this->patterns = array_values(array_unique($verbs));
    }

    public function accepts(Activity $activity): bool
    {
        return $this->verbs->allows($activity->verb)
            && array_all($this->conditions->values, static fn (bool|int|string $value, string $name): bool => $activity->context->get($name) === $value);
    }
}
