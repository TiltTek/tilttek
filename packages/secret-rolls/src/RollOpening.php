<?php

declare(strict_types=1);

namespace Tilttek\SecretRolls;

use Tilttek\SecretRolls\Exception\InvalidSecretRoll;

/**
 * Everything a hidden roll is, kept sealed until it is revealed: the roll, the scene and the position of its commitment
 * in the scene's journal, its expression in the canonical form of `tilttek/dice`, the rounding of its divisions, its
 * seed, its result as `PoolResult::toArray()` writes it, and its context.
 *
 * Its commitment is the SHA-256 of the canonical JSON of `["tilttek-secret-rolls/1", roll, scene, position,
 * expression, rounding, seed, result, context]`: whoever holds the revealed opening computes it with any tool.
 */
final readonly class RollOpening
{
    /** The fields of an opening, in the order its commitment hashes them. */
    public const array FIELDS = ['roll', 'scene', 'position', 'expression', 'rounding', 'seed', 'result', 'context'];

    /** The longest expression, in bytes. */
    public const int MAX_EXPRESSION = 4096;

    /**
     * @param list<array<array-key, mixed>> $result
     */
    public function __construct(
        public string $roll,
        public string $scene,
        public int $position,
        public string $expression,
        public string $rounding,
        public string $seed,
        public array $result,
        public ?string $context,
    ) {
    }

    /**
     * An opening as `toArray()` wrote it — revealed in a journal, or kept by a store —, checked field by field; a field
     * it does not know is refused, so that nothing shown escapes the commitment.
     *
     * @param array<array-key, mixed> $data
     *
     * @throws InvalidSecretRoll when the data does not read as an opening
     */
    public static function fromArray(array $data): self
    {
        if (self::FIELDS !== array_keys($data)) {
            throw InvalidSecretRoll::because('An opening holds roll, scene, position, expression, rounding, seed, result and context, in this order.');
        }

        ['roll' => $roll, 'scene' => $scene, 'position' => $position, 'expression' => $expression, 'rounding' => $rounding, 'seed' => $seed, 'result' => $result, 'context' => $context] = $data;

        if (!\is_string($roll) || 1 !== preg_match('/^[0-9a-f]{32}$/D', $roll)) {
            throw InvalidSecretRoll::because('The roll of an opening is 32 lower-case hexadecimal digits.');
        }

        if (!\is_int($position) || $position < 1) {
            throw InvalidSecretRoll::because('The position of an opening is 1 or more.');
        }

        if (!\is_string($expression) || '' === $expression || \strlen($expression) > self::MAX_EXPRESSION) {
            throw InvalidSecretRoll::because(\sprintf('The expression of an opening is 1 to %d bytes.', self::MAX_EXPRESSION));
        }

        if (!\is_string($rounding) || !\is_string($seed) || !\is_string($scene) || (null !== $context && !\is_string($context))) {
            throw InvalidSecretRoll::because('The scene, the rounding, the seed and the context of an opening are text.');
        }

        if (!\is_array($result) || !array_is_list($result) || [] === $result || array_any($result, static fn (mixed $one): bool => !\is_array($one))) {
            throw InvalidSecretRoll::because('The result of an opening is a list of the results of its expressions.');
        }

        /** @var list<array<array-key, mixed>> $result checked above, its content compared with the roll made again */
        return new self($roll, Encoding::scene($scene), $position, $expression, $rounding, Encoding::hex64($seed, 'seed'), $result, Encoding::text($context, 'context', Encoding::MAX_CONTEXT));
    }

    public function commitment(): RollCommitment
    {
        return RollCommitment::of($this);
    }

    /**
     * The totals of the expressions, as the result writes them: what the game master reads first.
     *
     * @return list<mixed>
     */
    public function totals(): array
    {
        return array_column($this->result, 'total');
    }

    /**
     * @return array{roll: string, scene: string, position: int, expression: string, rounding: string, seed: string, result: list<array<array-key, mixed>>, context: ?string}
     */
    public function toArray(): array
    {
        return [
            'roll' => $this->roll,
            'scene' => $this->scene,
            'position' => $this->position,
            'expression' => $this->expression,
            'rounding' => $this->rounding,
            'seed' => $this->seed,
            'result' => $this->result,
            'context' => $this->context,
        ];
    }
}
