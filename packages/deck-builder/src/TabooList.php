<?php

declare(strict_types=1);

namespace Tilttek\DeckBuilder;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Tilttek\DeckBuilder\Exception\InvalidDeck;

/**
 * A dated list of cards a format bans or restricts from a day on, replacing the taboo list before it: the format's
 * own lists stay, and this one adds to them.
 */
final readonly class TabooList
{
    /**
     * The most cards a list bans, and the most it restricts.
     */
    public const int MAX_CARDS = 10_000;

    public string $code;

    /**
     * The first day the list applies, at midnight UTC.
     */
    public DateTimeImmutable $effectiveFrom;

    /** @var list<string> */
    public array $banned;

    /** @var array<array-key, int> */
    public array $restricted;

    /**
     * @param list<string>          $banned     the identifiers of the cards banned
     * @param array<array-key, int> $restricted the identifiers of the cards restricted, each with the copies allowed, 1
     *                                          to 999, in place of the format's restriction of the same card
     *
     * @throws InvalidDeck when the code, a card or a number of copies is refused
     */
    public function __construct(string $code, DateTimeInterface $effectiveFrom, array $banned = [], array $restricted = [])
    {
        $this->code = Identifiers::code($code, 'taboo list');
        $this->effectiveFrom = $this->day($effectiveFrom);
        $this->banned = Format::cardList($banned, 'banned', 'taboo list "'.$code.'"');
        $this->restricted = Format::restrictions($restricted, 'taboo list "'.$code.'"');
    }

    /**
     * @throws InvalidDeck when the array does not read as a taboo list
     */
    public static function fromArray(mixed $data): self
    {
        $object = ArrayInput::object($data, 'taboo list', ['code', 'effective_from'], ['banned', 'restricted']);
        $date = ArrayInput::string($object, 'effective_from', 'taboo list');
        $day = DateTimeImmutable::createFromFormat('!Y-m-d', $date, new DateTimeZone('UTC'));

        if (false === $day || $day->format('Y-m-d') !== $date) {
            throw InvalidDeck::because(\sprintf('The "effective_from" of a taboo list is a day such as 2026-11-01; "%s" is not.', substr($date, 0, 32)));
        }

        return new self(
            ArrayInput::string($object, 'code', 'taboo list'),
            $day,
            ArrayInput::strings($object, 'banned', 'taboo list', self::MAX_CARDS),
            ArrayInput::counts($object, 'restricted', 'taboo list', self::MAX_CARDS),
        );
    }

    /**
     * Whether the list applies on that day, in the day's own time zone.
     */
    public function appliesOn(DateTimeInterface $day): bool
    {
        return strcmp($this->effectiveFrom->format('Y-m-d'), $day->format('Y-m-d')) <= 0;
    }

    /**
     * @return array{code: string, effective_from: string, banned: list<string>, restricted: array<array-key, int>}
     */
    public function toArray(): array
    {
        return [
            'code' => $this->code,
            'effective_from' => $this->effectiveFrom->format('Y-m-d'),
            'banned' => $this->banned,
            'restricted' => $this->restricted,
        ];
    }

    /**
     * The day of a moment, in the moment's own time zone, at midnight UTC.
     */
    private function day(DateTimeInterface $moment): DateTimeImmutable
    {
        return new DateTimeImmutable($moment->format('Y-m-d'), new DateTimeZone('UTC'));
    }
}
