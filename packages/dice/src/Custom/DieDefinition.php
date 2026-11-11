<?php

declare(strict_types=1);

namespace Tilttek\Dice\Custom;

use Tilttek\Dice\Exception\InvalidDefinition;

/**
 * A die the application declares: a name and at least two faces, with distinct identifiers, that either all carry a
 * value or none does.
 */
final readonly class DieDefinition
{
    public const string NAME_PATTERN = '/^[a-z][a-z0-9_-]{0,31}$/D';

    /**
     * @var non-empty-list<Face>
     */
    public array $faces;

    private bool $numeric;

    public function __construct(
        public string $name,
        Face ...$faces,
    ) {
        if (1 !== preg_match(self::NAME_PATTERN, $name)) {
            throw InvalidDefinition::because(\sprintf('A die name is 1 to 32 lower-case letters, digits, "_" or "-", starting with a letter; "%s" is not.', $name));
        }

        $faces = array_values($faces);

        if (\count($faces) < 2) {
            throw InvalidDefinition::because(\sprintf('The die "%s" needs at least two faces.', $name));
        }

        $ids = array_map(static fn (Face $face): string => $face->id, $faces);

        if (\count(array_unique($ids)) !== \count($ids)) {
            throw InvalidDefinition::because(\sprintf('Two faces of the die "%s" share an identifier.', $name));
        }

        $valued = \count(array_filter($faces, static fn (Face $face): bool => null !== $face->value));

        if (0 !== $valued && \count($faces) !== $valued) {
            throw InvalidDefinition::because(\sprintf('Either every face of the die "%s" carries a value, or none does.', $name));
        }

        $this->faces = $faces;
        $this->numeric = 0 !== $valued;
    }

    public function numeric(): bool
    {
        return $this->numeric;
    }
}
