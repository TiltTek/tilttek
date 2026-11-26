<?php

declare(strict_types=1);

namespace Tilttek\ScoreSheets\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tilttek\ScoreSheets\Entries;
use Tilttek\ScoreSheets\Exception\InvalidEntry;
use Tilttek\ScoreSheets\MatchId;
use Tilttek\ScoreSheets\ParticipantId;
use Tilttek\ScoreSheets\Team;
use Tilttek\ScoreSheets\TeamId;

#[CoversClass(Entries::class)]
#[CoversClass(Team::class)]
#[CoversClass(ParticipantId::class)]
#[CoversClass(TeamId::class)]
#[CoversClass(MatchId::class)]
#[CoversClass(InvalidEntry::class)]
final class EntriesTest extends TestCase
{
    public function testValuesAreEnteredCorrectedAndErased(): void
    {
        $sheet = LanternHarbor::sheet();
        $ada = new ParticipantId('ada');
        $entries = new Entries([$ada, new ParticipantId('42')]);

        $one = $entries->with($sheet, $ada, 'boats', 5);
        self::assertSame(5, $one->value($ada, 'boats'));
        self::assertNull($entries->value($ada, 'boats'), 'The entries given are left as they were.');
        self::assertSame(12, $one->with($sheet, $ada, 'boats', 12)->value($ada, 'boats'));
        self::assertSame(0, $one->with($sheet, $ada, 'boats', 0)->value($ada, 'boats'));
        self::assertNull($one->without($sheet, $ada, 'boats')->value($ada, 'boats'));
        self::assertSame(3, $one->with($sheet, new ParticipantId('42'), 'sets', 3)->value(new ParticipantId('42'), 'sets'));
        self::assertSame(5, $one->with($sheet, new ParticipantId('42'), 'sets', 3)->value($ada, 'boats'));
    }

    public function testASheetIsCompleteWhenEveryEnteredCategoryHasAValue(): void
    {
        $sheet = LanternHarbor::sheet();
        $ada = new ParticipantId('ada');
        $bo = new ParticipantId('bo');
        $entries = new Entries([$ada, $bo]);

        foreach (['boats', 'lanterns', 'sets', 'coins'] as $category) {
            self::assertFalse($entries->isComplete($sheet));
            $entries = $entries->with($sheet, $ada, $category, 1)->with($sheet, $bo, $category, 1);
        }

        self::assertTrue($entries->isComplete($sheet));
        self::assertFalse($entries->without($sheet, $bo, 'coins')->isComplete($sheet));
    }

    /**
     * @return iterable<string, array{string, string, int, string}>
     */
    public static function badValues(): iterable
    {
        yield 'unknown participant' => ['zed', 'boats', 1, 'The participant "zed" is not on the sheet.'];
        yield 'unknown category' => ['ada', 'ships', 1, 'The category "ships" is not one the scorer enters.'];
        yield 'computed category' => ['ada', 'harbor', 1, 'The category "harbor" is not one the scorer enters.'];
        yield 'below the minimum' => ['ada', 'boats', -1, 'The category "boats" takes a value between 0 and 12.'];
        yield 'above the maximum' => ['ada', 'boats', 13, 'The category "boats" takes a value between 0 and 12.'];
    }

    #[DataProvider('badValues')]
    public function testAValueTheSheetDoesNotTakeIsRefused(string $participant, string $category, int $value, string $message): void
    {
        $this->expectExceptionObject(InvalidEntry::because($message));

        new Entries([new ParticipantId('ada')])->with(LanternHarbor::sheet(), new ParticipantId($participant), $category, $value);
    }

    public function testOnlyAnEnteredValueIsErased(): void
    {
        $this->expectExceptionObject(InvalidEntry::because('The category "fleet" is not one the scorer enters.'));

        new Entries([new ParticipantId('ada')])->without(LanternHarbor::sheet(), new ParticipantId('ada'), 'fleet');
    }

    public function testParticipantsAndTeamsHoldTogether(): void
    {
        $players = array_map(static fn (int $index): ParticipantId => new ParticipantId('p'.$index), range(1, 100));
        $teams = array_map(static fn (int $index): Team => new Team(new TeamId('t'.$index), [$players[$index - 1]]), range(1, 50));
        $entries = new Entries($players, $teams);

        self::assertCount(100, $entries->participants);
        self::assertCount(50, $entries->teams);

        foreach ([
            'A match has 1 to 100 participants, each once.' => static fn (): Entries => new Entries([]),
            'A match has 1 to 100 participants, each once. ' => static fn (): Entries => new Entries([...$players, new ParticipantId('p101')]),
            ' A match has 1 to 100 participants, each once.' => static fn (): Entries => new Entries([new ParticipantId('a'), new ParticipantId('a')]),
            'A match has at most 50 teams.' => static fn (): Entries => new Entries($players, [...$teams, new Team(new TeamId('t51'), [$players[60]])]),
            'The team "t" is declared twice.' => static fn (): Entries => new Entries($players, [new Team(new TeamId('t'), [$players[0]]), new Team(new TeamId('t'), [$players[1]])]),
            'The member "zed" of the team "t" is a participant of no other team.' => static fn (): Entries => new Entries($players, [new Team(new TeamId('t'), [new ParticipantId('zed')])]),
            'The member "p1" of the team "u" is a participant of no other team.' => static fn (): Entries => new Entries($players, [new Team(new TeamId('t'), [$players[0]]), new Team(new TeamId('u'), [$players[0]])]),
            'The team "t" has at least one member.' => static fn (): Team => new Team(new TeamId('t'), []),
        ] as $message => $make) {
            try {
                $make();
                self::fail($message);
            } catch (InvalidEntry $invalid) {
                self::assertSame(trim($message), $invalid->getMessage());
            }
        }
    }

