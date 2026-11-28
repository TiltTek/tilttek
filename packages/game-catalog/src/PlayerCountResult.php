<?php

declare(strict_types=1);

namespace Tilttek\GameCatalog;

use Tilttek\GameCatalog\Internal\Average;

/**
 * The votes of the members on one number of players: the ratings of 1 to 5 and their average, and the three choices.
 */
final readonly class PlayerCountResult
{
    public function __construct(
        public int $count,
        public int $ratingVotes = 0,
        public int $ratingSum = 0,
        public int $best = 0,
        public int $recommended = 0,
        public int $notRecommended = 0,
    ) {
    }

    /**
     * The average rating in hundredths, rounded half up — 350 for 3.5 —, or `null` without a rating.
     */
    public function averageRating(): ?int
    {
        return Average::hundredths($this->ratingSum, $this->ratingVotes);
    }

    /**
     * How many members gave one of the three choices.
     */
    public function choiceVotes(): int
    {
        return $this->best + $this->recommended + $this->notRecommended;
    }

    /**
     * The choice most members gave, or `null` without a choice; a tie goes to the more cautious choice.
     */
    public function choice(): ?PlayerCountChoice
    {
        if (0 === $this->choiceVotes()) {
            return null;
        }

        if ($this->notRecommended >= $this->best && $this->notRecommended >= $this->recommended) {
            return PlayerCountChoice::NotRecommended;
        }

        return $this->recommended >= $this->best ? PlayerCountChoice::Recommended : PlayerCountChoice::Best;
    }

    public function withRating(int $rating): self
    {
        return new self($this->count, $this->ratingVotes + 1, $this->ratingSum + $rating, $this->best, $this->recommended, $this->notRecommended);
    }

    public function withChoice(PlayerCountChoice $choice): self
    {
        return new self(
            $this->count,
            $this->ratingVotes,
            $this->ratingSum,
            $this->best + (PlayerCountChoice::Best === $choice ? 1 : 0),
            $this->recommended + (PlayerCountChoice::Recommended === $choice ? 1 : 0),
            $this->notRecommended + (PlayerCountChoice::NotRecommended === $choice ? 1 : 0),
        );
    }
}
