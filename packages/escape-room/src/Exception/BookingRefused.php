<?php

declare(strict_types=1);

namespace Tilttek\EscapeRoom\Exception;

use DomainException;
use Tilttek\EscapeRoom\Booking\BookingRefusal;

/**
 * A booking of a session refused, with its reason. Nothing is reserved when one is refused.
 */
final class BookingRefused extends DomainException implements EscapeRoomException
{
    public function __construct(
        public readonly BookingRefusal $reason,
        string $message,
    ) {
        parent::__construct($message);
    }

    public static function because(BookingRefusal $reason, string $session): self
    {
        return new self($reason, \sprintf('The session "%s" cannot be booked: %s', $session, $reason->explain()));
    }
}
