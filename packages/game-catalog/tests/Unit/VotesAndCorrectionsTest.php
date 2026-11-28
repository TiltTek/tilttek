<?php

declare(strict_types=1);

namespace Tilttek\GameCatalog\Tests\Unit;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tilttek\GameCatalog\Ballot;
use Tilttek\GameCatalog\Correction;
use Tilttek\GameCatalog\CorrectionStatus;
use Tilttek\GameCatalog\Details;
use Tilttek\GameCatalog\Entry;
use Tilttek\GameCatalog\Exception\CatalogRefused;
use Tilttek\GameCatalog\Exception\InvalidEntry;
use Tilttek\GameCatalog\Exception\InvalidVote;
use Tilttek\GameCatalog\Field;
use Tilttek\GameCatalog\LanguageDependence;
use Tilttek\GameCatalog\Link;
use Tilttek\GameCatalog\LinkType;
use Tilttek\GameCatalog\Origin;
use Tilttek\GameCatalog\PlayerCountChoice;
use Tilttek\GameCatalog\PlayerCountResult;
use Tilttek\GameCatalog\PollResults;
use Tilttek\GameCatalog\Sheet;

#[CoversClass(Correction::class)]
#[CoversClass(Ballot::class)]
#[CoversClass(PlayerCountResult::class)]
#[CoversClass(PollResults::class)]
#[CoversClass(Sheet::class)]
#[CoversClass(InvalidVote::class)]
final class VotesAndCorrectionsTest extends TestCase
{
    public function testACorrectionKeepsTheFieldsTheMemberChanged(): void
    {
        $at = new DateTimeImmutable('2027-02-01 10:00 UTC');
        $proposed = DetailsTest::full()->merge(DetailsTest::other(), [Field::Year, Field::Name]);
        $correction = new Correction('c-1', 'e-1', 'm-1', DetailsTest::full(), $proposed, $at);

        self::assertSame([Field::Name, Field::Year], $correction->fields);
        self::assertSame('c-1', $correction->id);
        self::assertSame('e-1', $correction->entry);
        self::assertSame('m-1', $correction->member);
        self::assertSame($at, $correction->proposedAt);
        self::assertSame(CorrectionStatus::Pending, $correction->status);
        self::assertTrue($correction->isPending());
        self::assertNull($correction->decidedBy);
        self::assertNull($correction->decidedAt);
        self::assertNull($correction->note);
        self::assertSame(0, $correction->version);

        $decidedAt = new DateTimeImmutable('2027-02-02 10:00 UTC');
        $refused = $correction->withVersion(3)->decided(CorrectionStatus::Refused, 'admin', $decidedAt, " Already right.\r\nSee p. 2 ");

        self::assertSame(CorrectionStatus::Refused, $refused->status);
        self::assertFalse($refused->isPending());
        self::assertFalse($correction->decided(CorrectionStatus::Accepted, 'admin', $decidedAt)->isPending());
        self::assertSame('admin', $refused->decidedBy);
        self::assertSame($decidedAt, $refused->decidedAt);
        self::assertSame("Already right.\nSee p. 2", $refused->note);
        self::assertSame(3, $refused->version);
        self::assertSame($proposed, $refused->proposed);
        self::assertSame($at, $refused->proposedAt);
        self::assertSame(5, $refused->withVersion(5)->version);
        self::assertSame("Already right.\nSee p. 2", $refused->withVersion(5)->note);
        self::assertSame('admin', $refused->withVersion(5)->decidedBy);
    }

    public function testACorrectionChangesSomething(): void
    {
        $this->expectException(CatalogRefused::class);
        $this->expectExceptionMessageIs('The correction changes nothing of the entry.');
        new Correction('c-1', 'e-1', 'm-1', DetailsTest::full(), DetailsTest::full(), new DateTimeImmutable());
    }

    /**
     * @return iterable<string, array{string, string, string, ?string, string}>
     */
    public static function badCorrections(): iterable
    {
        yield 'the correction' => ['1', 'e-1', 'm-1', null, 'The identifier of a correction is 1 to 128 letters, digits, "_", ".", ":", "@" or "-", starting with a letter.'];
        yield 'the entry' => ['c-1', '1', 'm-1', null, 'The identifier of an entry is 1 to 128 letters, digits, "_", ".", ":", "@" or "-", starting with a letter.'];
        yield 'the member' => ['c-1', 'e-1', '1', null, 'The identifier of a member is 1 to 128 letters, digits, "_", ".", ":", "@" or "-", starting with a letter.'];
        yield 'the decider' => ['c-1', 'e-1', 'm-1', '1', 'The identifier of a member is 1 to 128 letters, digits, "_", ".", ":", "@" or "-", starting with a letter.'];
    }

