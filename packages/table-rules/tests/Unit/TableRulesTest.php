<?php

declare(strict_types=1);

namespace Tilttek\TableRules\Tests\Unit;

use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Tilttek\TableRules\AcceptanceState;
use Tilttek\TableRules\Decision;
use Tilttek\TableRules\Event\PlayerForgotten;
use Tilttek\TableRules\Event\RulebookPublished;
use Tilttek\TableRules\Event\RulesAccepted;
use Tilttek\TableRules\Event\RulesDeclined;
use Tilttek\TableRules\Exception\InvalidRules;
use Tilttek\TableRules\Exception\RulesConflict;
use Tilttek\TableRules\Exception\RulesNotFound;
use Tilttek\TableRules\Exception\RulesRefused;
use Tilttek\TableRules\Rule;
use Tilttek\TableRules\Rulebook;
use Tilttek\TableRules\RulebookVersion;
use Tilttek\TableRules\Store\InMemoryRulebookStore;
use Tilttek\TableRules\TableRules;
use Tilttek\TableRules\Tests\Unit\Fixture\Observed;
use Tilttek\TableRules\Tests\Unit\Fixture\Variants;
use Tilttek\TableRules\View\PlayerStatus;
use Tilttek\TableRules\View\RosterView;

#[CoversClass(TableRules::class)]
#[CoversClass(InMemoryRulebookStore::class)]
#[CoversClass(PlayerStatus::class)]
#[CoversClass(RosterView::class)]
#[CoversClass(RulebookPublished::class)]
#[CoversClass(RulesAccepted::class)]
#[CoversClass(RulesDeclined::class)]
#[CoversClass(PlayerForgotten::class)]
#[CoversClass(RulesNotFound::class)]
#[CoversClass(RulesRefused::class)]
#[CoversClass(RulesConflict::class)]
final class TableRulesTest extends TestCase
{
    private MockClock $now;

    private Observed $observed;

    private InMemoryRulebookStore $store;

    private TableRules $rules;

    protected function setUp(): void
    {
        $this->store = new InMemoryRulebookStore();
        $this->now = new MockClock(new DateTimeImmutable('2027-01-01 12:00:00', new DateTimeZone('UTC')));
        $this->observed = new Observed();
        $this->rules = new TableRules($this->store, $this->now, $this->observed);
    }

    public function testATableWithoutRulesHasAnEmptyRulebook(): void
    {
        $rulebook = $this->rules->rulebook('t-1');

        self::assertSame('t-1', $rulebook->table);
        self::assertNull($rulebook->current);
        self::assertSame(0, $rulebook->revision);
    }

    public function testTheFirstVersionIsPublishedAndRequiresAcceptance(): void
    {
        $rulebook = $this->rules->publish('t-1', 'gm-1', [$this->rule('phones')], ['crits'], 'sys', 'The first rules.', requiresAcceptance: false);

        $current = $rulebook->current;
        self::assertInstanceOf(RulebookVersion::class, $current);
        self::assertSame(1, $current->number);
        self::assertTrue($current->requiresAcceptance);
        self::assertSame('gm-1', $current->publishedBy);
        self::assertSame(['crits'], $current->variants);
        self::assertSame('sys', $current->system);
        self::assertSame('The first rules.', $current->note);
        self::assertEquals($this->now->now(), $current->publishedAt);
        self::assertSame(1, $rulebook->baseline);
        self::assertSame(1, $rulebook->revision);
        self::assertSame($rulebook, $this->store->rulebook('t-1'));
        self::assertSame($current, $this->store->version('t-1', 1));
        self::assertEquals([new RulebookPublished('t-1', 1, true)], $this->observed->take());
    }

    public function testEachVersionTakesTheNextNumber(): void
    {
        $this->rules->publish('t-1', 'gm-1', [$this->rule('a')]);
        $rulebook = $this->rules->publish('t-1', 'gm-2', [$this->rule('a'), $this->rule('b')], requiresAcceptance: false);

        self::assertSame(2, $rulebook->current?->number);
        self::assertFalse($rulebook->current->requiresAcceptance);
        self::assertSame(1, $rulebook->baseline);
        self::assertSame(2, $rulebook->revision);
        self::assertEquals([new RulebookPublished('t-1', 1, true), new RulebookPublished('t-1', 2, false)], $this->observed->take());

        $rulebook = $this->rules->publish('t-1', 'gm-2', [$this->rule('b')]);

        self::assertSame(3, $rulebook->baseline);
    }

