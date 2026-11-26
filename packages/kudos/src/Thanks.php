<?php

declare(strict_types=1);

namespace Tilttek\Kudos;

use DateTimeImmutable;
use Phil\Resource\ResourceReference;
use Tilttek\Activity\Activity;
use Tilttek\Kudos\Exception\InvalidKudos;

/**
 * Thanks as their giver or their receiver reads them: the key they were given under, who gave them, to whom, with which
 * message, about what, and when. The receiver is null once that member is erased; the message is its name, read with
 * the application's list of messages.
 */
final readonly class Thanks
{
    public function __construct(
        public string $key,
        public ResourceReference $giver,
        public ?ResourceReference $receiver,
        public string $message,
        public ?ResourceReference $object,
        public DateTimeImmutable $givenAt,
    ) {
    }

    /**
     * The thanks an activity of the journal records.
     *
     * @throws InvalidKudos when the activity holds no message, and so records no thanks
     */
    public static function fromActivity(Activity $activity): self
    {
        $message = $activity->context->get(Kudos::CONTEXT_MESSAGE);
        $object = $activity->context->get(Kudos::CONTEXT_OBJECT);

        if (!\is_string($message)) {
            throw InvalidKudos::because(\sprintf('The activity "%s" holds no message: it records no thanks.', $activity->key->value));
        }

        return new self(
            $activity->key->value,
            $activity->member,
            $activity->object,
            $message,
            \is_string($object) ? ResourceReference::fromString($object) : null,
            $activity->occurredAt,
        );
    }
}
