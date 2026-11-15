<?php

declare(strict_types=1);

namespace Tilttek\Bounties;

use DateTimeImmutable;
use Phil\Resource\ResourceReference;

/**
 * What a member asks for when they offer a bounty: its identifier, chosen by the application, what it is offered on —
 * a question, a quest, a request —, why, its points and when it ends. The end is required: a bounty never stays open
 * without a limit. The subject is never a reference of the kind `erased`, which the library draws for erased members.
 */
final readonly class BountyDraft
{
    public DateTimeImmutable $endsAt;

    public function __construct(
        public string $id,
        public ResourceReference $subject,
        public BountyReason $reason,
        public Prize $prize,
        DateTimeImmutable $endsAt,
    ) {
        Check::identifier($id, 'A bounty identifier');
        Check::notErased($subject, 'The subject of a bounty');
        $this->endsAt = Instant::utc($endsAt);
    }
}
