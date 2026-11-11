<?php

declare(strict_types=1);

namespace Tilttek\Dice\Custom;

use Tilttek\Dice\Exception\InvalidDefinition;

/**
 * One face of a custom die: an identifier, a label — a translation key or a text, the words a screen reader says —,
 * and how it is shown: a colour, a shape, an image, and a value when the face counts.
 *
 * A face is never told by its colour alone: a face with a colour also has a shape or an image.
 */
final readonly class Face
{
    public const string ID_PATTERN = '/^[a-z0-9][a-z0-9_-]{0,31}$/D';

    public function __construct(
        public string $id,
        public string $label,
        public ?int $value = null,
        public ?string $color = null,
        public ?Shape $shape = null,
        public ?string $image = null,
    ) {
        if (1 !== preg_match(self::ID_PATTERN, $id)) {
            throw InvalidDefinition::because(\sprintf('A face identifier is 1 to 32 lower-case letters, digits, "_" or "-"; "%s" is not.', $id));
        }

        if ('' === trim($label)) {
            throw InvalidDefinition::because(\sprintf('The face "%s" has no label: a screen reader would have nothing to say.', $id));
        }

        if (null !== $color && 1 !== preg_match('/^#[0-9a-fA-F]{6}$/D', $color)) {
            throw InvalidDefinition::because(\sprintf('The colour of the face "%s" is written #rrggbb; "%s" is not.', $id, $color));
        }

        if ('' === $image) {
            throw InvalidDefinition::because(\sprintf('The image of the face "%s" is empty.', $id));
        }

        if (null !== $color && !$shape instanceof Shape && null === $image) {
            throw InvalidDefinition::because(\sprintf('The face "%s" is told by its colour alone: give it a shape or an image too.', $id));
        }
    }
}
