<?php

declare(strict_types=1);

namespace Tilttek\Bingo;

use Tilttek\Bingo\Exception\InvalidBingo;

/**
 * A square of the pool cards are drawn from: its identifier, the words shown, and, in a game ticked by activity, the
 * gesture that ticks it.
 */
final readonly class Square
{
    public const int MAX_LABEL = 200;

    public function __construct(
        public string $id,
        public string $label,
        public ?Trigger $trigger = null,
    ) {
        Identifier::check($id, 'square');

        if ('' === trim($label) || \strlen($label) > self::MAX_LABEL) {
            throw InvalidBingo::because(\sprintf('The label of the square %s is 1 to %d bytes of text.', $id, self::MAX_LABEL));
        }
    }
}
