<?php

declare(strict_types=1);

namespace Tilttek\Weather;

use Tilttek\Weather\Exception\InvalidClimate;

/**
 * A weather condition — clear skies, fog, a blizzard — with its labels, how many degrees it moves the temperature from
 * the middle of the season's range, and the conditions it may turn into from one day to the next.
 */
final readonly class Condition
{
    /**
     * The most degrees a condition moves the temperature, up or down.
     */
    public const int TEMPERATURE = 20;

    /**
     * @var array<string, string> by language
     */
    public array $labels;

    /**
     * @var list<string>
     */
    public array $neighbours;

    /**
     * @param array<array-key, mixed> $labels     by language
     * @param list<string>            $neighbours the conditions it may turn into, and that may turn into it
     *
     * @throws InvalidClimate
     */
    public function __construct(public string $id, array $labels, public int $temperature = 0, array $neighbours = [])
    {
        $place = 'conditions.'.Rules::identifier('conditions', $id);
        $this->labels = Rules::labels($place.'.labels', $labels);
        Rules::between($place.'.temperature', $temperature, -self::TEMPERATURE, self::TEMPERATURE);

        foreach ($neighbours as $neighbour) {
            if ($neighbour === $id) {
                throw InvalidClimate::at($place.'.next_to', 'a condition is not its own neighbour: it may always stay');
            }

            Rules::identifier($place.'.next_to', $neighbour);
        }

        $this->neighbours = array_values(array_unique($neighbours));
    }
}
