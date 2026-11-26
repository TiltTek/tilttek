<?php

declare(strict_types=1);

namespace Tilttek\MathTrade;

use JsonException;
use Tilttek\MathTrade\Contract\Wish;
use Tilttek\MathTrade\Exception\InvalidTradeList;
use Tilttek\MathTrade\Exception\LimitExceeded;

/**
 * Writes a trade list as JSON, and reads it back, on the format of doc/json-format.md:
 *
 *     {"format": "tilttek/math-trade", "version": 1, "participants": [
 *         {"id": "alice", "offers": [{"id": "a1", "game": "azul", "wants": ["b1", ["c1", "c2"]]}]}
 *     ]}
 *
 * A wish is the identifier of an offer, or a list of two identifiers or more for a group. The reader refuses any other
 * key, type or version, and a document longer than the limits allow, before decoding it.
 */
final class TradeListJson
{
    public const string FORMAT = 'tilttek/math-trade';

    public const int VERSION = 1;

    /**
     * The nesting of the format: document, participants, participant, offers, offer, wishes, group.
     */
    private const int DEPTH = 8;

    public function encode(TradeList $list): string
    {
        $participants = [];

        foreach ($list->participants as $participant) {
            $offers = [];

            foreach ($participant->offers as $offer) {
                $entry = ['id' => $offer->id];

                if (null !== $offer->game) {
                    $entry['game'] = $offer->game;
                }

                $entry['wants'] = array_map(
                    static fn (Wish $wish): string|array => $wish instanceof Want ? $wish->offer : $wish->offers(),
                    $offer->wants,
                );
                $offers[] = $entry;
            }

            $participants[] = ['id' => $participant->id, 'offers' => $offers];
        }

        return json_encode(
            ['format' => self::FORMAT, 'version' => self::VERSION, 'participants' => $participants],
            \JSON_THROW_ON_ERROR | \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE,
        );
    }

    /**
     * @throws InvalidTradeList when the document is not a trade list of this format
     * @throws LimitExceeded    when the document or the list is larger than the limits allow
     */
    public function decode(string $json, Limits $limits = new Limits()): TradeList
    {
        if (\strlen($json) > $limits->jsonBytes) {
            throw LimitExceeded::because(\sprintf('The JSON document is longer than %d bytes.', $limits->jsonBytes));
        }

        try {
            $document = json_decode($json, true, self::DEPTH, \JSON_THROW_ON_ERROR);
        } catch (JsonException $jsonException) {
            throw InvalidTradeList::because('The trade list is not a JSON document of its format: '.$jsonException->getMessage().'.');
        }

        $document = $this->object($document, ['format', 'version', 'participants'], 'The document');

        if (self::FORMAT !== $document['format'] || self::VERSION !== $document['version']) {
            throw InvalidTradeList::because(\sprintf('The document is not a trade list of the format "%s", version %d.', self::FORMAT, self::VERSION));
        }

        return new TradeList(
            array_map($this->participant(...), $this->list($document['participants'], 'The list of participants')),
            $limits,
        );
    }

    private function participant(mixed $value): Participant
    {
        $participant = $this->object($value, ['id', 'offers'], 'A participant');

        return new Participant(
            self::string($participant['id'], 'The identifier of a participant'),
            array_map($this->offer(...), $this->list($participant['offers'], 'The list of offers of a participant')),
        );
    }

    private function offer(mixed $value): Offer
    {
        $offer = $this->object($value, ['id', 'game', 'wants'], 'An offer', ['game']);
        $game = $offer['game'] ?? null;

        return new Offer(
            self::string($offer['id'], 'The identifier of an offer'),
            array_map($this->wish(...), $this->list($offer['wants'], 'The list of wishes of an offer')),
            null === $game ? null : self::string($game, 'The game of an offer'),
        );
    }

    private function wish(mixed $value): Wish
    {
        if (\is_string($value)) {
            return new Want($value);
        }

        if (!\is_array($value) || !array_is_list($value)) {
            throw InvalidTradeList::because('A wish is the identifier of an offer, or a JSON array of identifiers for a group.');
        }

        return new WantGroup(array_map(
            static fn (mixed $offer): string => self::string($offer, 'An offer of a group of wishes'),
            $value,
        ));
    }

    /**
     * @param list<string> $keys     the keys the object holds
     * @param list<string> $optional the keys it may leave out
     *
     * @return array<string, mixed>
     */
    private function object(mixed $value, array $keys, string $what, array $optional = []): array
    {
        if (!\is_array($value) || ([] !== $value && array_is_list($value))) {
            throw InvalidTradeList::because($what.' is a JSON object.');
        }

        $unknown = array_diff(array_map(strval(...), array_keys($value)), $keys);

        if ([] !== $unknown) {
            throw InvalidTradeList::because(\sprintf('%s holds the unknown key "%s".', $what, reset($unknown)));
        }

        $missing = array_diff($keys, $optional, array_keys($value));

        if ([] !== $missing) {
            throw InvalidTradeList::because(\sprintf('%s lacks the key "%s".', $what, reset($missing)));
        }

        /** @var array<string, mixed> $value the keys are those of the format */
        return $value;
    }

    /**
     * @return list<mixed>
     */
    private function list(mixed $value, string $what): array
    {
        if (!\is_array($value) || !array_is_list($value)) {
            throw InvalidTradeList::because($what.' is a JSON array.');
        }

        return $value;
    }

    private static function string(mixed $value, string $what): string
    {
        if (!\is_string($value)) {
            throw InvalidTradeList::because($what.' is a JSON string.');
        }

        return $value;
    }
}
