<?php

declare(strict_types=1);

namespace Tilttek\Collections;

use Tilttek\Collections\Exception\InvalidCollection;

/**
 * A set of items to bring together, worth its rewards once a member owns every item of it: its code, its translated
 * name, how it comes to a member, and the rewards it declares.
 */
final readonly class ItemSet
{
    public const int MAX_REWARDS = 20;

    /**
     * @param list<Reward> $rewards
     */
    public function __construct(
        public string $code,
        public Texts $name,
        public SetKind $kind = SetKind::Plain,
        public array $rewards = [],
    ) {
        Names::check($code, 'set');

        if (\count($rewards) > self::MAX_REWARDS) {
            throw InvalidCollection::because(\sprintf('The set "%s" declares at most %d rewards.', $code, self::MAX_REWARDS));
        }
    }
}
