<?php

declare(strict_types=1);

namespace Tilttek\Sets;

use Tilttek\Sets\Exception\InvalidSet;

/**
 * A reward a set or a tier declares, never grants: its kind — `points`, `badge`, `item`, or any the application
 * branches on —, an opaque reference in that kind, and a quantity. The application grants it, through the packages it
 * has, when it hears that the set is completed or the tier reached.
 */
final readonly class Reward
{
    public const int MAX_REFERENCE_LENGTH = 255;

    public const int MAX_QUANTITY = 1_000_000_000;

    /**
     * @throws InvalidSet when the kind, the reference or the quantity is refused
     */
    public function __construct(
        public string $kind,
        public string $reference,
        public int $quantity = 1,
    ) {
        Identifiers::code($kind, 'reward kind');

        if ('' === $reference || \strlen($reference) > self::MAX_REFERENCE_LENGTH || 1 === preg_match('/[\s\x00-\x1F\x7F]/', $reference)) {
            throw InvalidSet::because(\sprintf('The reference of a reward is 1 to %d bytes without whitespace or control characters.', self::MAX_REFERENCE_LENGTH));
        }

        if ($quantity < 1 || $quantity > self::MAX_QUANTITY) {
            throw InvalidSet::because(\sprintf('A reward gives 1 to %d; this one gives %d.', self::MAX_QUANTITY, $quantity));
        }
    }

    /**
     * @throws InvalidSet when the value is not a reward {@see self::toArray()} wrote
     */
    public static function fromArray(mixed $data): self
    {
        $object = ArrayInput::object($data, 'reward', ['kind', 'reference'], ['quantity']);

        return new self(
            ArrayInput::string($object, 'kind', 'reward'),
            ArrayInput::string($object, 'reference', 'reward'),
            ArrayInput::int($object, 'quantity', 'reward', 1),
        );
    }

    /**
     * @return array{kind: string, reference: string, quantity: int}
     */
    public function toArray(): array
    {
        return ['kind' => $this->kind, 'reference' => $this->reference, 'quantity' => $this->quantity];
    }
}
