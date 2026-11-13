<?php

declare(strict_types=1);

namespace Tilttek\TableSafety\Contract;

use Phil\Resource\ResourceReference;
use Tilttek\TableSafety\Feedback\Feedback;

/**
 * Where the stars and wishes of each session are kept, one feedback per table, session and participant.
 */
interface FeedbackStore
{
    /**
     * Keeps the feedback, in the place of the one of its table, session and participant.
     */
    public function save(Feedback $feedback): void;

    /**
     * @return list<Feedback> the session's, in no order that tells who answered first
     */
    public function ofSession(ResourceReference $table, string $session): array;

    /**
     * @return list<Feedback> the participant's, at every table
     */
    public function ofParticipant(ResourceReference $participant): array;

    /**
     * Deletes the participant's feedback at every table; the number deleted.
     */
    public function eraseParticipant(ResourceReference $participant): int;
}
