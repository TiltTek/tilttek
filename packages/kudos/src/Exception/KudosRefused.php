<?php

declare(strict_types=1);

namespace Tilttek\Kudos\Exception;

use DomainException;
use Tilttek\Kudos\Refusal;

/**
 * Thanks that cannot be given, and why: to oneself, over the giver's daily cap, too often to the same member, or to a
 * member who does not take thanks.
 */
final class KudosRefused extends DomainException implements KudosException
{
    public function __construct(
        public readonly Refusal $refusal,
    ) {
        parent::__construct(match ($refusal) {
            Refusal::Self => 'A member cannot thank themselves.',
            Refusal::Declined => 'This member does not take thanks.',
            Refusal::DailyCap => 'The giver has given all the thanks of their day.',
            Refusal::PairLimit => 'The giver has thanked this member as often as the period allows.',
        });
    }

    public static function because(Refusal $refusal): self
    {
        return new self($refusal);
    }
}
