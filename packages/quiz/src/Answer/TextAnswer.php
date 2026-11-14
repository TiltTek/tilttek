<?php

declare(strict_types=1);

namespace Tilttek\Quiz\Answer;

use Tilttek\Quiz\Contract\Answer;
use Tilttek\Quiz\Exception\InvalidQuiz;

/**
 * The words a player types for a typed-answer question, at most `MAX_LENGTH` bytes of UTF-8.
 */
final readonly class TextAnswer implements Answer
{
    public const int MAX_LENGTH = 200;

    public function __construct(
        public string $text,
    ) {
        if (\strlen($text) > self::MAX_LENGTH) {
            throw InvalidQuiz::because(\sprintf('A typed answer is at most %d bytes; this one is %d.', self::MAX_LENGTH, \strlen($text)));
        }

        if (!mb_check_encoding($text, 'UTF-8')) {
            throw InvalidQuiz::because('A typed answer is text in UTF-8.');
        }
    }
}
