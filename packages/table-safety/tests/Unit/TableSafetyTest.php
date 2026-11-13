<?php

declare(strict_types=1);

namespace Tilttek\TableSafety\Tests\Unit;

use DateTimeImmutable;
use Override;
use Phil\Resource\ResourceReference;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tilttek\TableSafety\Boundary;
use Tilttek\TableSafety\ConsentChecklist;
use Tilttek\TableSafety\ConsentLevel;
use Tilttek\TableSafety\Exception\InvalidAnswer;
use Tilttek\TableSafety\Exception\NotGameMaster;
use Tilttek\TableSafety\Exception\ToolNotEnabled;
use Tilttek\TableSafety\Feedback\StarsAndWishes;
use Tilttek\TableSafety\LinesAndVeils;
use Tilttek\TableSafety\SafetyTool;
use Tilttek\TableSafety\SessionZero\Templates;
use Tilttek\TableSafety\Signal\Audience;
use Tilttek\TableSafety\Signal\Signal;
use Tilttek\TableSafety\Signal\SignalKind;
use Tilttek\TableSafety\Store\InMemoryFeedbackStore;
use Tilttek\TableSafety\Store\InMemoryParticipationStore;
use Tilttek\TableSafety\Store\InMemorySignalStore;
use Tilttek\TableSafety\Store\InMemoryTableStore;
use Tilttek\TableSafety\TableSafety;
use Tilttek\TableSafety\TableSettings;
use Tilttek\TableSafety\Tests\Unit\Fixture\Catalogues;
use Tilttek\TableSafety\Tests\Unit\Fixture\RecordingObserver;

#[CoversClass(TableSafety::class)]
#[CoversClass(ToolNotEnabled::class)]
#[CoversClass(NotGameMaster::class)]
final class TableSafetyTest extends TestCase
{
    private InMemoryParticipationStore $participations;

    private InMemorySignalStore $signals;

    private InMemoryTableStore $tables;

    private RecordingObserver $observer;

    private TableSafety $safety;

    private ResourceReference $table;

    private ResourceReference $ana;

    private ResourceReference $bo;

    private DateTimeImmutable $now;

    #[Override]
    protected function setUp(): void
    {
        $this->tables = new InMemoryTableStore();
        $this->participations = new InMemoryParticipationStore();
        $this->signals = new InMemorySignalStore();
        $this->observer = new RecordingObserver();
        $this->safety = new TableSafety(Catalogues::small(), $this->tables, $this->participations, $this->signals, new InMemoryFeedbackStore(), $this->observer);
        $this->table = ResourceReference::of('table', '7');
        $this->ana = ResourceReference::of('member', 'ana');
        $this->bo = ResourceReference::of('member', 'bo');
        $this->now = new DateTimeImmutable('2026-11-12 20:00:00 UTC');
    }

    public function testATableWithoutSettingsHasTheDefaults(): void
    {
        self::assertEquals(TableSettings::defaults(), $this->safety->settings($this->table));
    }

    public function testTheDefaultsAreGiven(): void
    {
        $defaults = TableSettings::defaults()->withTools([SafetyTool::XCard]);
        $safety = new TableSafety(Catalogues::small(), $this->tables, $this->participations, $this->signals, new InMemoryFeedbackStore(), defaults: $defaults);

        self::assertSame($defaults, $safety->settings($this->table));

        $this->tables->save($this->table, TableSettings::defaults());
        self::assertEquals(TableSettings::defaults(), $safety->settings($this->table));
    }

    public function testATableIsConfigured(): void
    {
        $settings = TableSettings::defaults()->withCustomThemes(['clowns' => 'Clowns']);
        $this->safety->configure($this->table, $settings);

        self::assertEquals($settings, $this->safety->settings($this->table));
        self::assertTrue($this->safety->themes($this->table)->has('clowns'));
        self::assertTrue($this->safety->themes($this->table)->has('gore'));
        self::assertFalse($this->safety->themes(ResourceReference::of('table', '8'))->has('clowns'));
    }

    public function testAnAddedThemeMustNotShadowTheCatalogue(): void
    {
        try {
            $this->safety->configure($this->table, TableSettings::defaults()->withCustomThemes(['gore' => 'Blood']));
            self::fail('The theme is refused.');
        } catch (InvalidAnswer) {
            self::assertNull($this->tables->settings($this->table));
        }
    }

