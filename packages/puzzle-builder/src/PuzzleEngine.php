<?php

declare(strict_types=1);

namespace Tilttek\PuzzleBuilder;

use DateTimeImmutable;
use Phil\Resource\ResourceReference;
use Psr\Clock\ClockInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Clock\NativeClock;
use Tilttek\PuzzleBuilder\Contract\GuessLimiter;
use Tilttek\PuzzleBuilder\Event\GuessThrottled;
use Tilttek\PuzzleBuilder\Event\HintRevealed;
use Tilttek\PuzzleBuilder\Event\PartialAnswerGiven;
use Tilttek\PuzzleBuilder\Event\PuzzleOpened;
use Tilttek\PuzzleBuilder\Event\PuzzleSolved;
use Tilttek\PuzzleBuilder\Event\ScenarioCompleted;
use Tilttek\PuzzleBuilder\Event\WrongAnswerGiven;
use Tilttek\PuzzleBuilder\Exception\InvalidAnswer;
use Tilttek\PuzzleBuilder\Exception\PuzzleRefused;
use Tilttek\PuzzleBuilder\Record\HintTaken;
use Tilttek\PuzzleBuilder\Record\Solved;
use Tilttek\PuzzleBuilder\View\PuzzleView;
use Tilttek\PuzzleBuilder\View\ScenarioView;

/**
 * The moves of a game: guesses checked on the server and bounded by the limiter, hints revealed tier after tier,
 * puzzles the host opens or skips, and what the players see.
 *
 * Each move takes the scenario and the team's `Progress` and gives the progress to store after it; the engine keeps
 * nothing between two calls, so one instance serves every request of a worker. Once a move is made, its events are
 * dispatched: they name identifiers, never a guess nor an answer.
 */
