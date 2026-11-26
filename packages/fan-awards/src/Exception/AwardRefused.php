<?php

declare(strict_types=1);

namespace Tilttek\FanAwards\Exception;

use DomainException;
use Tilttek\FanAwards\Refusal;

/**
 * An act the awards' rules refuse, and why; for an ineligible nominee, the reason the application's eligibility gave.
 */
final class AwardRefused extends DomainException implements FanAwardsException
{
    public function __construct(
        public readonly Refusal $refusal,
        public readonly ?string $reason = null,
    ) {
        $message = match ($refusal) {
            Refusal::EditionExists => 'An edition already holds this key.',
            Refusal::WrongPhase => 'The edition is not in a phase that allows this act.',
            Refusal::VoterNotEligible => 'This member may not vote in this edition.',
            Refusal::Ineligible => 'This nominee is not eligible in this category.',
            Refusal::TooManyCandidates => 'The category holds as many nominees as a ballot may offer.',
            Refusal::NotCounted => 'The edition is not counted yet.',
            Refusal::NotAnnounced => 'The results are published once the winners are announced.',
        };

        parent::__construct(null === $reason ? $message : $message.' '.$reason);
    }

    public static function because(Refusal $refusal, ?string $reason = null): self
    {
        return new self($refusal, $reason);
    }
}
