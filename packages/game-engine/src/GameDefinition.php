<?php

declare(strict_types=1);

namespace Tilttek\GameEngine;

use Tilttek\GameEngine\Exception\InvalidGame;

/**
 * What a game declares to the engine: its phases and the transitions between them, the phase it starts in, how many
 * seats a table holds, the options it offers, the moves a seat may make out of its turn, whether the last move may be
 * taken back, the version of its rules a log is replayed with, the preferences it offers each player, and the
 * statistics it reports when it ends.
 */
final readonly class GameDefinition
{
    /** The most seats a table holds. */
    public const int MAX_SEATS = 64;

    public Phases $machine;

    /** The phase a table starts in. */
    public string $initial;

    /** @var array<string, Phase> */
    private array $byName;

    /** @var array<string, Statistic> */
    private array $byStatistic;

    /**
     * @param string           $game        the game's name, `race`
     * @param list<Phase>      $phases      its phases, at least one
     * @param list<Transition> $transitions the changes of phase it allows
     * @param ?string          $initial     the phase a table starts in; the first phase when null
     * @param list<Option>     $options     the options it offers its tables
     * @param list<string>     $outOfTurn   the move types a seat may make when it is not its turn
     * @param bool             $undo        whether the last move may be taken back, when it drew nothing at random
     * @param string           $version     the version of the rules; a log written under another is not replayed
     * @param list<Option>     $preferences the preferences it offers each player, which change the view of a seat only
     * @param list<Statistic>  $statistics  the statistics its outcome reports
     *
     * @throws InvalidGame
     */
    public function __construct(
        public string $game,
        public array $phases,
        public array $transitions = [],
        ?string $initial = null,
        public int $minimumSeats = 1,
        public int $maximumSeats = 8,
        public array $options = [],
        public array $outOfTurn = [],
        public bool $undo = false,
        public string $version = '1',
        public array $preferences = [],
        public array $statistics = [],
    ) {
        Name::check($game, 'a game');

        if ([] === $phases) {
            throw InvalidGame::because(\sprintf('The game "%s" has no phase.', $game));
        }

        if ($minimumSeats < 1 || $maximumSeats < $minimumSeats || $maximumSeats > self::MAX_SEATS) {
            throw InvalidGame::because(\sprintf('The game "%s" seats %d to %d players; it seats 1 to %d.', $game, $minimumSeats, $maximumSeats, self::MAX_SEATS));
        }

        $this->byName = $this->unique($phases, static fn (Phase $phase): string => $phase->name, 'phase', $game);
        $this->unique($options, static fn (Option $option): string => $option->name, 'option', $game);
        $this->unique($preferences, static fn (Option $preference): string => $preference->name, 'preference', $game);
        $this->byStatistic = $this->unique($statistics, static fn (Statistic $statistic): string => $statistic->name, 'statistic', $game);

        foreach ($outOfTurn as $type) {
            if (str_starts_with(Name::type($type), 'engine.')) {
                throw InvalidGame::because(\sprintf('The game "%s" cannot declare the engine\'s move "%s".', $game, $type));
            }
        }

        if ('' === $version || \strlen($version) > 64) {
            throw InvalidGame::because(\sprintf('The version of the game "%s" is 1 to 64 characters.', $game));
        }

        $this->initial = $initial ?? $phases[0]->name;
        $this->machine = new Phases($game, $phases, $transitions, $this->initial);
    }

    /**
     * The phase a table starts in.
     */
    public function initialPhase(): Phase
    {
        return $this->phase($this->initial);
    }

    /**
     * @throws InvalidGame when the game has no such phase
     */
    public function phase(string $name): Phase
    {
        return $this->byName[$name] ?? throw InvalidGame::because(\sprintf('The game "%s" has no phase "%s".', $this->game, $name));
    }

    /**
     * The statistic of that name and scope, or null when the game declares none.
     */
    public function statistic(string $name, StatisticScope $scope): ?Statistic
    {
        $statistic = $this->byStatistic[$name] ?? null;

        return $statistic?->scope === $scope ? $statistic : null;
    }

    /**
     * @template T
     *
     * @param list<T>             $items
     * @param callable(T): string $name
     *
     * @return array<string, T>
     */
    private function unique(array $items, callable $name, string $kind, string $game): array
    {
        $byName = [];

        foreach ($items as $item) {
            $key = $name($item);

            if (isset($byName[$key])) {
                throw InvalidGame::because(\sprintf('The game "%s" has two of the %s "%s".', $game, $kind, $key));
            }

            $byName[$key] = $item;
        }

        return $byName;
    }
}
