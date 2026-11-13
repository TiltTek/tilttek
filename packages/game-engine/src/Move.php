<?php

declare(strict_types=1);

namespace Tilttek\GameEngine;

use JsonException;
use Tilttek\GameEngine\Exception\InvalidGame;

/**
 * A move made at a table: its type, the seat that makes it — or none, for the application acting for the table, a
 * timer that ends a turn —, and its payload, plain values that JSON writes.
 *
 * The engine's own moves are `engine.end-turn`, `engine.transition` (`{transition}`), `engine.initiative`
 * (`{scores: {seat: int}}`) and `engine.delay` (`{after: seat}`); every other type is the game's.
 */
final readonly class Move
{
    public const string END_TURN = 'engine.end-turn';

    public const string TRANSITION = 'engine.transition';

    public const string INITIATIVE = 'engine.initiative';

    public const string DELAY = 'engine.delay';

    /** The longest payload, written as JSON, in bytes. */
    public const int MAX_PAYLOAD = 16_384;

    /**
     * @param array<string, mixed> $payload plain values — texts, numbers, booleans, null and arrays of them —, 8 levels
     *                                      deep and 16 384 bytes of JSON at most
     *
     * @throws InvalidGame
     */
    public function __construct(
        public string $type,
        public ?string $seat = null,
        public array $payload = [],
    ) {
        Name::type($type);

        if (null !== $seat) {
            Name::identifier($seat, 'a seat');
        }

        try {
            $json = json_encode($payload, \JSON_THROW_ON_ERROR | \JSON_PRESERVE_ZERO_FRACTION, 8);
        } catch (JsonException $jsonException) {
            throw InvalidGame::because(\sprintf('The payload of the move "%s" is not plain values 8 levels deep: %s', $type, $jsonException->getMessage()), $jsonException);
        }

        if (\strlen($json) > self::MAX_PAYLOAD) {
            throw InvalidGame::because(\sprintf('The payload of the move "%s" is longer than %d bytes.', $type, self::MAX_PAYLOAD));
        }
    }

    public static function endTurn(?string $seat): self
    {
        return new self(self::END_TURN, $seat);
    }

    public static function transition(string $transition, ?string $seat = null): self
    {
        return new self(self::TRANSITION, $seat, ['transition' => $transition]);
    }

    /**
     * @param array<string, int> $scores the initiative of each seat
     */
    public static function initiative(array $scores, ?string $seat = null): self
    {
        return new self(self::INITIATIVE, $seat, ['scores' => $scores]);
    }

    public static function delay(string $seat, string $after): self
    {
        return new self(self::DELAY, $seat, ['after' => $after]);
    }

    public function isEngine(): bool
    {
        return str_starts_with($this->type, 'engine.');
    }

    /**
     * A text of the payload, or null.
     */
    public function text(string $name): ?string
    {
        $value = $this->payload[$name] ?? null;

        return \is_string($value) ? $value : null;
    }

    /**
     * An integer of the payload, or null.
     */
    public function int(string $name): ?int
    {
        $value = $this->payload[$name] ?? null;

        return \is_int($value) ? $value : null;
    }
}