    #[DataProvider('badCorrections')]
    public function testTheIdentifiersOfACorrectionAreIdentifiers(string $id, string $entry, string $member, ?string $by, string $message): void
    {
        $this->expectException(InvalidEntry::class);
        $this->expectExceptionMessageIs($message);
        new Correction($id, $entry, $member, DetailsTest::full(), DetailsTest::other(), new DateTimeImmutable(), decidedBy: $by);
    }

    public function testTheNoteOfACorrectionIsBounded(): void
    {
        $this->expectExceptionMessageIs('The note of a correction is 1 to 1000 characters.');
        new Correction('c-1', 'e-1', 'm-1', DetailsTest::full(), DetailsTest::other(), new DateTimeImmutable(), note: str_repeat('n', 1_001));
    }

    public function testABallotKeepsItsVotes(): void
    {
        $ballot = new Ballot('e-1', 'm-1', [1 => 1, 999 => 5], [4 => PlayerCountChoice::Best], 5, 99, LanguageDependence::None);

        self::assertSame('e-1', $ballot->entry);
        self::assertSame('m-1', $ballot->member);
        self::assertSame([1 => 1, 999 => 5], $ballot->ratings);
        self::assertSame([4 => PlayerCountChoice::Best], $ballot->choices);
        self::assertSame(5, $ballot->weight);
        self::assertSame(99, $ballot->minimumAge);
        self::assertSame(LanguageDependence::None, $ballot->languageDependence);
        self::assertSame(1, new Ballot('e-1', 'm-1', weight: 1)->weight);
        self::assertSame(0, new Ballot('e-1', 'm-1', minimumAge: 0)->minimumAge);
        self::assertSame(LanguageDependence::Some, new Ballot('e-1', 'm-1', languageDependence: LanguageDependence::Some)->languageDependence);
        self::assertCount(30, new Ballot('e-1', 'm-1', array_fill_keys(range(1, 30), 3), array_fill_keys(range(1, 30), PlayerCountChoice::Recommended))->ratings);
    }

    /**
     * @return iterable<string, array{callable(): Ballot, string}>
     */
    public static function badBallots(): iterable
    {
        yield 'no vote' => [static fn (): Ballot => new Ballot('e-1', 'm-1'), 'A ballot holds at least one vote.'];
        yield 'a rating of 0' => [static fn (): Ballot => new Ballot('e-1', 'm-1', [2 => 0]), 'A rating of a number of players is from 1 to 5.'];
        yield 'a rating of 6' => [static fn (): Ballot => new Ballot('e-1', 'm-1', [2 => 6]), 'A rating of a number of players is from 1 to 5.'];
        yield 'a weight of 0' => [static fn (): Ballot => new Ballot('e-1', 'm-1', weight: 0), 'The weight is from 1 to 5.'];
        yield 'a weight of 6' => [static fn (): Ballot => new Ballot('e-1', 'm-1', weight: 6), 'The weight is from 1 to 5.'];
        yield 'a negative age' => [static fn (): Ballot => new Ballot('e-1', 'm-1', minimumAge: -1), 'The minimum age is from 0 to 99.'];
        yield 'an age of 100' => [static fn (): Ballot => new Ballot('e-1', 'm-1', minimumAge: 100), 'The minimum age is from 0 to 99.'];
        yield 'no player rated' => [static fn (): Ballot => new Ballot('e-1', 'm-1', [0 => 3]), 'A number of players is from 1 to 999.'];
        yield 'a thousand players chosen' => [static fn (): Ballot => new Ballot('e-1', 'm-1', choices: [1_000 => PlayerCountChoice::Best]), 'A number of players is from 1 to 999.'];
        yield 'too many ratings' => [static fn (): Ballot => new Ballot('e-1', 'm-1', array_fill_keys(range(1, 31), 3)), 'A ballot votes on at most 30 numbers of players.'];
        yield 'too many choices' => [static fn (): Ballot => new Ballot('e-1', 'm-1', choices: array_fill_keys(range(1, 31), PlayerCountChoice::Best)), 'A ballot votes on at most 30 numbers of players.'];
    }

