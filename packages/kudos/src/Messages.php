<?php

declare(strict_types=1);

namespace Tilttek\Kudos;

use Tilttek\Kudos\Exception\InvalidKudos;
use Tilttek\Kudos\Exception\UnknownMessage;

/**
 * The closed list of messages thanks are given with, in the order a member sees them. The library gives a default
 * list; an application adds its own messages, replaces a text, or takes some out. It is immutable: each method returns
 * a new list.
 */
final readonly class Messages
{
    public const int MAX_MESSAGES = 100;

    /**
     * @var array<string, Message>
     */
    private array $messages;

    public function __construct(Message ...$messages)
    {
        if (\count($messages) > self::MAX_MESSAGES) {
            throw InvalidKudos::because(\sprintf('A list holds at most %d messages; this one holds %d.', self::MAX_MESSAGES, \count($messages)));
        }

        $byName = [];

        foreach ($messages as $message) {
            if (isset($byName[$message->name])) {
                throw InvalidKudos::because(\sprintf('Two messages are named "%s".', $message->name));
            }

            $byName[$message->name] = $message;
        }

        $this->messages = $byName;
    }

    /**
     * The library's messages: help, well played, great idea, welcome, good game and great session.
     */
    public static function defaults(): self
    {
        return new self(
            new Message('help', 'Thanks for your help'),
            new Message('well_played', 'Well played'),
            new Message('great_idea', 'Great idea'),
            new Message('welcome', 'Welcome to the table'),
            new Message('good_game', 'Good game'),
            new Message('great_session', 'Great session'),
        );
    }

    /**
     * The same list with these messages: a message of a name already listed takes its place, a new one comes last.
     */
    public function with(Message ...$messages): self
    {
        $all = $this->messages;

        foreach ($messages as $message) {
            $all[$message->name] = $message;
        }

        return new self(...$all);
    }

    /**
     * The same list without the messages of these names.
     */
    public function without(string ...$names): self
    {
        return new self(...array_diff_key($this->messages, array_flip($names)));
    }

    public function has(string $name): bool
    {
        return isset($this->messages[$name]);
    }

    /**
     * @throws UnknownMessage when the list holds no message of this name
     */
    public function get(string $name): Message
    {
        return $this->messages[$name] ?? throw UnknownMessage::named($name);
    }

    /**
     * @return list<Message>
     */
    public function all(): array
    {
        return array_values($this->messages);
    }
}
