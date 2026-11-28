<?php

declare(strict_types=1);

namespace Tilttek\GameCatalog;

use Tilttek\GameCatalog\Exception\InvalidEntry;
use Tilttek\GameCatalog\Internal\Identifier;
use Tilttek\GameCatalog\Internal\Text;

/**
 * What an entry says of a game, as its publisher states it: the values a member corrects, field by field. The votes of
 * the members — the best numbers of players, the weight, the age, the dependence on language — are kept apart, in the
 * ballots, and shown beside these values, never in their place.
 */
final readonly class Details
{
    public const int MAX_NAME = 200;

    public const int MAX_PEOPLE = 20;

    public const int MAX_PERSON = 120;

    public const int MAX_LANGUAGES = 50;

    public const int MAX_DESCRIPTION = 4_000;

    public const int MAX_PLAYERS = 999;

    public const int MAX_AGE = 99;

    public const int MAX_LEVEL = 1_000;

    public const int MAX_YEAR = 9_999;

    private const string LANGUAGE = '/^[A-Za-z]{2,3}(?:-[A-Za-z0-9]{1,8}){0,4}$/D';

    public string $name;

    /** @var list<string> */
    public array $publishers;

    /** @var list<string> */
    public array $designers;

    public ?string $description;

    /**
     * @param ?string      $system     the key of the game system, for a role-playing game, as the application's
     *                                 list of systems names it
     * @param list<string> $publishers in the order of the box, at most 20
     * @param list<string> $designers  in the order of the box, at most 20
     * @param ?int         $year       the year of publication, 1 to 9999
     * @param ?Range       $players    the numbers of players the publisher states, 1 to 999
     * @param ?Range       $playtime   the minutes of a game the publisher states
     * @param ?int         $minimumAge the age the publisher states, 0 to 99
     * @param list<string> $languages  the BCP 47 tags of the languages of this edition, at most 50
     * @param ?Range       $levels     the levels of the characters a scenario is written for, 0 to 1000
     * @param ?string      $cover      an identifier of the cover image, which the application keeps
     *
     * @throws InvalidEntry when a value is out of its shape or its bounds
     */
    public function __construct(
        string $name,
        public EntryType $type = EntryType::BaseGame,
        public GameKind $kind = GameKind::Board,
        public ?string $system = null,
        array $publishers = [],
        array $designers = [],
        public ?int $year = null,
        public ?Range $players = null,
        public ?Range $playtime = null,
        public ?int $minimumAge = null,
        public array $languages = [],
        public ?Range $levels = null,
        public ?LanguageDependence $languageDependence = null,
        ?string $description = null,
        public ?string $cover = null,
    ) {
        $this->name = Text::line($name, self::MAX_NAME, 'name of a game');
        $this->publishers = $this->people($publishers, 'publisher');
        $this->designers = $this->people($designers, 'designer');
        $this->description = null === $description ? null : Text::lines($description, self::MAX_DESCRIPTION, 'description of a game');

        if (null !== $system) {
            Identifier::check($system, 'a game system');
        }

        if (null !== $cover) {
            Identifier::check($cover, 'a cover');
        }

        if (null !== $year && ($year < 1 || $year > self::MAX_YEAR)) {
            throw InvalidEntry::because(\sprintf('The year of publication is from 1 to %d.', self::MAX_YEAR));
        }

        if (null !== $minimumAge && ($minimumAge < 0 || $minimumAge > self::MAX_AGE)) {
            throw InvalidEntry::because(\sprintf('The minimum age is from 0 to %d.', self::MAX_AGE));
        }

        $players?->within(1, self::MAX_PLAYERS, 'numbers of players');
        $playtime?->within(1, Range::MAX, 'minutes of a game');
        $levels?->within(0, self::MAX_LEVEL, 'levels of a scenario');
        $this->checkLanguages($languages);
    }

    /**
     * The value of a field, in a shape that compares with `===`: a range as its two ends, an enumeration as its
     * value.
     *
     * @return string|int|list<string>|list<int>|null
     */
    public function value(Field $field): string|int|array|null
    {
        return match ($field) {
            Field::Name => $this->name,
            Field::Type => $this->type->value,
            Field::Kind => $this->kind->value,
            Field::System => $this->system,
            Field::Publishers => $this->publishers,
            Field::Designers => $this->designers,
            Field::Year => $this->year,
            Field::Players => $this->ends($this->players),
            Field::Playtime => $this->ends($this->playtime),
            Field::MinimumAge => $this->minimumAge,
            Field::Languages => $this->languages,
            Field::Levels => $this->ends($this->levels),
            Field::LanguageDependence => $this->languageDependence?->value,
            Field::Description => $this->description,
            Field::Cover => $this->cover,
        };
    }

    /**
     * The fields whose value differs in the other details, in the order of `Field`.
     *
     * @return list<Field>
     */
    public function differences(self $other): array
    {
        return array_values(array_filter(
            Field::cases(),
            fn (Field $field): bool => $this->value($field) !== $other->value($field),
        ));
    }

    /**
     * These details with the given fields taken from the other details.
     *
     * @param list<Field> $fields
     */
    public function merge(self $from, array $fields): self
    {
        $pick = fn (Field $field): self => \in_array($field, $fields, true) ? $from : $this;

        return new self(
            name: $pick(Field::Name)->name,
            type: $pick(Field::Type)->type,
            kind: $pick(Field::Kind)->kind,
            system: $pick(Field::System)->system,
            publishers: $pick(Field::Publishers)->publishers,
            designers: $pick(Field::Designers)->designers,
            year: $pick(Field::Year)->year,
            players: $pick(Field::Players)->players,
            playtime: $pick(Field::Playtime)->playtime,
            minimumAge: $pick(Field::MinimumAge)->minimumAge,
            languages: $pick(Field::Languages)->languages,
            levels: $pick(Field::Levels)->levels,
            languageDependence: $pick(Field::LanguageDependence)->languageDependence,
            description: $pick(Field::Description)->description,
            cover: $pick(Field::Cover)->cover,
        );
    }

    /**
     * @param list<string> $names
     *
     * @return list<string>
     */
    private function people(array $names, string $what): array
    {
        if (\count($names) > self::MAX_PEOPLE) {
            throw InvalidEntry::because(\sprintf('An entry names at most %d of each: publishers, designers.', self::MAX_PEOPLE));
        }

        $people = array_map(static fn (string $name): string => Text::line($name, self::MAX_PERSON, 'name of a '.$what), $names);

        if (\count(array_unique($people)) !== \count($people)) {
            throw InvalidEntry::because(\sprintf('An entry names each %s once.', $what));
        }

        return $people;
    }

    /**
     * @param list<string> $languages
     */
    private function checkLanguages(array $languages): void
    {
        if (\count($languages) > self::MAX_LANGUAGES) {
            throw InvalidEntry::because(\sprintf('An entry names at most %d languages.', self::MAX_LANGUAGES));
        }

        foreach ($languages as $language) {
            if (1 !== preg_match(self::LANGUAGE, $language)) {
                throw InvalidEntry::because('A language is a BCP 47 tag, as "fr" or "fr-CA".');
            }
        }

        if (\count(array_unique($languages)) !== \count($languages)) {
            throw InvalidEntry::because('An entry names each language once.');
        }
    }

    /**
     * @return ?list<int>
     */
    private function ends(?Range $range): ?array
    {
        return $range instanceof Range ? [$range->min, $range->max] : null;
    }
}