    public function testAParticipantAnswersAndChangesTheirAnswers(): void
    {
        self::assertTrue($this->safety->participation($this->table, $this->ana, $this->now)->isEmpty());
        self::assertSame($this->now, $this->safety->participation($this->table, $this->ana, $this->now)->updatedAt);

        $later = new DateTimeImmutable('2026-11-12 21:00:00 UTC');
        $lines = $this->safety->answerLinesAndVeils($this->table, $this->ana, LinesAndVeils::none()->with('gore', Boundary::Line), $this->now);
        $consent = $this->safety->answerConsent($this->table, $this->ana, ConsentChecklist::none()->with('spiders', ConsentLevel::Yellow), $later);

        self::assertSame(['gore'], $lines->linesAndVeils->lines());
        self::assertSame(['gore'], $consent->linesAndVeils->lines());
        self::assertSame($later, $consent->updatedAt);
        self::assertEquals($consent, $this->safety->participation($this->table, $this->ana, $this->now));

        $this->safety->answerSessionZero($this->table, $this->ana, ['tone' => 'dark'], $later);
        self::assertSame('dark', $this->safety->participation($this->table, $this->ana, $this->now)->sessionZero->of('tone'));
        self::assertSame(ConsentLevel::Yellow, $this->safety->participation($this->table, $this->ana, $this->now)->consent->level('spiders'));
    }

    public function testEmptyAnswersLeaveNothingBehind(): void
    {
        $this->safety->answerLinesAndVeils($this->table, $this->ana, LinesAndVeils::none()->with('gore', Boundary::Line), $this->now);
        $this->safety->answerLinesAndVeils($this->table, $this->ana, LinesAndVeils::none(), $this->now);

        self::assertNull($this->participations->find($this->table, $this->ana));

        $this->safety->answerConsent($this->table, $this->ana, ConsentChecklist::none()->with('gore', ConsentLevel::Red), $this->now);
        self::assertNotNull($this->participations->find($this->table, $this->ana));

        $this->safety->leave($this->table, $this->ana);
        self::assertNull($this->participations->find($this->table, $this->ana));
    }

    public function testAnUnknownThemeIsRefused(): void
    {
        $this->expectException(InvalidAnswer::class);
        $this->expectExceptionMessageIsOrContains('The table does not know the theme "clowns".');

        $this->safety->answerLinesAndVeils($this->table, $this->ana, LinesAndVeils::none()->with('clowns', Boundary::Line), $this->now);
    }

    public function testAnUnknownThemeIsRefusedInTheChecklist(): void
    {
        $this->expectException(InvalidAnswer::class);

        $this->safety->answerConsent($this->table, $this->ana, ConsentChecklist::none()->with('clowns', ConsentLevel::Red), $this->now);
    }

    public function testAnAddedThemeIsAnswered(): void
    {
        $this->safety->configure($this->table, TableSettings::defaults()->withCustomThemes(['clowns' => 'Clowns']));

        self::assertSame(['clowns'], $this->safety->answerLinesAndVeils($this->table, $this->ana, LinesAndVeils::none()->with('clowns', Boundary::Line), $this->now)->linesAndVeils->lines());
    }

    public function testAToolTheTableDoesNotUseTakesNoAnswer(): void
    {
        $this->safety->configure($this->table, TableSettings::defaults()->withTools([SafetyTool::XCard]));

        foreach ([
            fn (): mixed => $this->safety->answerLinesAndVeils($this->table, $this->ana, LinesAndVeils::none(), $this->now),
            fn (): mixed => $this->safety->answerConsent($this->table, $this->ana, ConsentChecklist::none(), $this->now),
            fn (): mixed => $this->safety->answerSessionZero($this->table, $this->ana, [], $this->now),
            fn (): mixed => $this->safety->giveFeedback($this->table, 's1', $this->ana, new StarsAndWishes(['a']), $this->now),
            fn (): mixed => $this->safety->raise($this->table, $this->ana, SignalKind::Pause, false, $this->now),
        ] as $i => $answer) {
            try {
                $answer();
                self::fail('The answer is refused.');
            } catch (ToolNotEnabled $toolNotEnabled) {
                self::assertSame(\sprintf('The table does not use the tool "%s".', ['lines-and-veils', 'consent-checklist', 'session-zero', 'stars-and-wishes', 'script-change'][$i]), $toolNotEnabled->getMessage());
            }
        }
    }

    public function testTheSessionZeroIsCheckedAgainstTheTablesTemplate(): void
    {
        $this->safety->configure($this->table, TableSettings::defaults()->withSessionZero(Templates::get('online')));

        $this->expectException(InvalidAnswer::class);
        $this->expectExceptionMessageIsOrContains('The question "recording" needs an answer.');

        $this->safety->answerSessionZero($this->table, $this->ana, ['tone' => 'dark'], $this->now);
    }