    public function testIdentifiersFollowTheirPattern(): void
    {
        self::assertSame('42', new ParticipantId('42')->value);
        self::assertSame('t-1', new TeamId('t-1')->value);
        self::assertSame('m.1', new MatchId('m.1')->value);

        foreach (['', '-a', 'a b', "a\n", str_repeat('a', 65)] as $bad) {
            try {
                new MatchId($bad);
                self::fail('A bad identifier was accepted.');
            } catch (InvalidEntry $invalid) {
                self::assertSame('A match identifier is 1 to 64 ASCII letters, digits, dots, underscores and hyphens, starting with a letter or a digit.', $invalid->getMessage());
            }
        }

        $this->expectExceptionObject(InvalidEntry::because('A team identifier is 1 to 64 ASCII letters, digits, dots, underscores and hyphens, starting with a letter or a digit.'));
        new TeamId('');
    }

    public function testEntriesGoToAnArrayAndBack(): void
    {
        $sheet = LanternHarbor::sheet();
        $ada = new ParticipantId('ada');
        $entries = new Entries([$ada, new ParticipantId('42')], [new Team(new TeamId('red'), [$ada, new ParticipantId('42')])])
            ->with($sheet, $ada, 'boats', 5)->with($sheet, new ParticipantId('42'), 'coins', 2)->with($sheet, $ada, 'sets', 1)->without($sheet, $ada, 'sets');
        $array = $entries->toArray();

        self::assertSame(['participants' => ['ada', '42'], 'teams' => [['id' => 'red', 'members' => ['ada', '42']]], 'values' => ['ada' => ['boats' => 5], 42 => ['coins' => 2]]], $array);
        self::assertEquals($entries, Entries::fromArray($sheet, json_decode(json_encode($array, \JSON_THROW_ON_ERROR), true, 512, \JSON_THROW_ON_ERROR)));
        self::assertSame([], new Entries([$ada])->with($sheet, $ada, 'boats', 1)->without($sheet, $ada, 'boats')->toArray()['values']);
    }

    /**
     * @return iterable<string, array{mixed, string}>
     */
    public static function badData(): iterable
    {
        $shape = 'Entries have participants, teams and values.';

        yield 'not an array' => ['x', $shape];
        yield 'no participants' => [['teams' => [], 'values' => []], $shape];
        yield 'no teams' => [['participants' => ['a'], 'values' => []], $shape];
        yield 'no values' => [['participants' => ['a'], 'teams' => []], $shape];
        yield 'participant not a string' => [['participants' => [1], 'teams' => [], 'values' => []], 'A participant is an identifier.'];
        yield 'team not an object' => [['participants' => ['a'], 'teams' => ['red'], 'values' => []], 'A team has an identifier and members.'];
        yield 'team without identifier' => [['participants' => ['a'], 'teams' => [['members' => ['a']]], 'values' => []], 'A team has an identifier and members.'];
        yield 'team without members' => [['participants' => ['a'], 'teams' => [['id' => 'red']], 'values' => []], 'A team has an identifier and members.'];
        yield 'members not a list' => [['participants' => ['a'], 'teams' => [['id' => 'red', 'members' => 'a']], 'values' => []], 'A team has an identifier and members.'];
        yield 'member not a string' => [['participants' => ['a'], 'teams' => [['id' => 'red', 'members' => [1]]], 'values' => []], 'A participant is an identifier.'];
        yield 'values of a participant not a map' => [['participants' => ['a'], 'teams' => [], 'values' => ['a' => 1]], 'The values of a participant are a map of categories.'];
        yield 'value not an integer' => [['participants' => ['a'], 'teams' => [], 'values' => ['a' => ['boats' => '1']]], 'A value is an integer.'];
        yield 'value out of bounds' => [['participants' => ['a'], 'teams' => [], 'values' => ['a' => ['boats' => 99]]], 'The category "boats" takes a value between 0 and 12.'];
    }

    public function testDataKeyedByAnythingGivesListsAgain(): void
    {
        $data = ['participants' => ['x' => 'ada', 'y' => 'bo'], 'teams' => ['r' => ['id' => 'red', 'members' => ['m' => 'ada']], 's' => ['id' => 'blue', 'members' => ['n' => 'bo']]], 'values' => []];

        $array = Entries::fromArray(LanternHarbor::sheet(), $data)->toArray();

        self::assertSame(['ada', 'bo'], $array['participants']);
        self::assertSame([['id' => 'red', 'members' => ['ada']], ['id' => 'blue', 'members' => ['bo']]], $array['teams']);
    }

    #[DataProvider('badData')]
    public function testDataThatIsNotEntriesIsRefused(mixed $data, string $message): void
    {
        $this->expectExceptionObject(InvalidEntry::because($message));

        Entries::fromArray(LanternHarbor::sheet(), $data);
    }
}
