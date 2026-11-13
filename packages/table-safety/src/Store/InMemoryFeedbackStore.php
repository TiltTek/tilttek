<?php

declare(strict_types=1);

namespace Tilttek\TableSafety\Store;

use Override;
use Phil\Resource\ResourceReference;
use Tilttek\TableSafety\Contract\FeedbackStore;
use Tilttek\TableSafety\Feedback\Feedback;

/**
 * The stars and wishes in memory, for tests and for a process that keeps nothing. A session's feedback is listed in
 * the order of the participants' references, not of their answers.
 */
final class InMemoryFeedbackStore implements FeedbackStore
{
    /** @var array<string, array<string, array<string, Feedback>>> by table, session, then participant */
    private array $feedback = [];

    #[Override]
    public function save(Feedback $feedback): void
    {
        $this->feedback[$feedback->table->toString()][$feedback->session][$feedback->participant->toString()] = $feedback;
    }

    #[Override]
    public function ofSession(ResourceReference $table, string $session): array
    {
        $feedback = $this->feedback[$table->toString()][$session] ?? [];
        ksort($feedback, \SORT_STRING);

        return array_values($feedback);
    }

    #[Override]
    public function ofParticipant(ResourceReference $participant): array
    {
        $found = [];

        foreach ($this->feedback as $sessions) {
            foreach ($sessions as $feedback) {
                if (isset($feedback[$participant->toString()])) {
                    $found[] = $feedback[$participant->toString()];
                }
            }
        }

        return $found;
    }

    #[Override]
    public function eraseParticipant(ResourceReference $participant): int
    {
        $erased = 0;

        foreach ($this->feedback as $table => $sessions) {
            foreach ($sessions as $session => $feedback) {
                if (isset($feedback[$participant->toString()])) {
                    unset($this->feedback[$table][$session][$participant->toString()]);
                    ++$erased;
                }
            }
        }

        return $erased;
    }
}
