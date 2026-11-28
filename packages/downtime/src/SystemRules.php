<?php

declare(strict_types=1);

namespace Tilttek\Downtime;

use Tilttek\Downtime\Exception\InvalidDowntime;
use Tilttek\Downtime\Internal\Identifier;

/**
 * The activities the rules of one game system declare, each key once, in the order given.
 */
final readonly class SystemRules
{
    /** The most activities of one system. */
    public const int MAX_ACTIVITIES = 64;

    /** @var array<string, Activity> by key, in the order given */
    private array $byKey;

    /**
     * @param string         $system     the identifier of the game system
     * @param list<Activity> $activities 1 to `MAX_ACTIVITIES`, each key once
     *
     * @throws InvalidDowntime when the identifier is not one, the activities are none or too many, or a key comes twice
     */
    public function __construct(public string $system, array $activities)
    {
        Identifier::check($system, 'a system');

        if ([] === $activities || \count($activities) > self::MAX_ACTIVITIES) {
            throw InvalidDowntime::because(\sprintf('The rules of a system declare 1 to %d activities.', self::MAX_ACTIVITIES));
        }

        $byKey = [];

        foreach ($activities as $activity) {
            if (isset($byKey[$activity->key])) {
                throw InvalidDowntime::because(\sprintf('The activity %s comes twice in the rules of the system %s.', $activity->key, $system));
            }

            $byKey[$activity->key] = $activity;
        }

        $this->byKey = $byKey;
    }

    public function activity(string $key): ?Activity
    {
        return $this->byKey[$key] ?? null;
    }

    /**
     * @return list<Activity> in the order given
     */
    public function activities(): array
    {
        return array_values($this->byKey);
    }
}
