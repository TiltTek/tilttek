<?php

declare(strict_types=1);

namespace Tilttek\Encounter\Difficulty;

use Tilttek\Encounter\Exception\InvalidEncounter;
use Tilttek\Encounter\Internal\Identifier;

/**
 * The difficulty of an encounter as the rules of its game system assess it: the cost of its adversaries, the bands of
 * difficulty with their thresholds for these characters, and the band reached — the last one whose threshold the cost
 * meets, or null when it meets none.
 */
final readonly class Assessment
{
    /** The most bands of difficulty. */
    public const int MAX_BANDS = 12;

    /** @var list<Band> */
    public array $bands;

    /** The key of the band reached, or null when the cost is under every threshold. */
    public ?string $difficulty;

    /**
     * @param string     $system      the key of the game system whose rules assessed it
     * @param int        $cost        what the adversaries cost, as the rules count it, 0 or more
     * @param list<Band> $bands       1 to 12, from the easiest, their thresholds in order, each key once
     * @param int        $characters  how many characters the thresholds are for
     * @param int        $adversaries how many adversaries the cost is for, every copy counted
     *
     * @throws InvalidEncounter when a value is out of its bounds, or the bands are out of order
     */
    public function __construct(public string $system, public int $cost, array $bands, public int $characters, public int $adversaries)
    {
        Identifier::check($system, 'a game system');
        if ($cost < 0 || $characters < 0 || $adversaries < 0) {
            throw InvalidEncounter::because('The cost, the characters and the adversaries of an assessment are 0 or more.');
        }

        if ([] === $bands || \count($bands) > self::MAX_BANDS) {
            throw InvalidEncounter::because(\sprintf('An assessment has 1 to %d bands of difficulty.', self::MAX_BANDS));
        }

        $difficulty = null;
        $keys = [];
        $previous = $bands[0]->threshold;
        foreach ($bands as $band) {
            if (isset($keys[$band->key])) {
                throw InvalidEncounter::because(\sprintf('The band %s comes twice.', $band->key));
            }

            if ($band->threshold < $previous) {
                throw InvalidEncounter::because(\sprintf('The threshold of the band %s is under the one before it.', $band->key));
            }

            if ($band->threshold <= $cost) {
                $difficulty = $band->key;
            }

            $keys[$band->key] = true;
            $previous = $band->threshold;
        }

        $this->bands = $bands;
        $this->difficulty = $difficulty;
    }

    /**
     * The threshold of the band.
     *
     * @throws InvalidEncounter when the rules declare no band of the key
     */
    public function threshold(string $band): int
    {
        foreach ($this->bands as $declared) {
            if ($declared->key === $band) {
                return $declared->threshold;
            }
        }

        throw InvalidEncounter::because(\sprintf('The rules of %s declare no band %s.', $this->system, $band));
    }

    /**
     * Whether the cost meets the threshold of the band.
     *
     * @throws InvalidEncounter when the rules declare no band of the key
     */
    public function reached(string $band): bool
    {
        return $this->cost >= $this->threshold($band);
    }

    /**
     * What the game master may still add before the encounter reaches the band, 0 once it is reached.
     *
     * @throws InvalidEncounter when the rules declare no band of the key
     */
    public function remainingTo(string $band): int
    {
        return max(0, $this->threshold($band) - $this->cost);
    }

    /**
     * @return array{system: string, cost: int, difficulty: ?string, characters: int, adversaries: int, bands: list<array{key: string, threshold: int}>}
     */
    public function toArray(): array
    {
        return [
            'system' => $this->system,
            'cost' => $this->cost,
            'difficulty' => $this->difficulty,
            'characters' => $this->characters,
            'adversaries' => $this->adversaries,
            'bands' => array_map(static fn (Band $band): array => ['key' => $band->key, 'threshold' => $band->threshold], $this->bands),
        ];
    }
}