final readonly class PuzzleEngine
{
    public function __construct(
        private GuessLimiter $limiter,
        private ?EventDispatcherInterface $events = null,
        private ClockInterface $clock = new NativeClock(),
    ) {
    }

    /**
     * @throws PuzzleRefused when the scenario holds no such puzzle
     */
    public function status(Scenario $scenario, Progress $progress, string $puzzle): PuzzleStatus
    {
        $unlock = $scenario->puzzle($puzzle)->unlock;

        if ($progress->isSolved($puzzle)) {
            return PuzzleStatus::Solved;
        }

        return $progress->isOpened($puzzle) || $unlock->opensWith($progress->solved()) ? PuzzleStatus::Open : PuzzleStatus::Locked;
    }

    /**
     * Checks a guess of an open puzzle. A guess past the limit of the period is not checked and gives
     * `GuessOutcome::Throttled` with the time the team may guess again.
     *
     * @throws PuzzleRefused when the puzzle is unknown, locked or solved, or the team is named by an email address
     * @throws InvalidAnswer when the guess is not one the check reads; it counts against the limit, not among the
     *                       guesses of the progress
     */
    public function guess(Scenario $scenario, Progress $progress, ResourceReference $team, string $puzzle, string $answer): GuessResult
    {
        $this->playable($scenario, $progress, $team, $puzzle);
        $allowance = $this->limiter->attempt(self::key($scenario, $team, $puzzle));

        if ($allowance->retryAfter instanceof DateTimeImmutable) {
            $this->events?->dispatch(new GuessThrottled($scenario->id, $team, $puzzle, $allowance->retryAfter));

            return new GuessResult(GuessOutcome::Throttled, $progress, retryAfter: $allowance->retryAfter);
        }

        $verdict = $scenario->puzzle($puzzle)->answer->check($answer);
        $progress = $progress->withGuess($puzzle);
        $now = $this->clock->now();

        return match ($verdict->outcome) {
            GuessOutcome::Correct => $this->solve($scenario, $progress, $team, $puzzle, $now, false),
            GuessOutcome::Partial => $this->dispatched(new GuessResult(GuessOutcome::Partial, $progress, $verdict->message), new PartialAnswerGiven($scenario->id, $team, $puzzle, $now)),
            default => $this->dispatched(new GuessResult(GuessOutcome::Wrong, $progress), new WrongAnswerGiven($scenario->id, $team, $puzzle, $now)),
        };
    }

    /**
     * Marks an open puzzle solved for players who are stuck: the host's move, which opens what the puzzle opens.
     *
     * @throws PuzzleRefused when the puzzle is unknown, locked or solved, or the team is named by an email address
     */
    public function skip(Scenario $scenario, Progress $progress, ResourceReference $team, string $puzzle): GuessResult
    {
        $this->playable($scenario, $progress, $team, $puzzle);

        return $this->solve($scenario, $progress, $team, $puzzle, $this->clock->now(), true);
    }

    /**
     * Opens a puzzle the host opens: a clue handed out in the second act.
     *
     * @throws PuzzleRefused when the puzzle is unknown, opens by its unlock, or is open or solved already
     */
    public function open(Scenario $scenario, Progress $progress, ResourceReference $team, string $puzzle): Progress
    {
        $this->named($team);

        if (!$scenario->puzzle($puzzle)->unlock->manual) {
            throw PuzzleRefused::notManual($puzzle);
        }

        if (PuzzleStatus::Locked !== $this->status($scenario, $progress, $puzzle)) {
            throw PuzzleRefused::opened($puzzle);
        }

        $now = $this->clock->now();
        $this->events?->dispatch(new PuzzleOpened($scenario->id, $team, $puzzle, $now, true));

        return $progress->withOpened($puzzle, $now);
    }

    /**
     * Reveals the next tier of hints of an open puzzle, asked by the players or pushed by the host.
     *
     * @throws PuzzleRefused when the puzzle is unknown, locked or solved, or has no hint left
     */
    public function hint(Scenario $scenario, Progress $progress, ResourceReference $team, string $puzzle, bool $pushed = false): HintResult
    {
        $this->playable($scenario, $progress, $team, $puzzle);
        $hints = $scenario->puzzle($puzzle)->hints;
        $tier = \count($progress->hintsOf($puzzle)) + 1;

        if ($tier > \count($hints)) {
            throw PuzzleRefused::noHintLeft($puzzle);
        }

        $now = $this->clock->now();
        $progress = $progress->withHint(new HintTaken($puzzle, $tier, $now, $pushed));
        $this->events?->dispatch(new HintRevealed($scenario->id, $team, $puzzle, $now, $tier, $pushed));

        return new HintResult($hints[$tier - 1], $tier, \count($hints) - $tier, $progress);
    }

    /**
     * What the players see: each puzzle in the order of the scenario, with its status, its hints revealed and its
     * guesses, never an answer.
     */
    public function view(Scenario $scenario, Progress $progress): ScenarioView
    {
        $views = [];

        foreach ($scenario->order() as $id) {
            $puzzle = $scenario->puzzle($id);
            $status = $this->status($scenario, $progress, $id);
            $solved = $progress->solved()[$id] ?? null;

            if (PuzzleStatus::Locked === $status) {
                $views[] = new PuzzleView($id, $status, null, [], 0, 0, null, false);

                continue;
            }

            $revealed = array_map(static fn (HintTaken $taken): string => $puzzle->hints[$taken->tier - 1]->text, $progress->hintsOf($id));
            $views[] = new PuzzleView($id, $status, $puzzle->title, $revealed, \count($puzzle->hints) - \count($revealed), $progress->guesses($id), $solved?->at, null !== $solved && $solved->skipped);
        }

        return new ScenarioView($scenario->id, $views, $scenario->completedBy($progress->solved()));
    }

    /**
     * The key a limiter counts the guesses under: one scenario, one team, one puzzle.
     */
    public static function key(Scenario $scenario, ResourceReference $team, string $puzzle): string
    {
        return \sprintf('%s|%s|%s', $scenario->id, $team->toString(), $puzzle);
    }

    private function playable(Scenario $scenario, Progress $progress, ResourceReference $team, string $puzzle): void
    {
        $this->named($team);

        match ($this->status($scenario, $progress, $puzzle)) {
            PuzzleStatus::Locked => throw PuzzleRefused::locked($puzzle),
            PuzzleStatus::Solved => throw PuzzleRefused::solved($puzzle),
            PuzzleStatus::Open => null,
        };
    }

    private function named(ResourceReference $team): void
    {
        if (str_contains($team->id, '@')) {
            throw PuzzleRefused::emailTeam();
        }
    }

    private function solve(Scenario $scenario, Progress $progress, ResourceReference $team, string $puzzle, DateTimeImmutable $at, bool $skipped): GuessResult
    {
        $locked = array_filter($scenario->order(), fn (string $id): bool => PuzzleStatus::Locked === $this->status($scenario, $progress, $id));
        $wasCompleted = $scenario->completedBy($progress->solved());
        $progress = $progress->withSolved(new Solved($puzzle, $at, $skipped));
        $this->events?->dispatch(new PuzzleSolved($scenario->id, $team, $puzzle, $at, $skipped));
        $opened = [];

        foreach ($locked as $id) {
            if (PuzzleStatus::Open === $this->status($scenario, $progress, $id)) {
                $opened[] = $id;
                $this->events?->dispatch(new PuzzleOpened($scenario->id, $team, $id, $at, false));
            }
        }

        $completed = !$wasCompleted && $scenario->completedBy($progress->solved());

        if ($completed) {
            $this->events?->dispatch(new ScenarioCompleted($scenario->id, $team, $at));
        }

        return new GuessResult(GuessOutcome::Correct, $progress, opened: $opened, completed: $completed);
    }

    private function dispatched(GuessResult $result, object $event): GuessResult
    {
        $this->events?->dispatch($event);

        return $result;
    }
}
