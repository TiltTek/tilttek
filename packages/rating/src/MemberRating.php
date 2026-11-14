<?php

declare(strict_types=1);

namespace Tilttek\Rating;

use Tilttek\Rating\Contract\Rating;
use Tilttek\Rating\Contract\RatingSystem;
use Tilttek\Rating\Exception\InvalidRating;

/**
 * A member's rating of a game, as the store keeps it: the system that computed it, the rating, its score in
 * hundredths, the number of rated games, the highest score reached, who sees it, the last result it counts and its
 * version.
 */
final readonly class MemberRating
{
    /**
     * @throws InvalidRating when an identifier or a number is out of its range
     */
    public function __construct(
        public string $game,
        public string $member,
        public string $system,
        public Rating $rating,
        public int $score,
        public int $games = 0,
        public ?int $peak = null,
        public Visibility $visibility = Visibility::Private,
        public ?string $lastResult = null,
        public int $version = 0,
    ) {
        Identifier::check($game, 'game');
        Identifier::check($member, 'member');

        if (null !== $lastResult) {
            Identifier::check($lastResult, 'result');
        }

        if ($games < 0 || $version < 0) {
            throw InvalidRating::because('The games and the version of a rating are 0 or more.');
        }
    }

    /**
     * The rating of a member who has not played a rated game of the game yet.
     */
    public static function initial(string $game, string $member, RatingSystem $system): self
    {
        $rating = $system->initial();

        return new self($game, $member, $system->id(), $rating, $system->score($rating));
    }

    /**
     * The rating after one more rated result: the highest score kept, at the same version until the store keeps it.
     */
    public function after(Rating $rating, int $score, string $result): self
    {
        return new self($this->game, $this->member, $this->system, $rating, $score, $this->games + 1, max($this->peak ?? $score, $score), $this->visibility, $result, $this->version);
    }

    /**
     * Whether a score is above the highest one reached so far, with at least one rated game before.
     */
    public function peaksAt(int $score): bool
    {
        return null !== $this->peak && $score > $this->peak;
    }

    public function withVisibility(Visibility $visibility): self
    {
        return new self($this->game, $this->member, $this->system, $this->rating, $this->score, $this->games, $this->peak, $visibility, $this->lastResult, $this->version);
    }

    public function withVersion(int $version): self
    {
        return new self($this->game, $this->member, $this->system, $this->rating, $this->score, $this->games, $this->peak, $this->visibility, $this->lastResult, $version);
    }

    /**
     * The score as the standings show it: the rating, or the conservative estimate of a skill, to two decimals.
     */
    public function value(): float
    {
        return $this->score / 100;
    }

    /**
     * The rating as plain values; `fromArray()` reads them back with the system that computed it.
     *
     * @return array{game: string, member: string, system: string, rating: array<string, int>, score: int, games: int, peak: ?int, visibility: string, last_result: ?string, version: int}
     */
    public function toArray(): array
    {
        return [
            'game' => $this->game,
            'member' => $this->member,
            'system' => $this->system,
            'rating' => $this->rating->toArray(),
            'score' => $this->score,
            'games' => $this->games,
            'peak' => $this->peak,
            'visibility' => $this->visibility->value,
            'last_result' => $this->lastResult,
            'version' => $this->version,
        ];
    }

    /**
     * A rating as `toArray()` wrote it.
     *
     * @param array<mixed> $data
     *
     * @throws InvalidRating when a field is missing, out of its range, or of another system
     */
    public static function fromArray(array $data, RatingSystem $system): self
    {
        $values = [$data['game'] ?? null, $data['member'] ?? null, $data['system'] ?? null, $data['rating'] ?? null, $data['games'] ?? null, $data['visibility'] ?? null, $data['version'] ?? null];
        [$game, $member, $id, $rating, $games, $visibility, $version] = $values;
        $peak = $data['peak'] ?? null;
        $lastResult = $data['last_result'] ?? null;

        if (!\is_string($game) || !\is_string($member) || !\is_string($id) || !\is_array($rating) || !\is_int($games) || !\is_string($visibility) || !\is_int($version) || (null !== $peak && !\is_int($peak)) || (null !== $lastResult && !\is_string($lastResult))) {
            throw InvalidRating::because('A stored rating has a game, a member, a system, a rating, games, a visibility and a version.');
        }

        if ($id !== $system->id()) {
            throw InvalidRating::because(\sprintf('A rating of the system %s is not read by the system %s.', $id, $system->id()));
        }

        $read = $system->restore($rating);

        return new self($game, $member, $id, $read, $system->score($read), $games, $peak, Visibility::tryFrom($visibility) ?? throw InvalidRating::because('A rating is private or public.'), $lastResult, $version);
    }
}