    public function testTheAgreementAndThePactSayNotWho(): void
    {
        $this->safety->answerLinesAndVeils($this->table, $this->ana, LinesAndVeils::none()->with('gore', Boundary::Line), $this->now);
        $this->safety->answerConsent($this->table, $this->bo, ConsentChecklist::none()->with('spiders', ConsentLevel::Red), $this->now);
        $this->safety->answerSessionZero($this->table, $this->ana, ['tone' => 'dark', 'comfort' => 'Breaks'], $this->now);
        $this->safety->answerSessionZero($this->table, $this->bo, ['tone' => 'light'], $this->now);
        $this->safety->answerSessionZero(ResourceReference::of('table', '8'), $this->bo, ['tone' => 'light'], $this->now);

        $agreement = $this->safety->agreement($this->table);
        self::assertSame(2, $agreement->respondents);
        self::assertSame(['gore'], $agreement->lines());
        self::assertSame(ConsentLevel::Red, $agreement->consent('spiders'));

        $pact = $this->safety->pact($this->table);
        self::assertSame(2, $pact->respondents);
        self::assertSame(['light' => 1, 'mixed' => 0, 'serious' => 0, 'dark' => 1], $pact->tally('tone'));
        self::assertSame(['Breaks'], $pact->texts('comfort'));
        self::assertSame(Templates::CAMPAIGN, $pact->template->key);
    }

    public function testASignalIsAnonymousByDefault(): void
    {
        $signal = $this->safety->raise($this->table, $this->ana, SignalKind::XCard, false, $this->now);

        self::assertTrue($signal->isAnonymous());
        self::assertSame(Audience::Table, $signal->audience);
        self::assertSame(SignalKind::XCard, $signal->kind);
        self::assertSame($this->now, $signal->raisedAt);
        self::assertSame($this->table, $signal->table);
        self::assertEquals([$signal], $this->signals->recent($this->table));
        self::assertSame([['raised', $signal]], $this->observer->told);
    }

    public function testANamedSignalAtATableThatChoseThem(): void
    {
        $this->safety->configure($this->table, TableSettings::defaults()->withNamedSignals(true)->withSignalsSeenBy(Audience::GameMaster));

        $signal = $this->safety->raise($this->table, $this->ana, SignalKind::Rewind, false, $this->now);
        self::assertSame($this->ana, $signal->raisedBy);
        self::assertSame(Audience::GameMaster, $signal->audience);

        self::assertTrue($this->safety->raise($this->table, $this->ana, SignalKind::NotOkay, false, $this->now)->isAnonymous());
    }

    public function testOnlyTheGameMasterAsksForACheckIn(): void
    {
        $asked = $this->safety->raise($this->table, $this->bo, SignalKind::CheckIn, true, $this->now);
        self::assertSame(Audience::Table, $asked->audience);

        $this->expectException(NotGameMaster::class);
        $this->expectExceptionMessageIsOrContains('Only the game master raises the signal "check-in".');

        $this->safety->raise($this->table, $this->ana, SignalKind::CheckIn, false, $this->now);
    }

    public function testTheGameMasterSeesEverySignalAndTheTableItsOwn(): void
    {
        $xCard = $this->safety->raise($this->table, $this->ana, SignalKind::XCard, false, $this->now);
        $door = $this->safety->raise($this->table, $this->ana, SignalKind::OpenDoor, false, new DateTimeImmutable('2026-11-12 20:01:00 UTC'));
        $later = $this->safety->raise($this->table, $this->ana, SignalKind::Pause, false, new DateTimeImmutable('2026-11-12 20:02:00 UTC'));

        self::assertEquals([$xCard, $door, $later], $this->safety->signals($this->table, true));
        self::assertEquals([$xCard, $later], $this->safety->signals($this->table, false));
        self::assertEquals([$door, $later], $this->safety->signals($this->table, true, $this->now));
        self::assertEquals([$later], $this->safety->signals($this->table, false, $this->now));
    }

    public function testAScreenReadsTheLastSignalsOnly(): void
    {
        for ($i = 0; $i <= TableSafety::SIGNALS_LIMIT; ++$i) {
            $this->safety->raise($this->table, $this->ana, SignalKind::AllGood, false, $this->now->modify('+'.$i.' seconds'));
        }

        $signals = $this->safety->signals($this->table, true);

        self::assertCount(TableSafety::SIGNALS_LIMIT, $signals);
        self::assertEquals($this->now->modify('+1 second'), $signals[0]->raisedAt);
    }

