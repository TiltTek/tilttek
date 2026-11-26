<?php

declare(strict_types=1);

namespace Tilttek\Kudos;

use Tilttek\Kudos\Exception\InvalidKudos;

/**
 * One of the fixed messages thanks are given with — `help`, *Thanks for your help* —: a name kept with the thanks, a
 * translation key and the English text it stands for. The member picks a message; they never type one.
 *
 * The name is 1 to 64 lower-case letters, digits and "_", starting with a letter; the text 1 to 200 bytes.
 */
final readonly class Message
{
    public const int MAX_NAME_LENGTH = 64;

    public const int MAX_TEXT_LENGTH = 200;

    /**
     * What a translation key starts with, before the message's name.
     */
    public const string TRANSLATION_PREFIX = 'kudos.message.';

    public function __construct(
        public string $name,
        public string $text,
    ) {
        if (1 !== preg_match('/^[a-z][a-z0-9_]{0,63}$/D', $name)) {
            throw InvalidKudos::because(\sprintf('"%s" is not a message name: 1 to %d lower-case letters, digits and "_", starting with a letter.', $name, self::MAX_NAME_LENGTH));
        }

        if ('' === $text || \strlen($text) > self::MAX_TEXT_LENGTH) {
            throw InvalidKudos::because(\sprintf('The text of the message "%s" is 1 to %d bytes; it is %d.', $name, self::MAX_TEXT_LENGTH, \strlen($text)));
        }
    }

    /**
     * The key an application translates the message with: `kudos.message.help`.
     */
    public function translationKey(): string
    {
        return self::TRANSLATION_PREFIX.$this->name;
    }
}
