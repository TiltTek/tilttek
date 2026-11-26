<?php

declare(strict_types=1);

namespace Tilttek\CardDeck\Gesture;

use Tilttek\CardDeck\CardId;
use Tilttek\CardDeck\Exception\InvalidDefinition;
use Tilttek\CardDeck\PlayerId;
use Tilttek\CardDeck\ZoneId;

/**
 * Reads back the gestures `Gesture::toArray()` writes, from a store or a published game: every field checked, a
 * refusal for anything else.
 */
final class GestureCodec
{
    /**
     * @throws InvalidDefinition when the data is not a gesture `toArray()` could have written
     */
    public static function fromArray(mixed $data): Gesture
    {
        if (!\is_array($data)) {
            throw InvalidDefinition::because('A gesture is an array.');
        }

        return match ($data['gesture'] ?? null) {
            'shuffle' => new Shuffle(self::zone($data, 'zone')),
            'draw' => new Draw(new PlayerId(self::string($data, 'player')), Pick::tryFrom(self::string($data, 'from')) ?? throw self::invalid('from'), self::zone($data, 'zone'), self::bool($data, 'face_up')),
            'deal' => new Deal(self::players($data), self::int($data, 'count'), self::bool($data, 'face_up'), self::zone($data, 'from')),
            'discard' => new Discard(new CardId(self::string($data, 'card'))),
            'flip' => new Flip(new CardId(self::string($data, 'card'))),
            'turn_face' => new TurnFace(new CardId(self::string($data, 'card')), self::int($data, 'face')),
            'move' => new Move(new CardId(self::string($data, 'card')), self::zone($data, 'to'), self::end($data), self::bool($data, 'face_up')),
            'collect' => new Collect(self::zone($data, 'from'), self::zone($data, 'to'), self::end($data), self::bool($data, 'face_up')),
            default => throw InvalidDefinition::because('A gesture is shuffle, draw, deal, discard, flip, turn_face, move or collect.'),
        };
    }

    /**
     * @param array<mixed> $data
     */
    private static function string(array $data, string $field): string
    {
        $value = $data[$field] ?? null;

        return \is_string($value) ? $value : throw self::invalid($field);
    }

    /**
     * @param array<mixed> $data
     */
    private static function int(array $data, string $field): int
    {
        $value = $data[$field] ?? null;

        return \is_int($value) ? $value : throw self::invalid($field);
    }

    /**
     * @param array<mixed> $data
     */
    private static function bool(array $data, string $field): bool
    {
        $value = $data[$field] ?? null;

        return \is_bool($value) ? $value : throw self::invalid($field);
    }

    /**
     * @param array<mixed> $data
     */
    private static function zone(array $data, string $field): ZoneId
    {
        return ZoneId::fromString(self::string($data, $field));
    }

    /**
     * @param array<mixed> $data
     */
    private static function end(array $data): End
    {
        return End::tryFrom(self::string($data, 'at')) ?? throw self::invalid('at');
    }

    /**
     * @param array<mixed> $data
     *
     * @return list<PlayerId>
     */
    private static function players(array $data): array
    {
        $players = $data['players'] ?? null;

        if (!\is_array($players) || !array_is_list($players)) {
            throw self::invalid('players');
        }

        return array_map(static fn (mixed $player): PlayerId => \is_string($player) ? new PlayerId($player) : throw self::invalid('players'), $players);
    }

    private static function invalid(string $field): InvalidDefinition
    {
        return InvalidDefinition::because(\sprintf('The field "%s" of the gesture is missing or of the wrong type.', $field));
    }
}