    public function testTheRulesThatHoldAreNotPublishedAgain(): void
    {
        $this->rules->publish('t-1', 'gm-1', [$this->rule('a')], ['v'], 'sys', 'One');
        $this->observed->take();

        try {
            $this->rules->publish('t-1', 'gm-2', [$this->rule('a')], ['v'], 'sys', 'Two');
            self::fail('The same rules were published.');
        } catch (RulesRefused $rulesRefused) {
            self::assertSame('The rules of the table t-1 are already those.', $rulesRefused->getMessage());
        }

        self::assertSame([], $this->observed->take());
        self::assertSame(1, $this->rules->rulebook('t-1')->revision);
    }

    public function testATableHasTheMostVersions(): void
    {
        $at = new DateTimeImmutable();
        $this->store->save(new Rulebook('t-1', new RulebookVersion(10_000, [$this->rule('a')], $at, 'gm'), 1));

        $this->expectException(RulesRefused::class);
        $this->expectExceptionMessageIsOrContains('The rules of the table t-1 have the most versions.');

        $this->rules->publish('t-1', 'gm', [$this->rule('b')]);
    }

    public function testTheLastVersionMayBePublished(): void
    {
        $this->store->save(new Rulebook('t-1', new RulebookVersion(9_999, [$this->rule('a')], new DateTimeImmutable(), 'gm'), 1));

        self::assertSame(10_000, $this->rules->publish('t-1', 'gm', [$this->rule('b')])->current?->number);
    }

    public function testTheVariantsAreCheckedAgainstTheCatalogue(): void
    {
        $rules = new TableRules($this->store, $this->now, variants: new Variants(['sys' => ['crits', 'flanking']]));

        self::assertSame(['crits', 'flanking'], $rules->publish('t-1', 'gm', [], ['flanking', 'crits'], 'sys')->current?->variants);

        $this->expectException(InvalidRules::class);
        $this->expectExceptionMessageIsOrContains('The game system offers no variant gritty.');

        $rules->publish('t-1', 'gm', [], ['crits', 'gritty'], 'sys');
    }

    public function testAnyVariantIsTakenWithoutCatalogue(): void
    {
        self::assertSame(['anything'], $this->rules->publish('t-1', 'gm', [], ['anything'])->current?->variants);
    }

    public function testAPlayerAcceptsTheVersionThatHolds(): void
    {
        $this->rules->publish('t-1', 'gm', [$this->rule('a')]);
        $this->observed->take();
        $this->now->sleep(60);

        $rulebook = $this->rules->accept('t-1', 'p-1', 1);

        $acceptance = $rulebook->acceptance('p-1');
        self::assertSame(1, $acceptance?->version);
        self::assertSame(Decision::Accepted, $acceptance->decision);
        self::assertEquals($this->now->now(), $acceptance->decidedAt);
        self::assertSame(AcceptanceState::Accepted, $rulebook->state('p-1'));
        self::assertSame(2, $rulebook->revision);
        self::assertEquals([new RulesAccepted('t-1', 'p-1', 1)], $this->observed->take());
    }

    public function testAcceptingAgainChangesNothing(): void
    {
        $this->rules->publish('t-1', 'gm', [$this->rule('a')]);
        $this->rules->accept('t-1', 'p-1', 1);

        $this->observed->take();

        $rulebook = $this->rules->accept('t-1', 'p-1', 1);

        self::assertSame(2, $rulebook->revision);
        self::assertSame([], $this->observed->take());
    }

    public function testAPlayerDeclinesThenChangesTheirMind(): void
    {
        $this->rules->publish('t-1', 'gm', [$this->rule('a')]);
        $this->observed->take();

        $rulebook = $this->rules->decline('t-1', 'p-1', 1);
        self::assertSame(AcceptanceState::Declined, $rulebook->state('p-1'));
        self::assertSame(Decision::Declined, $rulebook->acceptance('p-1')?->decision);
        self::assertEquals([new RulesDeclined('t-1', 'p-1', 1)], $this->observed->take());

        self::assertSame(2, $this->rules->decline('t-1', 'p-1', 1)->revision);
        self::assertSame([], $this->observed->take());

        $rulebook = $this->rules->accept('t-1', 'p-1', 1);
        self::assertSame(AcceptanceState::Accepted, $rulebook->state('p-1'));
        self::assertEquals([new RulesAccepted('t-1', 'p-1', 1)], $this->observed->take());
    }

