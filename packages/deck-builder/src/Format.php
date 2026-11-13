<?php

declare(strict_types=1);

namespace Tilttek\DeckBuilder;

use DateTimeInterface;
use Tilttek\DeckBuilder\Exception\InvalidDeck;

/**
 * The declared rules of a format: the smallest and largest deck, the copies allowed of each card, the cards banned or
 * restricted, and the dated taboo lists that change them from a day on. The application gives its formats; the
 * library only applies them.
 */
final readonly class Format
{
    /**
     * The largest deck a format allows.
     */
    public const int MAX_SIZE = 100_000;

    /**
     * The most taboo lists of a format.
     */
    public const int MAX_TABOO_LISTS = 100;

    public string $code;

    public string $name;

    public int $minSize;

    /**
     * The largest deck; null for no limit.
     */
    public ?int $maxSize;

    /**
     * The copies allowed of each card that sets none itself.
     */
    public int $copies;

    /** @var list<string> */
    public array $banned;

    /** @var array<array-key, int> */
    public array $restricted;

    /**
     * The taboo lists, from the earliest to the latest.
     *
     * @var list<TabooList>
     */
    public array $tabooLists;

    /**
     * @param list<string>          $banned     the identifiers of the cards the format bans
     * @param array<array-key, int> $restricted the identifiers of the cards the format restricts, each with the copies
     *                                          allowed, 1 to 999
     * @param list<TabooList>       $tabooLists the dated taboo lists, in any order, each code and day once
     *
     * @throws InvalidDeck when a size, a number of copies, a card or a taboo list is refused
     */
    public function __construct(
        string $code,
        string $name,
        int $minSize = 1,
        ?int $maxSize = null,
        int $copies = Card::MAX_COPIES,
        array $banned = [],
        array $restricted = [],
        array $tabooLists = [],
    ) {
        $this->code = Identifiers::code($code, 'format');
        $this->name = Identifiers::name($name, 'format');

        if ($minSize < 0 || $minSize > self::MAX_SIZE) {
            throw InvalidDeck::because(\sprintf('The smallest deck of the format "%s" is from 0 to %d cards; %d is not.', $code, self::MAX_SIZE, $minSize));
        }

        if (null !== $maxSize && ($maxSize < max(1, $minSize) || $maxSize > self::MAX_SIZE)) {
            throw InvalidDeck::because(\sprintf('The largest deck of the format "%s" is from %d to %d cards; %d is not.', $code, max(1, $minSize), self::MAX_SIZE, $maxSize));
        }

        if ($copies < 1 || $copies > Card::MAX_COPIES) {
            throw InvalidDeck::because(\sprintf('The copies of a card in the format "%s" are from 1 to %d; %d is not.', $code, Card::MAX_COPIES, $copies));
        }

        if (\count($tabooLists) > self::MAX_TABOO_LISTS) {
            throw InvalidDeck::because(\sprintf('The format "%s" has at most %d taboo lists.', $code, self::MAX_TABOO_LISTS));
        }

        $this->minSize = $minSize;
        $this->maxSize = $maxSize;
        $this->copies = $copies;
        $this->banned = self::cardList($banned, 'banned', 'format "'.$code.'"');
        $this->restricted = self::restrictions($restricted, 'format "'.$code.'"');
        $this->tabooLists = $this->ordered($tabooLists, $code);
    }

    /**
     * @throws InvalidDeck when the array does not read as a format
     */
    public static function fromArray(mixed $data): self
    {
        $object = ArrayInput::object($data, 'format', ['code', 'name'], ['min_size', 'max_size', 'copies', 'banned', 'restricted', 'taboo_lists']);

        return new self(
            ArrayInput::string($object, 'code', 'format'),
            ArrayInput::string($object, 'name', 'format'),
            ArrayInput::int($object, 'min_size', 'format', 1),
            ArrayInput::nullableInt($object, 'max_size', 'format'),
            ArrayInput::int($object, 'copies', 'format', Card::MAX_COPIES),
            ArrayInput::strings($object, 'banned', 'format', TabooList::MAX_CARDS),
            ArrayInput::counts($object, 'restricted', 'format', TabooList::MAX_CARDS),
            array_map(TabooList::fromArray(...), ArrayInput::list($object, 'taboo_lists', 'format', self::MAX_TABOO_LISTS)),
        );
    }

    /**
     * @throws InvalidDeck when the text is not JSON or does not read as a format
     */
    public static function fromJson(string $json): self
    {
        return self::fromArray(ArrayInput::json($json, 'format'));
    }

    /**
     * The taboo list that applies on that day: the latest from that day or before, or none.
     */
    public function tabooListOn(DateTimeInterface $day): ?TabooList
    {
        $applying = null;

        foreach ($this->tabooLists as $list) {
            if ($list->appliesOn($day)) {
                $applying = $list;
            }
        }

        return $applying;
    }

    /**
     * The rules of the format on that day: its own lists and the taboo list that applies.
     */
    public function rulingOn(DateTimeInterface $day): Ruling
    {
        return new Ruling($this, $this->tabooListOn($day));
    }

    /**
     * @return array{code: string, name: string, min_size: int, max_size: ?int, copies: int, banned: list<string>, restricted: array<array-key, int>, taboo_lists: list<array{code: string, effective_from: string, banned: list<string>, restricted: array<array-key, int>}>}
     */
    public function toArray(): array
    {
        return [
            'code' => $this->code,
            'name' => $this->name,
            'min_size' => $this->minSize,
            'max_size' => $this->maxSize,
            'copies' => $this->copies,
            'banned' => $this->banned,
            'restricted' => $this->restricted,
            'taboo_lists' => array_map(static fn (TabooList $list): array => $list->toArray(), $this->tabooLists),
        ];
    }

    public function toJson(): string
    {
        return json_encode($this->toArray(), \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE);
    }

    public function equals(self $other): bool
    {
        return $this->toArray() === $other->toArray();
    }

    /**
     * Card identifiers, each once.
     *
     * @param list<string> $cards
     *
     * @return list<string>
     *
     * @throws InvalidDeck when an identifier is refused or listed twice
     *
     * @internal
     */
    public static function cardList(array $cards, string $list, string $owner): array
    {
        if (\count($cards) > TabooList::MAX_CARDS) {
            throw InvalidDeck::because(\sprintf('The %s bans at most %d cards.', $owner, TabooList::MAX_CARDS));
        }

        $seen = [];

        foreach ($cards as $card) {
            if (isset($seen[Identifiers::id($card, 'card')])) {
                throw InvalidDeck::because(\sprintf('The %s lists the card "%s" twice as %s.', $owner, $card, $list));
            }

            $seen[$card] = true;
        }

        return $cards;
    }

    /**
     * Card identifiers, each with the copies allowed.
     *
     * @param array<array-key, int> $restricted
     *
     * @return array<array-key, int>
     *
     * @throws InvalidDeck when an identifier or a number of copies is refused
     *
     * @internal
     */
    public static function restrictions(array $restricted, string $owner): array
    {
        if (\count($restricted) > TabooList::MAX_CARDS) {
            throw InvalidDeck::because(\sprintf('The %s restricts at most %d cards.', $owner, TabooList::MAX_CARDS));
        }

        $restrictions = [];

        foreach ($restricted as $card => $copies) {
            $card = Identifiers::id((string) $card, 'card');

            if ($copies < 1 || $copies > Card::MAX_COPIES) {
                throw InvalidDeck::because(\sprintf('The %s restricts the card "%s" to 1 to %d copies; %d is not.', $owner, $card, Card::MAX_COPIES, $copies));
            }

            $restrictions[$card] = $copies;
        }

        return $restrictions;
    }

    /**
     * @param list<TabooList> $lists
     *
     * @return list<TabooList>
     *
     * @throws InvalidDeck when two lists share a code or a day
     */
    private function ordered(array $lists, string $format): array
    {
        $codes = [];
        $days = [];

        foreach ($lists as $list) {
            $day = $list->effectiveFrom->format('Y-m-d');

            if (isset($codes[$list->code])) {
                throw InvalidDeck::because(\sprintf('The format "%s" has two taboo lists "%s".', $format, $list->code));
            }

            if (isset($days[$day])) {
                throw InvalidDeck::because(\sprintf('The format "%s" has two taboo lists from %s.', $format, $day));
            }

            $codes[$list->code] = true;
            $days[$day] = $list;
        }

        ksort($days, \SORT_STRING);

        return array_values($days);
    }
}