    /**
     * @param callable(): Ballot $ballot
     */
    #[DataProvider('badBallots')]
    public function testEachVoteIsOnItsScale(callable $ballot, string $message): void
    {
        $this->expectException(InvalidVote::class);
        $this->expectExceptionMessageIs($message);
        $ballot();
    }

    public function testTheIdentifiersOfABallotAreIdentifiers(): void
    {
        $this->expectExceptionMessageIs('The identifier of a member is 1 to 128 letters, digits, "_", ".", ":", "@" or "-", starting with a letter.');
        new Ballot('e-1', 'm 1', weight: 3);
    }

    public function testTheEntryOfABallotIsAnIdentifier(): void
    {
        $this->expectException(InvalidEntry::class);
        new Ballot('e 1', 'm-1', weight: 3);
    }

    public function testNoBallotMeansNoResult(): void
    {
        $results = PollResults::count([]);

        self::assertSame(0, $results->voters);
        self::assertSame([], $results->playerCounts);
        self::assertNull($results->playerCount(4));
        self::assertSame([], $results->topPlayerCounts());
        self::assertSame([], $results->recommendedPlayerCounts());
        self::assertSame([], $results->bestByChoice());
        self::assertSame([], $results->recommendedByChoice());
        self::assertNull($results->weight());
        self::assertNull($results->minimumAge());
        self::assertNull($results->languageDependence());
    }

    public function testTheBallotsAreCounted(): void
    {
        $results = PollResults::count([
            new Ballot('e-1', 'a', [4 => 5, 3 => 4, 2 => 2], [4 => PlayerCountChoice::Best, 3 => PlayerCountChoice::Recommended, 2 => PlayerCountChoice::NotRecommended], 3, 12, LanguageDependence::Moderate),
            new Ballot('e-1', 'b', [4 => 4, 3 => 3, 5 => 3], [4 => PlayerCountChoice::Best, 3 => PlayerCountChoice::Best, 5 => PlayerCountChoice::NotRecommended], 4, 10, LanguageDependence::Moderate),
            new Ballot('e-1', 'c', [2 => 3], [3 => PlayerCountChoice::Recommended], minimumAge: 14, languageDependence: LanguageDependence::Extensive),
            new Ballot('e-1', 'd', choices: [6 => PlayerCountChoice::Recommended], minimumAge: 8),
        ]);

        self::assertSame(4, $results->voters);
        self::assertSame([2, 3, 4, 5, 6], array_keys($results->playerCounts));
        self::assertSame(450, $results->playerCount(4)?->averageRating());
        self::assertSame(2, $results->playerCount(4)->ratingVotes);
        self::assertSame(9, $results->playerCount(4)->ratingSum);
        self::assertSame(2, $results->playerCount(4)->best);
        self::assertSame(350, $results->playerCount(3)?->averageRating());
        self::assertSame(1, $results->playerCount(3)->best);
        self::assertSame(2, $results->playerCount(3)->recommended);
        self::assertSame(250, $results->playerCount(2)?->averageRating());
        self::assertSame(1, $results->playerCount(2)->notRecommended);
        self::assertNull($results->playerCount(6)?->averageRating());
        self::assertSame([4], $results->topPlayerCounts());
        self::assertSame([3, 4, 5], $results->recommendedPlayerCounts());
        self::assertSame([4], $results->bestByChoice());
        self::assertSame([3, 4, 6], $results->recommendedByChoice());
        self::assertSame(2, $results->weightVotes);
        self::assertSame(7, $results->weightSum);
        self::assertSame(350, $results->weight());
        self::assertSame([8, 10, 12, 14], $results->ages);
        self::assertSame(10, $results->minimumAge());
        self::assertSame(LanguageDependence::Moderate, $results->languageDependence());
        self::assertSame(['moderate' => 2, 'extensive' => 1], $results->languageDependence);
    }

    public function testTheThresholdsOfTheRatingsAreIncluded(): void
    {
        $results = PollResults::count([
            new Ballot('e-1', 'a', [1 => 4, 2 => 3, 3 => 2]),
            new Ballot('e-1', 'b', [1 => 4, 2 => 3, 3 => 5]),
        ]);

        self::assertSame([1], $results->topPlayerCounts());
        self::assertSame([1, 2, 3], $results->recommendedPlayerCounts());
    }

