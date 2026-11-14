<?php

declare(strict_types=1);

namespace Tilttek\Quiz\Question;

use Tilttek\Quiz\Exception\InvalidQuiz;
use Tilttek\Quiz\Identifier;

/**
 * A choice of a question, or an item of an ordering question: its identifier and the words shown.
 */
final readonly class Choice
{
    public const int MAX_LABEL = 200;

    public function __construct(
        public string $id,
        public string $label,
    ) {
        Identifier::check($id, 'choice');

        if ('' === trim($label) || \strlen($label) > self::MAX_LABEL) {
            throw InvalidQuiz::because(\sprintf('The label of the choice %s is 1 to %d bytes of text.', $id, self::MAX_LABEL));
        }
    }
}