    public function testAPlayerAnswersOnlyTheVersionThatHolds(): void
    {
        $this->rules->publish('t-1', 'gm', [$this->rule('a')]);
        $this->rules->publish('t-1', 'gm', [$this->rule('b')]);

        foreach ([1, 3, 0] as $version) {
            try {
                $this->rules->accept('t-1', 'p-1', $version);
                self::fail('An acceptance of another version was kept.');
            } catch (RulesRefused $exception) {
                self::assertSame(\sprintf('The version %d is not the rules of the table t-1 that hold.', $version), $exception->getMessage());
            }
        }

        self::assertNull($this->rules->rulebook('t-1')->acceptance('p-1'));
    }

    public function testNoPlayerAnswersBeforeTheFirstVersion(): void
    {
        $this->expectException(RulesNotFound::class);
        $this->expectExceptionMessageIsOrContains('The table t-1 has no rules published.');

        $this->rules->decline('t-1', 'p-1', 1);
    }

    public function testThePlayerOfADecisionIsAnIdentifier(): void
    {
        $this->rules->publish('t-1', 'gm', [$this->rule('a')]);

        $this->expectException(InvalidRules::class);
        $this->expectExceptionMessageIsOrContains('The identifier of a player');

        $this->rules->accept('t-1', 'p@x', 1);
    }

    public function testThePlayerIsCheckedBeforeTheVersion(): void
    {
        $this->rules->publish('t-1', 'gm', [$this->rule('a')]);

        $this->expectException(InvalidRules::class);
        $this->expectExceptionMessageIsOrContains('The identifier of a player');

        $this->rules->decline('t-1', 'p@x', 9);
    }

    public function testARulebookKeepsTheDecisionsOfTheMostPlayers(): void
    {
        $this->rules->publish('t-1', 'gm', [$this->rule('a')]);
        foreach (range(1, 500) as $i) {
            $this->rules->accept('t-1', 'p'.$i, 1);
        }

        self::assertSame(AcceptanceState::Declined, $this->rules->decline('t-1', 'p500', 1)->state('p500'));

        $this->expectException(RulesRefused::class);
        $this->expectExceptionMessageIsOrContains('The rules of the table t-1 keep the decisions of the most players.');

        $this->rules->accept('t-1', 'p501', 1);
    }

    public function testAVersionThatRequiresAcceptanceAsksEveryPlayerAgain(): void
    {
        $this->rules->publish('t-1', 'gm', [$this->rule('a')]);
        $this->rules->accept('t-1', 'p-yes', 1);
        $this->rules->decline('t-1', 'p-no', 1);

        $rulebook = $this->rules->publish('t-1', 'gm', [$this->rule('a'), $this->rule('b')]);

        self::assertSame(AcceptanceState::Outdated, $rulebook->state('p-yes'));
        self::assertSame(AcceptanceState::Pending, $rulebook->state('p-no'));
    }

    public function testAVersionThatDoesNotRequireAcceptanceKeepsTheDecisions(): void
    {
        $this->rules->publish('t-1', 'gm', [$this->rule('a')]);
        $this->rules->accept('t-1', 'p-yes', 1);
        $this->rules->decline('t-1', 'p-no', 1);

        $rulebook = $this->rules->publish('t-1', 'gm', [new Rule('a', 'A', 'The rule a, made clearer.')], requiresAcceptance: false);

        self::assertSame(AcceptanceState::Accepted, $rulebook->state('p-yes'));
        self::assertSame(AcceptanceState::Declined, $rulebook->state('p-no'));
        self::assertSame(1, $rulebook->acceptance('p-yes')?->version);
    }

    public function testThePublishedVersionsAreRead(): void
    {
        $this->rules->publish('t-1', 'gm', [$this->rule('a')]);
        $this->rules->publish('t-1', 'gm', [$this->rule('b')]);

        self::assertSame('a', $this->rules->version('t-1', 1)->rules[0]->id);
        self::assertSame('b', $this->rules->version('t-1', 2)->rules[0]->id);
        self::assertSame($this->rules->rulebook('t-1')->current, $this->rules->version('t-1', 2));

        foreach ([0, -1, 3] as $number) {
            try {
                $this->rules->version('t-1', $number);
                self::fail('A version not published was read.');
            } catch (RulesNotFound $exception) {
                self::assertSame(\sprintf('The rules of the table t-1 have no version %d.', $number), $exception->getMessage());
            }
        }
    }

    public function testTheCurrentVersionIsReadWithoutTheStoreOfVersions(): void
    {
        $this->store->save(new Rulebook('t-1', new RulebookVersion(4, [$this->rule('a')], new DateTimeImmutable(), 'gm'), 4));

        self::assertSame(4, $this->rules->version('t-1', 4)->number);
    }