    public function testTheMedianAgeIsTheMiddleVote(): void
    {
        self::assertSame(12, PollResults::count([new Ballot('e', 'a', minimumAge: 12)])->minimumAge());
        self::assertSame(10, PollResults::count([
            new Ballot('e', 'a', minimumAge: 14),
            new Ballot('e', 'b', minimumAge: 8),
            new Ballot('e', 'c', minimumAge: 10),
        ])->minimumAge());
    }

    public function testATieOfLanguageDependenceGoesToTheHigherLevel(): void
    {
        self::assertSame(LanguageDependence::Unplayable, PollResults::count([
            new Ballot('e', 'a', languageDependence: LanguageDependence::Unplayable),
            new Ballot('e', 'b', languageDependence: LanguageDependence::None),
        ])->languageDependence());
        self::assertSame(LanguageDependence::None, PollResults::count([
            new Ballot('e', 'a', languageDependence: LanguageDependence::Some),
            new Ballot('e', 'b', languageDependence: LanguageDependence::None),
            new Ballot('e', 'c', languageDependence: LanguageDependence::None),
        ])->languageDependence());
        self::assertSame(LanguageDependence::None, PollResults::count([
            new Ballot('e', 'a', languageDependence: LanguageDependence::None),
        ])->languageDependence());
    }

    /**
     * @return iterable<string, array{int, int, int, ?PlayerCountChoice}>
     */
    public static function choices(): iterable
    {
        yield 'none' => [0, 0, 0, null];
        yield 'best alone' => [1, 0, 0, PlayerCountChoice::Best];
        yield 'recommended alone' => [0, 1, 0, PlayerCountChoice::Recommended];
        yield 'not recommended alone' => [0, 0, 1, PlayerCountChoice::NotRecommended];
        yield 'best over recommended' => [3, 2, 1, PlayerCountChoice::Best];
        yield 'a tie of best and recommended' => [2, 2, 1, PlayerCountChoice::Recommended];
        yield 'a tie of recommended and not recommended' => [1, 2, 2, PlayerCountChoice::NotRecommended];
        yield 'a tie of best and not recommended' => [2, 1, 2, PlayerCountChoice::NotRecommended];
        yield 'recommended over not recommended' => [0, 3, 2, PlayerCountChoice::Recommended];
        yield 'best over not recommended' => [3, 0, 2, PlayerCountChoice::Best];
    }

    #[DataProvider('choices')]
    public function testTheChoiceOfANumberOfPlayersIsTheMostGivenOrTheMoreCautious(int $best, int $recommended, int $notRecommended, ?PlayerCountChoice $choice): void
    {
        $result = new PlayerCountResult(3, best: $best, recommended: $recommended, notRecommended: $notRecommended);

        self::assertSame($choice, $result->choice());
        self::assertSame($best + $recommended + $notRecommended, $result->choiceVotes());
    }

    public function testAResultAddsVotesOneByOne(): void
    {
        $result = new PlayerCountResult(3)->withRating(4)->withChoice(PlayerCountChoice::NotRecommended)->withRating(1)->withChoice(PlayerCountChoice::Recommended)->withChoice(PlayerCountChoice::Best);

        self::assertSame(3, $result->count);
        self::assertSame(2, $result->ratingVotes);
        self::assertSame(5, $result->ratingSum);
        self::assertSame(250, $result->averageRating());
        self::assertSame(1, $result->best);
        self::assertSame(1, $result->recommended);
        self::assertSame(1, $result->notRecommended);
    }

    public function testASheetShowsTheEntryItsVotesAndItsLinks(): void
    {
        $entry = new Entry('e-1', DetailsTest::full(), Origin::manual());
        $expands = new Link('e-2', LinkType::Expands, 'e-1');
        $edition = new Link('e-1', LinkType::EditionOf, 'e-3');
        $results = PollResults::count([]);
        $sheet = new Sheet($entry, $results, [$expands, $edition]);

        self::assertSame($entry, $sheet->entry);
        self::assertSame($results, $sheet->results);
        self::assertSame($entry->details(), $sheet->details());
        self::assertSame([$expands, $edition], $sheet->links);
        self::assertSame([$edition], $sheet->linksOf(LinkType::EditionOf));
        self::assertSame([], $sheet->linksOf(LinkType::Requires));
        self::assertInstanceOf(Details::class, $sheet->details());
    }
}
