<?php

declare(strict_types=1);

namespace Tilttek\PuzzleBuilder;

use JsonException;
use Tilttek\PuzzleBuilder\Answer\AnswerKey;
use Tilttek\PuzzleBuilder\Answer\AnswerNormalizer;
use Tilttek\PuzzleBuilder\Exception\InvalidScenario;
use Tilttek\PuzzleBuilder\Exception\PuzzleRefused;

/**
 * The puzzles of a scenario and the graph that opens them, checked when it is made: every identifier once, every
 * puzzle required held by the scenario, no cycle among the puzzles that require one another, and every puzzle
 * reachable by a game that solves what it opens — a puzzle that waits for more solves than the scenario allows, or
 * on a puzzle that never opens, is refused.
 *
 * A scenario ends when its finale is solved or, without one, when every puzzle is.
 */
final readonly class Scenario
{
    public const int MAX_PUZZLES = 200;

    public const int MAX_JSON_BYTES = 262_144;

    public const int MAX_DEPTH = 8;

    private const string IDENTIFIER = '/^[a-z](?:[a-z0-9_-]{0,62}[a-z0-9])?$/D';

    private const array FIELDS = ['id', 'finale', 'puzzles'];

    private const array PUZZLE_FIELDS = ['id', 'title', 'answers', 'partials', 'normalize', 'hints', 'unlock'];

    private const array NORMALIZE_FIELDS = ['keep_accents', 'keep_spaces', 'keep_punctuation'];

    private const array HINT_FIELDS = ['text', 'penalty'];

    private const array UNLOCK_FIELDS = ['requires', 'minimum', 'after_solves', 'manual'];

    /** @var array<string, Puzzle> */
    private array $puzzles;

    /** @var list<string> */
    private array $order;

    /**
     * @param string       $id      1 to 64 characters among `a-z`, `0-9`, inner `-` and `_`, from a letter
     * @param list<Puzzle> $puzzles 1 to 200 puzzles
     * @param ?string      $finale  the puzzle that ends the scenario; null when every puzzle must be solved
     *
     * @throws InvalidScenario when the scenario is not one the library takes, as described above
     */
    public function __construct(
        public string $id,
        array $puzzles,
        public ?string $finale = null,
    ) {
        self::identifier($id, 'scenario');

        if ([] === $puzzles || \count($puzzles) > self::MAX_PUZZLES) {
            throw InvalidScenario::because(\sprintf('A scenario holds 1 to %d puzzles.', self::MAX_PUZZLES));
        }

        $byId = [];

        foreach ($puzzles as $puzzle) {
            if (isset($byId[$puzzle->id])) {
                throw InvalidScenario::because(\sprintf('The puzzle "%s" is given twice.', $puzzle->id));
            }

            $byId[$puzzle->id] = $puzzle;
        }

        foreach ($byId as $puzzle) {
            foreach ($puzzle->unlock->requires as $required) {
                if (!isset($byId[$required])) {
                    throw InvalidScenario::because(\sprintf('The puzzle "%s" requires "%s", which the scenario does not hold.', $puzzle->id, $required));
                }
            }
        }

        if (null !== $finale && !isset($byId[$finale])) {
            throw InvalidScenario::because(\sprintf('The finale "%s" is not a puzzle of the scenario.', $finale));
        }

        $this->acyclic($byId);
        $this->puzzles = $byId;
        $this->order = $this->reachable($byId);
    }

    /**
     * Reads a scenario from JSON, within 256 KiB and 8 levels.
     *
     * @throws InvalidScenario when the text is not a scenario as `fromArray()` reads it
     */
    public static function fromJson(string $json): self
    {
        if (\strlen($json) > self::MAX_JSON_BYTES) {
            throw InvalidScenario::because(\sprintf('A scenario holds %d bytes at most.', self::MAX_JSON_BYTES));
        }

        try {
            $scenario = json_decode($json, true, self::MAX_DEPTH, \JSON_THROW_ON_ERROR);
        } catch (JsonException $jsonException) {
            throw InvalidScenario::because(\sprintf('The scenario is not JSON of %d levels at most: %s.', self::MAX_DEPTH, $jsonException->getMessage()));
        }

        if (!\is_array($scenario)) {
            throw InvalidScenario::because('A scenario is an object of fields.');
        }

        return self::fromArray($scenario);
    }

    /**
     * Reads a scenario from plain values: `id`, `puzzles` and `finale`; each puzzle with its `id`, `title`, `answers`,
     * `partials`, `normalize`, `hints` and `unlock`. A field the library does not know is refused.
     *
     * @param array<array-key, mixed> $scenario
     *
     * @throws InvalidScenario when the values are not a scenario
     */
    public static function fromArray(array $scenario): self
    {
        self::fields($scenario, self::FIELDS, 'the scenario');
        $puzzles = $scenario['puzzles'] ?? null;

        if (!\is_array($puzzles) || !array_is_list($puzzles) || \count($puzzles) > self::MAX_PUZZLES) {
            throw InvalidScenario::because(\sprintf('The puzzles of a scenario are a list of %d at most.', self::MAX_PUZZLES));
        }

        $read = [];

        foreach ($puzzles as $puzzle) {
            $read[] = self::puzzleOf($puzzle);
        }

        return new self(self::text($scenario, 'id', 'the scenario'), $read, self::optionalText($scenario, 'finale', 'the scenario'));
    }

    /**
     * @return list<Puzzle> in the order they were given
     */
    public function puzzles(): array
    {
        return array_values($this->puzzles);
    }

    public function has(string $puzzle): bool
    {
        return isset($this->puzzles[$puzzle]);
    }

    /**
     * @throws PuzzleRefused when the scenario holds no such puzzle
     */
    public function puzzle(string $puzzle): Puzzle
    {
        return $this->puzzles[$puzzle] ?? throw PuzzleRefused::unknown($puzzle);
    }

    /**
     * The puzzles in an order a game can solve them: each opens once those before it are solved, and those the host
     * opens are counted as opened.
     *
     * @return list<string>
     */
    public function order(): array
    {
        return $this->order;
    }

    /**
     * Whether the scenario ends with these puzzles solved.
     *
     * @param array<string, mixed> $solved the puzzles solved, by their identifier
     */
    public function completedBy(array $solved): bool
    {
        if (null !== $this->finale) {
            return \array_key_exists($this->finale, $solved);
        }

        return [] === array_diff_key($this->puzzles, $solved);
    }

    /**
     * @throws InvalidScenario when the identifier is not 1 to 64 characters among `a-z`, `0-9`, inner `-` and `_`, from a letter
     *
     * @internal
     */
    public static function identifier(string $id, string $what): void
    {
        if (1 !== preg_match(self::IDENTIFIER, $id)) {
            throw InvalidScenario::because(\sprintf('The identifier of a %s holds 1 to 64 characters among a-z, 0-9, and inner - and _, from a letter; "%s" does not.', $what, mb_substr($id, 0, 64)));
        }
    }

    /**
     * @param array<string, Puzzle> $puzzles
     */
    private function acyclic(array $puzzles): void
    {
        /** @var array<string, bool> $state false while the puzzle is on the path, true once it is cleared */
        $state = [];

        foreach (array_keys($puzzles) as $start) {
            if (!isset($state[$start])) {
                self::visit($puzzles, $start, $state, []);
            }
        }
    }

    /**
     * @param array<string, Puzzle> $puzzles
     * @param array<string, bool>   $state
     * @param list<string>          $path
     */
    private static function visit(array $puzzles, string $puzzle, array &$state, array $path): void
    {
        $state[$puzzle] = false;
        $path[] = $puzzle;

        foreach ($puzzles[$puzzle]->unlock->requires as $required) {
            if (false === ($state[$required] ?? null)) {
                $cycle = \array_slice($path, (int) array_search($required, $path, true));

                throw InvalidScenario::because(\sprintf('The puzzles %s require one another.', implode(' → ', [...$cycle, $required])));
            }

            if (!isset($state[$required])) {
                self::visit($puzzles, $required, $state, $path);
            }
        }

        $state[$puzzle] = true;
    }

    /**
     * @param array<string, Puzzle> $puzzles
     *
     * @return list<string>
     */
    private function reachable(array $puzzles): array
    {
        $solved = [];
        $order = [];

        do {
            $opened = false;

            foreach ($puzzles as $id => $puzzle) {
                if (!isset($solved[$id]) && ($puzzle->unlock->manual || $puzzle->unlock->opensWith($solved))) {
                    $solved[$id] = $id;
                    $order[] = $id;
                    $opened = true;
                }
            }
        } while ($opened);

        $never = array_keys(array_diff_key($puzzles, $solved));

        if ([] !== $never) {
            throw InvalidScenario::because(\sprintf('No game can reach the puzzles %s.', implode(', ', $never)));
        }

        return $order;
    }

    private static function puzzleOf(mixed $puzzle): Puzzle
    {
        if (!\is_array($puzzle)) {
            throw InvalidScenario::because('A puzzle is an object of fields.');
        }

        self::fields($puzzle, self::PUZZLE_FIELDS, 'a puzzle');
        $id = self::text($puzzle, 'id', 'a puzzle');
        $answers = $puzzle['answers'] ?? null;
        $partials = $puzzle['partials'] ?? [];
        $hints = $puzzle['hints'] ?? [];

        if (!\is_array($answers) || !array_is_list($answers) || !self::texts($answers)) {
            throw InvalidScenario::because(\sprintf('The answers of the puzzle "%s" are a list of texts.', $id));
        }

        if (!\is_array($partials) || (array_is_list($partials) && [] !== $partials) || !self::texts($partials)) {
            throw InvalidScenario::because(\sprintf('The partial answers of the puzzle "%s" are an object of messages.', $id));
        }

        if (!\is_array($hints) || !array_is_list($hints) || \count($hints) > Puzzle::MAX_HINTS) {
            throw InvalidScenario::because(\sprintf('The hints of the puzzle "%s" are a list of %d at most.', $id, Puzzle::MAX_HINTS));
        }

        $tiers = [];

        foreach ($hints as $hint) {
            if (!\is_array($hint)) {
                throw InvalidScenario::because('A hint is an object of fields.');
            }

            self::fields($hint, self::HINT_FIELDS, 'a hint');
            $tiers[] = new Hint(self::text($hint, 'text', 'a hint'), self::integer($hint, 'penalty', 'a hint') ?? 0);
        }

        return new Puzzle(
            $id,
            self::text($puzzle, 'title', 'a puzzle'),
            new AnswerKey($answers, $partials, self::normalizerOf($puzzle['normalize'] ?? [])),
            $tiers,
            self::unlockOf($puzzle['unlock'] ?? []),
        );
    }

    private static function normalizerOf(mixed $options): AnswerNormalizer
    {
        if (!\is_array($options)) {
            throw InvalidScenario::because('The normalization of a puzzle is an object of fields.');
        }

        self::fields($options, self::NORMALIZE_FIELDS, 'a normalization');

        return new AnswerNormalizer(
            self::flag($options, 'keep_accents'),
            self::flag($options, 'keep_spaces'),
            self::flag($options, 'keep_punctuation'),
        );
    }

    private static function unlockOf(mixed $unlock): Unlock
    {
        if (!\is_array($unlock)) {
            throw InvalidScenario::because('The unlock of a puzzle is an object of fields.');
        }

        self::fields($unlock, self::UNLOCK_FIELDS, 'an unlock');
        $requires = $unlock['requires'] ?? [];

        if (!\is_array($requires) || !array_is_list($requires) || !self::texts($requires)) {
            throw InvalidScenario::because('The puzzles an unlock requires are a list of identifiers.');
        }

        return new Unlock($requires, self::integer($unlock, 'minimum', 'an unlock'), self::integer($unlock, 'after_solves', 'an unlock') ?? 0, self::flag($unlock, 'manual'));
    }

    /**
     * @param array<array-key, mixed> $values
     * @param list<string>            $known
     */
    private static function fields(array $values, array $known, string $what): void
    {
        foreach (array_keys($values) as $field) {
            if (!\in_array($field, $known, true)) {
                throw InvalidScenario::because(\sprintf('The field "%s" is not one of %s.', mb_substr((string) $field, 0, 64), $what));
            }
        }
    }

    /**
     * @param array<array-key, mixed> $values
     *
     * @phpstan-assert-if-true array<array-key, string> $values
     */
    private static function texts(array $values): bool
    {
        return array_all($values, static fn (mixed $value): bool => \is_string($value));
    }

    /**
     * @param array<array-key, mixed> $values
     */
    private static function text(array $values, string $field, string $what): string
    {
        $value = $values[$field] ?? null;

        if (!\is_string($value)) {
            throw InvalidScenario::because(\sprintf('The field "%s" of %s is a text.', $field, $what));
        }

        return $value;
    }

    /**
     * @param array<array-key, mixed> $values
     */
    private static function optionalText(array $values, string $field, string $what): ?string
    {
        return null === ($values[$field] ?? null) ? null : self::text($values, $field, $what);
    }

    /**
     * @param array<array-key, mixed> $values
     */
    private static function integer(array $values, string $field, string $what): ?int
    {
        $value = $values[$field] ?? null;

        if (null !== $value && !\is_int($value)) {
            throw InvalidScenario::because(\sprintf('The field "%s" of %s is a whole number.', $field, $what));
        }

        return $value;
    }

    /**
     * @param array<array-key, mixed> $values
     */
    private static function flag(array $values, string $field): bool
    {
        $value = $values[$field] ?? false;

        if (!\is_bool($value)) {
            throw InvalidScenario::because(\sprintf('The field "%s" is true or false.', $field));
        }

        return $value;
    }
}
