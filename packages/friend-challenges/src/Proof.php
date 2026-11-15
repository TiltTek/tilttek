<?php

declare(strict_types=1);

namespace Tilttek\FriendChallenges;

use DateTimeImmutable;
use Phil\Resource\ResourceReference;

/**
 * A participant's proof that they met the challenge: an opaque reference to what the application keeps — an upload,
 * a play log, a post —, when it was submitted, where its review stands and, when the proofs are put to a vote, who
 * approved it and who rejected it.
 */
final readonly class Proof
{
    public DateTimeImmutable $submittedAt;

    /**
     * @param list<string> $approvals  the references of the members who approved it
     * @param list<string> $rejections the references of the members who rejected it
     */
    public function __construct(
        public string $id,
        public ResourceReference $participant,
        public string $evidence,
        DateTimeImmutable $submittedAt,
        public ProofState $state = ProofState::Pending,
        public array $approvals = [],
        public array $rejections = [],
    ) {
        Check::identifier($id, 'A proof identifier');
        Check::reference($evidence, 'The evidence of a proof');
        $this->submittedAt = Instant::utc($submittedAt);
    }

    /**
     * Whether the other proof is the same submission: the same participant and evidence.
     */
    public function sameSubmission(ResourceReference $participant, string $evidence): bool
    {
        return $this->participant->equals($participant) && $this->evidence === $evidence;
    }

    public function decided(ProofState $state): self
    {
        return new self($this->id, $this->participant, $this->evidence, $this->submittedAt, $state, $this->approvals, $this->rejections);
    }

    /**
     * @param list<string> $approvals
     * @param list<string> $rejections
     */
    public function voted(array $approvals, array $rejections): self
    {
        return new self($this->id, $this->participant, $this->evidence, $this->submittedAt, $this->state, $approvals, $rejections);
    }

    /**
     * The proof as the pseudonym submitted it: the pseudonym its participant, and the evidence replaced by it.
     */
    public function writtenBy(ResourceReference $pseudonym): self
    {
        return new self($this->id, $pseudonym, $pseudonym->toString(), $this->submittedAt, $this->state, $this->approvals, $this->rejections);
    }

    /**
     * @return array{id: string, participant: string, evidence: string, submitted_at: string, state: string, approvals: list<string>, rejections: list<string>}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'participant' => $this->participant->toString(),
            'evidence' => $this->evidence,
            'submitted_at' => Instant::write($this->submittedAt),
            'state' => $this->state->value,
            'approvals' => $this->approvals,
            'rejections' => $this->rejections,
        ];
    }
}
