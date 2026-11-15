<?php

declare(strict_types=1);

namespace Tilttek\Sets\Symfony;

use Tilttek\Sets\Exception\InvalidSet;
use Tilttek\Sets\Sets;

/**
 * The message that asks for the undelivered completions to be told again, for at most `$limit` holders. It carries
 * that number only: the holders and their completions stay in the store, which the handler reads.
 */
final readonly class DeliverPendingCompletions
{
    /**
     * The holders told by default.
     */
    public const int LIMIT = 100;

    /**
     * @throws InvalidSet when the limit is below 1 or above {@see Sets::PENDING_LIMIT}
     */
    public function __construct(
        public int $limit = self::LIMIT,
    ) {
        if ($limit < 1 || $limit > Sets::PENDING_LIMIT) {
            throw InvalidSet::because(\sprintf('A pending delivery tells 1 to %d holders; %d is not.', Sets::PENDING_LIMIT, $limit));
        }
    }
}