    public function testTheDifferenceBetweenTwoVersionsIsRead(): void
    {
        $this->rules->publish('t-1', 'gm', [$this->rule('a')]);
        $this->rules->publish('t-1', 'gm', [$this->rule('a'), $this->rule('b')]);

        $diff = $this->rules->diff('t-1', 1, 2);

        self::assertSame(1, $diff->from);
        self::assertSame(2, $diff->to);
        self::assertSame('b', $diff->added[0]->id);
    }

    public function testAPlayerIsForgotten(): void
    {
        $this->rules->publish('t-1', 'gm', [$this->rule('a')]);
        $this->rules->accept('t-1', 'p-1', 1);

        $this->observed->take();

        $rulebook = $this->rules->forget('t-1', 'p-1');

        self::assertNull($rulebook->acceptance('p-1'));
        self::assertSame(3, $rulebook->revision);
        self::assertSame(1, $rulebook->current?->number);
        self::assertSame(1, $rulebook->baseline);
        self::assertEquals([new PlayerForgotten('t-1', 'p-1')], $this->observed->take());

        self::assertSame(3, $this->rules->forget('t-1', 'p-1')->revision);
        self::assertSame([], $this->observed->take());
    }

    public function testForgettingTakesAnIdentifier(): void
    {
        $this->expectException(InvalidRules::class);
        $this->expectExceptionMessageIsOrContains('The identifier of a player');

        $this->rules->forget('t-1', '');
    }

    public function testTheStatusSaysWhatChangedSinceTheVersionAnswered(): void
    {
        $this->rules->publish('t-1', 'gm', [$this->rule('a')]);
        $this->now->sleep(10);
        $this->rules->accept('t-1', 'p-1', 1);
        $decidedAt = $this->now->now();
        $this->rules->publish('t-1', 'gm', [$this->rule('a'), $this->rule('b')]);

        $status = $this->rules->status('t-1', 'p-1');

        self::assertSame('p-1', $status->player);
        self::assertSame(AcceptanceState::Outdated, $status->state);
        self::assertSame(2, $status->currentVersion);
        self::assertSame(1, $status->answeredVersion);
        self::assertSame(Decision::Accepted, $status->decision);
        self::assertEquals($decidedAt, $status->decidedAt);
        self::assertTrue($status->mustAnswer());
        self::assertSame(1, $status->changes?->from);
        self::assertSame(2, $status->changes->to);
        self::assertSame('b', $status->changes->added[0]->id);
    }

    public function testTheStatusOfAPlayerUpToDateHasNoChanges(): void
    {
        $this->rules->publish('t-1', 'gm', [$this->rule('a')]);
        $this->rules->accept('t-1', 'p-1', 1);

        $status = $this->rules->status('t-1', 'p-1');

        self::assertSame(AcceptanceState::Accepted, $status->state);
        self::assertNull($status->changes);
        self::assertFalse($status->mustAnswer());
    }

    public function testTheStatusOfAPlayerWhoNeverAnswered(): void
    {
        $status = $this->rules->status('t-1', 'p-1');

        self::assertSame(AcceptanceState::Pending, $status->state);
        self::assertNull($status->currentVersion);
        self::assertNull($status->answeredVersion);
        self::assertNull($status->decision);
        self::assertNull($status->decidedAt);
        self::assertNull($status->changes);
        self::assertTrue($status->mustAnswer());

        $this->expectException(InvalidRules::class);
        $this->rules->status('t-1', 'p 1');
    }

    public function testTheRosterSaysWhereEachPlayerStands(): void
    {
        $this->rules->publish('t-1', 'gm', [$this->rule('a')]);
        $this->rules->accept('t-1', 'p-old', 1);
        $this->rules->publish('t-1', 'gm', [$this->rule('b')]);
        $this->rules->accept('t-1', 'p-yes', 2);
        $this->rules->decline('t-1', 'p-no', 2);
        $this->rules->publish('t-1', 'gm', [$this->rule('b'), $this->rule('c')], requiresAcceptance: false);
        $this->rules->accept('t-1', 'p-old2', 1 + 2);

        $roster = $this->rules->roster('t-1', ['p-yes', 'p-old', 'p-no', 'p-new', 'p-old2']);

        self::assertSame('t-1', $roster->table);
        self::assertSame(3, $roster->currentVersion);
        self::assertSame(['p-yes', 'p-old', 'p-no', 'p-new', 'p-old2'], array_map(static fn (PlayerStatus $status): string => $status->player, $roster->players));
        self::assertSame(2, $roster->count(AcceptanceState::Accepted));
        self::assertSame(1, $roster->count(AcceptanceState::Outdated));
        self::assertSame(1, $roster->count(AcceptanceState::Declined));
        self::assertSame(1, $roster->count(AcceptanceState::Pending));
        self::assertFalse($roster->everyoneAccepted());
        self::assertSame(['c'], array_map(static fn (Rule $rule): string => $rule->id, $roster->players[0]->changes->added ?? []));
        self::assertSame(1, $roster->players[1]->changes?->from);
        self::assertSame(2, $roster->players[2]->changes?->from);
        self::assertSame($roster->players[0]->changes, $roster->players[2]->changes);
        self::assertNull($roster->players[3]->changes);
        self::assertNull($roster->players[4]->changes);
        self::assertTrue($this->rules->roster('t-1', ['p-yes', 'p-old2'])->everyoneAccepted());
        self::assertTrue($this->rules->roster('t-1', [])->everyoneAccepted());
        self::assertFalse($this->rules->roster('t-2', [])->everyoneAccepted());
    }

