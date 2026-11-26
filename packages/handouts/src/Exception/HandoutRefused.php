<?php

declare(strict_types=1);

namespace Tilttek\Handouts\Exception;

use DomainException;
use Tilttek\Handouts\Refusal;

/**
 * An act the rules of handouts refuse, and why.
 */
final class HandoutRefused extends DomainException implements HandoutException
{
    public function __construct(
        public readonly Refusal $refusal,
    ) {
        parent::__construct(match ($refusal) {
            Refusal::AlreadyCreated => 'A handout already holds this reference.',
            Refusal::TooManyHandouts => 'The table holds as many handouts as allowed.',
            Refusal::TooManyAttachments => 'The handout holds more attachments than allowed.',
            Refusal::MediaTypeNotAllowed => 'An attachment has a media type the application does not allow.',
            Refusal::AudienceTooLarge => 'The audience names more players than allowed.',
            Refusal::TooManyRevealed => 'The handout is revealed to as many audiences as allowed.',
            Refusal::TooManyStaged => 'The handout holds as many staged reveals as allowed.',
            Refusal::NotRevealed => 'The handout is not revealed to this player.',
        });
    }

    public static function because(Refusal $refusal): self
    {
        return new self($refusal);
    }
}
