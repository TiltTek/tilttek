<?php

declare(strict_types=1);

namespace Tilttek\GameCatalog;

use Tilttek\GameCatalog\Exception\InvalidEntry;
use Tilttek\GameCatalog\Exception\InvalidVote;
use Tilttek\GameCatalog\Internal\Identifier;

/**
 * A member's votes on an entry: for each number of players, a rating of 1 to 5 and one of the three choices; the
 * weight, from 1 for light to 5 for heavy; the minimum age; the dependence on language. A member has one ballot per
 * entry, and voting again replaces it.
 */
final readonly class Ballot
{
    public const int MAX_COUNTS = 30;

    /**
     * @param array<int, int>               $ratings    by number of players, 1 to 5
     * @param array<int, PlayerCountChoice> $choices    by number of players
     * @param ?int                          $weight     1 to 5
     * @param ?int                          $minimumAge 0 to 99
     *
     * @throws InvalidEntry when an identifier is not one
     * @throws InvalidVote  when a vote is out of its scale, a ballot holds more than 30 numbers of players, or no vote
     */
    public function __construct(
        public string $entry,
        public string $member,
        public array $ratings = [],
        public array $choices = [],
        public ?int $weight = null,
        public ?int $minimumAge = null,
        public ?LanguageDependence $languageDependence = null,
    ) {
        Identifier::check($entry, 'an entry');
        Identifier::check($member, 'a member');
        $this->checkCounts(array_keys($ratings));
        $this->checkCounts(array_keys($choices));

        foreach ($ratings as $rating) {
            $this->checkScale($rating, 'A rating of a number of players');
        }

        if (null !== $weight) {
            $this->checkScale($weight, 'The weight');
        }

        if (null !== $minimumAge && ($minimumAge < 0 || $minimumAge > Details::MAX_AGE)) {
            throw InvalidVote::because(\sprintf('The minimum age is from 0 to %d.', Details::MAX_AGE));
        }

        if ([] === $ratings && [] === $choices && null === $weight && null === $minimumAge && !$languageDependence instanceof LanguageDependence) {
            throw InvalidVote::because('A ballot holds at least one vote.');
        }
    }

    /**
     * @param list<int> $counts
     */
    private function checkCounts(array $counts): void
    {
        if (\count($counts) > self::MAX_COUNTS) {
            throw InvalidVote::because(\sprintf('A ballot votes on at most %d numbers of players.', self::MAX_COUNTS));
        }

        foreach ($counts as $count) {
            if ($count < 1 || $count > Details::MAX_PLAYERS) {
                throw InvalidVote::because(\sprintf('A number of players is from 1 to %d.', Details::MAX_PLAYERS));
            }
        }
    }

    private function checkScale(int $vote, string $what): void
    {
        if ($vote < 1 || $vote > 5) {
            throw InvalidVote::because($what.' is from 1 to 5.');
        }
    }
}