    public function testTheRosterIsPlainData(): void
    {
        $this->rules->publish('t-1', 'gm', [$this->rule('a')]);
        $this->rules->accept('t-1', 'p-1', 1);
        $this->rules->publish('t-1', 'gm', [$this->rule('a'), $this->rule('b')]);

        self::assertSame([
            'table' => 't-1',
            'currentVersion' => 2,
            'everyoneAccepted' => false,
            'counts' => ['pending' => 1, 'accepted' => 0, 'outdated' => 1, 'declined' => 0],
            'players' => [
                [
                    'player' => 'p-1',
                    'state' => 'outdated',
                    'mustAnswer' => true,
                    'currentVersion' => 2,
                    'answeredVersion' => 1,
                    'decision' => 'accepted',
                    'decidedAt' => '2027-01-01T12:00:00+00:00',
                    'changes' => $this->rules->diff('t-1', 1, 2)->toArray(),
                ],
                [
                    'player' => 'p-2',
                    'state' => 'pending',
                    'mustAnswer' => true,
                    'currentVersion' => 2,
                    'answeredVersion' => null,
                    'decision' => null,
                    'decidedAt' => null,
                    'changes' => null,
                ],
            ],
        ], $this->rules->roster('t-1', ['p-1', 'p-2'])->toArray());
    }

    public function testTheRosterTakesTheMostPlayersEachOnce(): void
    {
        $players = array_map(static fn (int $i): string => 'p'.$i, range(1, 500));
        self::assertCount(500, $this->rules->roster('t-1', $players)->players);

        try {
            $this->rules->roster('t-1', ['p-1', 'p-2', 'p-1']);
            self::fail('A player was given twice.');
        } catch (InvalidRules $invalidRules) {
            self::assertSame('The player p-1 is given twice.', $invalidRules->getMessage());
        }

        try {
            $this->rules->roster('t-1', ['p@1']);
            self::fail('An address was taken.');
        } catch (InvalidRules $invalidRules) {
            self::assertStringStartsWith('The identifier of a player', $invalidRules->getMessage());
        }

        $this->expectException(InvalidRules::class);
        $this->expectExceptionMessageIsOrContains('A roster holds 500 players at most.');

        $this->rules->roster('t-1', [...$players, 'p501']);
    }

    public function testTheTableIsAnIdentifier(): void
    {
        $this->expectException(InvalidRules::class);
        $this->expectExceptionMessageIsOrContains('The identifier of a table');

        $this->rules->rulebook('t@1');
    }

    public function testAStaleRulebookIsNotKept(): void
    {
        $stale = $this->rules->publish('t-1', 'gm', [$this->rule('a')]);
        $this->rules->accept('t-1', 'p-1', 1);

        try {
            $this->store->save($stale);
            self::fail('A stale rulebook was kept.');
        } catch (RulesConflict $rulesConflict) {
            self::assertSame('The rules of the table t-1 changed since they were read.', $rulesConflict->getMessage());
        }

        $this->expectException(RulesConflict::class);
        $this->store->save(new Rulebook('t-1'));
    }

    public function testAVersionIsKeptOnlyWithItsRulebook(): void
    {
        $version = new RulebookVersion(1, [$this->rule('a')], new DateTimeImmutable(), 'gm');

        try {
            $this->store->save(new Rulebook('t-1', $version, 1, [], 4), $version);
        } catch (RulesConflict) {
        }

        self::assertNull($this->store->version('t-1', 1));
        self::assertNull($this->store->rulebook('t-1'));
        self::assertNull($this->store->version('t-2', 1));
    }

    private function rule(string $id): Rule
    {
        return new Rule($id, strtoupper($id), 'The rule '.$id.'.');
    }
}
