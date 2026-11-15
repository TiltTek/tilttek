<?php

declare(strict_types=1);

namespace Tilttek\LuckTokens;

use JsonException;
use Phil\Resource\InvalidResource;
use Phil\Resource\ResourceReference;
use Tilttek\LuckTokens\Exception\InvalidTokenRule;
use Tilttek\LuckTokens\Exception\TokenRefused;

/**
 * The tokens of a table: the kinds it hands out and its game masters. Read from plain values or from JSON — a form of
 * the table's settings —, as `toArray()` writes them:
 *
 *     {"kinds": [{"key": "bennies", "name": "Bennies", "cap": 5, "refresh": "each-session", "refresh_to": 3,
 *                 "carry_over": 0, "transferable": true}],
 *      "game_masters": ["member:7"]}
 *
 * A field of a kind left out takes its default: `character`, `never`, 0, all carried over, granted by the game masters,
 * not transferable. Whatever the input, the reader gives rules or refuses with `InvalidTokenRule`.
 */
final readonly class TableRules
{
    public const int MAX_KINDS = 16;

    public const int MAX_GAME_MASTERS = 16;

    public const int MAX_JSON_BYTES = 65_536;

    public const int MAX_DEPTH = 6;

    private const array FIELDS = ['kinds', 'game_masters'];

    private const array KIND_FIELDS = ['key', 'name', 'cap', 'scope', 'refresh', 'refresh_to', 'carry_over', 'grants', 'transferable'];

    /** @var array<string, TokenKind> */
    private array $kinds;

    /**
     * @param list<TokenKind>         $kinds
     * @param list<ResourceReference> $gameMasters
     *
     * @throws InvalidTokenRule when there are too many kinds or game masters, or two kinds have the same key
     */
    public function __construct(
        public ResourceReference $table,
        array $kinds,
        public array $gameMasters = [],
    ) {
        self::counted(\count($kinds), \count($gameMasters));

        $byKey = [];

        foreach ($kinds as $kind) {
            if (isset($byKey[$kind->key])) {
                throw InvalidTokenRule::because(\sprintf('Two kinds of tokens have the key "%s".', $kind->key));
            }

            $byKey[$kind->key] = $kind;
        }

        $this->kinds = $byKey;
    }

    /**
     * @throws InvalidTokenRule when the text is not rules as described above
     */
    public static function fromJson(ResourceReference $table, string $json): self
    {
        if (\strlen($json) > self::MAX_JSON_BYTES) {
            throw InvalidTokenRule::because(\sprintf('A text of rules holds %d bytes at most.', self::MAX_JSON_BYTES));
        }

        try {
            $rules = json_decode($json, true, self::MAX_DEPTH, \JSON_THROW_ON_ERROR);
        } catch (JsonException $jsonException) {
            throw InvalidTokenRule::because(\sprintf('The rules are not JSON of %d levels at most: %s.', self::MAX_DEPTH, $jsonException->getMessage()));
        }

        if (!\is_array($rules)) {
            throw InvalidTokenRule::because('The rules of a table are an object of fields.');
        }

        return self::fromArray($table, $rules);
    }

    /**
     * @param array<array-key, mixed> $rules
     *
     * @throws InvalidTokenRule when the values are not rules as described above
     */
    public static function fromArray(ResourceReference $table, array $rules): self
    {
        self::fields($rules, self::FIELDS, 'the rules');
        $kinds = $rules['kinds'] ?? [];
        $masters = $rules['game_masters'] ?? [];

        if (!\is_array($kinds) || !array_is_list($kinds)) {
            throw InvalidTokenRule::because('The kinds of tokens are a list.');
        }

        if (!\is_array($masters) || !array_is_list($masters)) {
            throw InvalidTokenRule::because('The game masters are a list of references.');
        }

        // Counted before they are read, so that a long list costs nothing.
        self::counted(\count($kinds), \count($masters));

        return new self($table, array_map(self::readKind(...), $kinds), array_map(self::reference(...), $masters));
    }

    /**
     * @return list<TokenKind>
     */
    public function kinds(): array
    {
        return array_values($this->kinds);
    }

    /**
     * @throws TokenRefused when the table hands out no such token
     */
    public function kind(string $key): TokenKind
    {
        return $this->kinds[$key] ?? throw TokenRefused::unknownKind($key);
    }

    public function isGameMaster(ResourceReference $actor): bool
    {
        return array_any($this->gameMasters, static fn (ResourceReference $master): bool => $master->equals($actor));
    }

    /**
     * @return array{kinds: list<array<string, mixed>>, game_masters: list<string>}
     */
    public function toArray(): array
    {
        return [
            'kinds' => array_map(static fn (TokenKind $kind): array => $kind->toArray(), $this->kinds()),
            'game_masters' => array_map(static fn (ResourceReference $master): string => $master->toString(), $this->gameMasters),
        ];
    }

    private static function readKind(mixed $definition): TokenKind
    {
        if (!\is_array($definition)) {
            throw InvalidTokenRule::because('A kind of token is an object of fields.');
        }

        self::fields($definition, self::KIND_FIELDS, 'a kind of token');
        $key = self::text($definition, 'key', 'a kind of token');
        $where = \sprintf('the token "%s"', mb_substr($key, 0, 32));
        $carryOver = $definition['carry_over'] ?? null;

        if (null !== $carryOver && !\is_int($carryOver)) {
            throw InvalidTokenRule::because(\sprintf('The field "carry_over" of %s is an integer, or null.', $where));
        }

        return new TokenKind(
            $key,
            self::text($definition, 'name', $where),
            self::integer($definition, 'cap', null, $where),
            self::choice(HolderScope::class, $definition, 'scope', HolderScope::Character, $where),
            self::choice(Refresh::class, $definition, 'refresh', Refresh::Never, $where),
            self::integer($definition, 'refresh_to', 0, $where),
            $carryOver,
            self::choice(GrantPolicy::class, $definition, 'grants', GrantPolicy::GameMasters, $where),
            self::flag($definition, 'transferable', $where),
        );
    }

    private static function counted(int $kinds, int $gameMasters): void
    {
        if ($kinds > self::MAX_KINDS) {
            throw InvalidTokenRule::because(\sprintf('A table hands out %d kinds of tokens at most.', self::MAX_KINDS));
        }

        if ($gameMasters > self::MAX_GAME_MASTERS) {
            throw InvalidTokenRule::because(\sprintf('A table has %d game masters at most.', self::MAX_GAME_MASTERS));
        }
    }

    private static function reference(mixed $reference): ResourceReference
    {
        if (!\is_string($reference)) {
            throw InvalidTokenRule::because('A game master is a reference written "kind:identifier".');
        }

        try {
            return ResourceReference::fromString($reference);
        } catch (InvalidResource $invalidResource) {
            throw InvalidTokenRule::because($invalidResource->getMessage());
        }
    }

    /**
     * @param array<array-key, mixed> $values
     * @param list<string>            $known
     */
    private static function fields(array $values, array $known, string $where): void
    {
        foreach (array_keys($values) as $field) {
            if (!\in_array($field, $known, true)) {
                throw InvalidTokenRule::because(\sprintf('The field "%s" of %s is not one of %s.', mb_substr((string) $field, 0, 32), $where, implode(', ', $known)));
            }
        }
    }

    /**
     * @param array<array-key, mixed> $values
     */
    private static function text(array $values, string $field, string $where): string
    {
        $value = $values[$field] ?? null;

        return \is_string($value) ? $value : throw InvalidTokenRule::because(\sprintf('The field "%s" of %s is a text.', $field, $where));
    }

    /**
     * @param array<array-key, mixed> $values
     */
    private static function integer(array $values, string $field, ?int $default, string $where): int
    {
        $value = $values[$field] ?? $default;

        return \is_int($value) ? $value : throw InvalidTokenRule::because(\sprintf('The field "%s" of %s is an integer.', $field, $where));
    }

    /**
     * @param array<array-key, mixed> $values
     */
    private static function flag(array $values, string $field, string $where): bool
    {
        $value = $values[$field] ?? false;

        return \is_bool($value) ? $value : throw InvalidTokenRule::because(\sprintf('The field "%s" of %s is true or false.', $field, $where));
    }

    /**
     * @template E of HolderScope|Refresh|GrantPolicy
     *
     * @param class-string<E>         $enum
     * @param array<array-key, mixed> $values
     * @param E                       $default
     *
     * @return E
     */
    private static function choice(string $enum, array $values, string $field, HolderScope|Refresh|GrantPolicy $default, string $where): HolderScope|Refresh|GrantPolicy
    {
        if (!isset($values[$field])) {
            return $default;
        }

        $value = $values[$field];
        $choice = \is_string($value) ? $enum::tryFrom($value) : null;

        if (null === $choice) {
            throw InvalidTokenRule::because(\sprintf('The field "%s" of %s is one of %s.', $field, $where, implode(', ', array_map(static fn (HolderScope|Refresh|GrantPolicy $case): string => $case->value, $enum::cases()))));
        }

        return $choice;
    }
}
