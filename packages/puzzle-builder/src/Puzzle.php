<?php

declare(strict_types=1);

namespace Tilttek\PuzzleBuilder;

use Tilttek\PuzzleBuilder\Contract\AnswerCheck;
use Tilttek\PuzzleBuilder\Exception\InvalidScenario;

/**
 * One puzzle of a scenario: its identifier and title, how its guesses are checked, its hints by tier and when it
 * opens.
 */
final readonly class Puzzle
{
    public const int MAX_TITLE = 200;

    public const int MAX_HINTS = 10;

    /** @var list<Hint> */
    public array $hints;

    /**
     * @param string     $id     1 to 64 characters among `a-z`, `0-9`, inner `-` and `_`, from a letter
     * @param list<Hint> $hints  the tiers of hints, revealed in this order, 10 at most
     * @param Unlock     $unlock when it opens; open from the start by default
     *
     * @throws InvalidScenario when the identifier, the title or the hints are not ones the library takes
     */
    public function __construct(
        public string $id,
        public string $title,
        public AnswerCheck $answer,
        array $hints = [],
        public Unlock $unlock = new Unlock(),
    ) {
        Scenario::identifier($id, 'puzzle');

        if ('' === trim($title) || mb_strlen($title, 'UTF-8') > self::MAX_TITLE) {
            throw InvalidScenario::because(\sprintf('The title of the puzzle "%s" holds 1 to %d characters.', $id, self::MAX_TITLE));
        }

        if (\count($hints) > self::MAX_HINTS) {
            throw InvalidScenario::because(\sprintf('The puzzle "%s" has %d hints at most.', $id, self::MAX_HINTS));
        }

        if (\in_array($id, $unlock->requires, true)) {
            throw InvalidScenario::because(\sprintf('The puzzle "%s" cannot require itself.', $id));
        }

        $this->hints = $hints;
    }

    /**
     * The same puzzle, opened another way: how a room chains its puzzles.
     */
    public function withUnlock(Unlock $unlock): self
    {
        return new self($this->id, $this->title, $this->answer, $this->hints, $unlock);
    }
}