    public function testASignalIsAcknowledgedOnce(): void
    {
        $signal = $this->safety->raise($this->table, $this->ana, SignalKind::XCard, false, $this->now);
        $at = new DateTimeImmutable('2026-11-12 20:00:30 UTC');

        self::assertTrue($this->safety->acknowledge($this->table, $signal->id, $at));
        self::assertFalse($this->safety->acknowledge($this->table, $signal->id, $at));
        self::assertFalse($this->safety->acknowledge($this->table, Signal::newId(), $at));
        self::assertEquals([['raised', $signal], ['acknowledged', $signal->acknowledge($at)]], $this->observer->told);
    }

    public function testWithoutAnObserverNothingIsTold(): void
    {
        $safety = new TableSafety(Catalogues::small(), new InMemoryTableStore(), new InMemoryParticipationStore(), new InMemorySignalStore(), new InMemoryFeedbackStore());
        $signal = $safety->raise($this->table, $this->ana, SignalKind::XCard, false, $this->now);

        self::assertTrue($safety->acknowledge($this->table, $signal->id, $this->now));
    }

    public function testTheCheckInIsCounted(): void
    {
        $this->safety->raise($this->table, $this->bo, SignalKind::CheckIn, true, $this->now);
        $this->safety->raise($this->table, $this->ana, SignalKind::Unsure, false, $this->now->modify('+1 second'));

        $tally = $this->safety->checkIn($this->table);

        self::assertSame(1, $tally->unsure);
        self::assertEquals($this->now, $tally->askedAt);
    }

    public function testStarsAndWishesAreGatheredWithoutTheirAuthors(): void
    {
        $feedback = $this->safety->giveFeedback($this->table, 's1', $this->ana, new StarsAndWishes(['the heist'], ['zeppelins']), $this->now);
        $this->safety->giveFeedback($this->table, 's1', $this->bo, new StarsAndWishes(['apples']), $this->now);

        self::assertSame('s1', $feedback->session);
        self::assertSame($this->ana, $feedback->participant);

        $summary = $this->safety->feedback($this->table, 's1');
        self::assertSame(['apples', 'the heist'], $summary->stars);
        self::assertSame(['zeppelins'], $summary->wishes);
        self::assertSame(2, $summary->respondents);
    }

    public function testAFeedbackSessionHasAKey(): void
    {
        $this->expectException(InvalidAnswer::class);

        $this->safety->feedback($this->table, 'Session 1');
    }

    public function testAParticipantIsExportedAndErased(): void
    {
        $this->safety->configure($this->table, TableSettings::defaults()->withNamedSignals(true));
        $this->safety->answerLinesAndVeils($this->table, $this->ana, LinesAndVeils::none()->with('gore', Boundary::Line), $this->now);
        $this->safety->answerConsent($this->table, $this->ana, ConsentChecklist::none()->with('spiders', ConsentLevel::Red), $this->now);
        $this->safety->answerSessionZero($this->table, $this->ana, ['tone' => 'dark'], $this->now);

        $signal = $this->safety->raise($this->table, $this->ana, SignalKind::XCard, false, $this->now);
        $this->safety->giveFeedback($this->table, 's1', $this->ana, new StarsAndWishes(['a'], ['b']), $this->now);
        $this->safety->answerLinesAndVeils($this->table, $this->bo, LinesAndVeils::none()->with('gore', Boundary::Veil), $this->now);

        self::assertSame([
            'participations' => [[
                'table' => 'table:7',
                'lines_and_veils' => ['gore' => 'line'],
                'consent' => ['spiders' => 'red'],
                'session_zero' => ['tone' => 'dark'],
                'updated_at' => '2026-11-12T20:00:00+00:00',
            ]],
            'signals' => [$signal->toArray()],
            'feedback' => [['table' => 'table:7', 'session' => 's1', 'stars' => ['a'], 'wishes' => ['b'], 'given_at' => '2026-11-12T20:00:00+00:00']],
        ], $this->safety->export($this->ana));

        $this->safety->erase($this->ana);
        $this->safety->erase($this->ana);

        self::assertSame(['participations' => [], 'signals' => [], 'feedback' => []], $this->safety->export($this->ana));
        self::assertEquals([$signal->anonymous()], $this->signals->recent($this->table));
        self::assertSame(1, $this->safety->agreement($this->table)->respondents);
    }
}
