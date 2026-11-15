<?php

declare(strict_types=1);

namespace Tilttek\TableSafety\Tests\Unit;

use DateTimeImmutable;
use Phil\ContentWarnings\Display;
use Phil\Resource\ResourceReference;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tilttek\TableSafety\AgreementSummary;
use Tilttek\TableSafety\Boundary;
use Tilttek\TableSafety\ConsentChecklist;
use Tilttek\TableSafety\ConsentLevel;
use Tilttek\TableSafety\Exception\InvalidAnswer;
use Tilttek\TableSafety\LinesAndVeils;
use Tilttek\TableSafety\Participation;
use Tilttek\TableSafety\RespondentThreshold;
use Tilttek\TableSafety\SessionZero\Answers;
use Tilttek\TableSafety\TableAgreement;

#[CoversClass(TableAgreement::class)]
#[CoversClass(AgreementSummary::class)]
#[CoversClass(RespondentThreshold::class)]
#[CoversClass(Participation::class)]
final class TableAgreementTest extends TestCase
{
    public function testTheMostRestrictiveAnswerWins(): void
    {
        $agreement = TableAgreement::of([
            $this->participation('ana', LinesAndVeils::none()->with('gore', Boundary::Veil)->with('spiders', Boundary::Line), ConsentChecklist::none()->with('torture', ConsentLevel::Yellow)),
            $this->participation('bo', LinesAndVeils::none()->with('gore', Boundary::Line)->with('clowns', Boundary::Veil), ConsentChecklist::none()->with('torture', ConsentLevel::Red)->with('war', ConsentLevel::Green)),
            $this->participation('cy', LinesAndVeils::none()->with('gore', Boundary::Veil)->with('spiders', Boundary::Veil), ConsentChecklist::none()->with('torture', ConsentLevel::Green)->with('war', ConsentLevel::Yellow)),
            $this->participation('di', LinesAndVeils::none(), ConsentChecklist::none()),
        ]);

        self::assertSame(3, $agreement->respondents);
        self::assertSame(['gore', 'spiders'], $agreement->lines());
        self::assertSame(['clowns'], $agreement->veils());
        self::assertSame(Boundary::Line, $agreement->boundary('gore'));
        self::assertNull($agreement->boundary('war'));
        self::assertSame(ConsentLevel::Red, $agreement->consent('torture'));
        self::assertSame(ConsentLevel::Yellow, $agreement->consent('war'));
        self::assertNull($agreement->consent('gore'));
        self::assertSame(['torture' => ConsentLevel::Red, 'war' => ConsentLevel::Yellow], $agreement->checklist());
        self::assertSame(['war'], $agreement->themesAt(ConsentLevel::Yellow));
        self::assertSame([], $agreement->themesAt(ConsentLevel::Green));
    }

    public function testTheOrderOfTheAnswersDoesNotMatter(): void
    {
        $first = $this->participation('ana', LinesAndVeils::none()->with('gore', Boundary::Line), ConsentChecklist::none()->with('war', ConsentLevel::Red));
        $second = $this->participation('bo', LinesAndVeils::none()->with('gore', Boundary::Veil), ConsentChecklist::none()->with('war', ConsentLevel::Green));

        self::assertEquals(TableAgreement::of([$first, $second]), TableAgreement::of([$second, $first]));
        self::assertSame(Boundary::Line, TableAgreement::of([$second, $first])->boundary('gore'));
        self::assertSame(ConsentLevel::Red, TableAgreement::of([$second, $first])->consent('war'));
    }

    public function testAParticipantWithOnlyAChecklistAnswered(): void
    {
        $agreement = TableAgreement::of([
            $this->participation('ana', LinesAndVeils::none(), ConsentChecklist::none()->with('war', ConsentLevel::Green)),
            $this->participation('bo', LinesAndVeils::none()->with('war', Boundary::Veil), ConsentChecklist::none()),
        ], 2);

        self::assertSame(2, $agreement->respondents);
        self::assertSame(0, TableAgreement::of([])->respondents);
    }

    public function testTheThemesAreInKeyOrder(): void
    {
        $agreement = TableAgreement::of([
            $this->participation('ana', LinesAndVeils::none()->with('war', Boundary::Line), ConsentChecklist::none()->with('war', ConsentLevel::Red)),
            $this->participation('bo', LinesAndVeils::none()->with('gore', Boundary::Line), ConsentChecklist::none()->with('gore', ConsentLevel::Red)),
        ]);

        self::assertSame(['gore', 'war'], $agreement->lines());
        self::assertSame(['gore', 'war'], array_keys($agreement->checklist()));
    }

    public function testItStandsForThePreferencesOfTheTable(): void
    {
        $preferences = TableAgreement::of([
            $this->participation('ana', LinesAndVeils::none()->with('gore', Boundary::Veil)->with('spiders', Boundary::Veil), ConsentChecklist::none()->with('gore', ConsentLevel::Red)->with('war', ConsentLevel::Yellow)->with('spiders', ConsentLevel::Green)->with('fire', ConsentLevel::Green)),
            $this->participation('bo', LinesAndVeils::none()->with('torture', Boundary::Line), ConsentChecklist::none()),
        ])->preferences();

        self::assertSame(Display::Show, $preferences->default);
        self::assertSame(Display::Hide, $preferences->theme('gore')?->display);
        self::assertSame(Display::Fold, $preferences->theme('spiders')?->display);
        self::assertSame(Display::Hide, $preferences->theme('torture')?->display);
        self::assertSame(Display::Fold, $preferences->theme('war')?->display);
        self::assertNull($preferences->theme('fire'));
    }

    public function testAParticipationWithoutAFirstAnswerStartsAtItsLastChange(): void
    {
        $participation = $this->participation('ana', LinesAndVeils::none(), ConsentChecklist::none());

        self::assertSame($participation->updatedAt, $participation->firstAnsweredAt);
    }

    public function testAParticipationChangesAnswerByAnswer(): void
    {
        $table = ResourceReference::of('table', '7');
        $participant = ResourceReference::of('member', 'ana');
        $then = new DateTimeImmutable('2026-11-12 10:00:00');
        $later = new DateTimeImmutable('2026-11-12 11:00:00');
        $empty = Participation::empty($table, $participant, $then);

        self::assertTrue($empty->isEmpty());
        self::assertSame($then, $empty->updatedAt);
        self::assertSame($then, $empty->firstAnsweredAt);
        self::assertSame($table, $empty->table);
        self::assertSame($participant, $empty->participant);

        $lines = $empty->withLinesAndVeils(LinesAndVeils::none()->with('gore', Boundary::Line), $later);
        self::assertFalse($lines->isEmpty());
        self::assertSame($later, $lines->updatedAt);
        self::assertSame($then, $lines->firstAnsweredAt);
        self::assertSame(['gore'], $lines->linesAndVeils->lines());
        self::assertTrue($lines->consent->isEmpty());

        $consent = $empty->withConsent(ConsentChecklist::none()->with('war', ConsentLevel::Red), $later);
        self::assertFalse($consent->isEmpty());
        self::assertSame($later, $consent->updatedAt);
        self::assertSame($then, $consent->firstAnsweredAt);
        self::assertTrue($consent->linesAndVeils->isEmpty());

        $zero = $lines->withSessionZero(new Answers(['tone' => 'dark']), $later)->withConsent(ConsentChecklist::none()->with('war', ConsentLevel::Red), $later);
        self::assertSame('dark', $zero->sessionZero->of('tone'));
        self::assertSame($then, $zero->firstAnsweredAt);
        self::assertSame($then, $lines->withSessionZero(new Answers(['tone' => 'dark']), $later)->firstAnsweredAt);
        self::assertSame(['gore'], $zero->linesAndVeils->lines());
        self::assertSame(ConsentLevel::Red, $zero->consent->level('war'));
        self::assertFalse($empty->withSessionZero(new Answers(['tone' => 'dark']), $later)->isEmpty());
        self::assertSame(['gore'], $lines->withSessionZero(Answers::none(), $later)->linesAndVeils->lines());
        self::assertSame(ConsentLevel::Red, $consent->withLinesAndVeils(LinesAndVeils::none(), $later)->consent->level('war'));
    }

    public function testUnderTheThresholdNothingIsShownAndEveryAnswerStillProtects(): void
    {
        $agreement = TableAgreement::of([
            $this->participation('ana', LinesAndVeils::none()->with('gore', Boundary::Line), ConsentChecklist::none()->with('war', ConsentLevel::Yellow)),
            $this->participation('bo', LinesAndVeils::none()->with('spiders', Boundary::Veil), ConsentChecklist::none()->with('torture', ConsentLevel::Red)),
        ]);

        self::assertSame(0, $agreement->respondents);
        self::assertSame(3, $agreement->threshold);
        self::assertFalse($agreement->hasEnoughAnswers());
        self::assertFalse($agreement->shown()->hasEnoughAnswers());
        self::assertSame([], $agreement->shown()->lines());
        self::assertSame([], $agreement->shown()->veils());
        self::assertSame([], $agreement->shown()->checklist());
        self::assertSame([], $agreement->shown()->boundaries());

        self::assertSame(['gore'], $agreement->lines());
        self::assertSame(['spiders'], $agreement->veils());
        self::assertSame(Boundary::Line, $agreement->boundary('gore'));
        self::assertSame(ConsentLevel::Red, $agreement->consent('torture'));
        self::assertSame(['torture' => ConsentLevel::Red, 'war' => ConsentLevel::Yellow], $agreement->checklist());
        self::assertSame(['war'], $agreement->themesAt(ConsentLevel::Yellow));
        self::assertSame(Display::Hide, $agreement->preferences()->theme('gore')?->display);
        self::assertSame(Display::Fold, $agreement->preferences()->theme('spiders')?->display);
        self::assertSame(Display::Hide, $agreement->preferences()->theme('torture')?->display);
    }

    public function testASingleAnswerProtectsTheTable(): void
    {
        $agreement = TableAgreement::of([$this->participation('ana', LinesAndVeils::none()->with('gore', Boundary::Veil), ConsentChecklist::none())]);

        self::assertSame(['gore'], $agreement->veils());
        self::assertSame(Display::Fold, $agreement->preferences()->theme('gore')?->display);
        self::assertSame([], $agreement->shown()->veils());
    }

    public function testOneMoreAnswerPastTheThresholdChangesNothingShown(): void
    {
        $given = [
            $this->participation('ana', LinesAndVeils::none()->with('gore', Boundary::Veil), ConsentChecklist::none()),
            $this->participation('bo', LinesAndVeils::none(), ConsentChecklist::none()->with('war', ConsentLevel::Green)),
            $this->participation('empty', LinesAndVeils::none(), ConsentChecklist::none()),
            $this->participation('cy', LinesAndVeils::none()->with('gore', Boundary::Veil), ConsentChecklist::none()),
            $this->participation('di', LinesAndVeils::none()->with('gore', Boundary::Line), ConsentChecklist::none()->with('war', ConsentLevel::Red)),
            $this->participation('ed', LinesAndVeils::none(), ConsentChecklist::none()->with('war', ConsentLevel::Green)),
            $this->participation('fa', LinesAndVeils::none()->with('spiders', Boundary::Veil), ConsentChecklist::none()),
        ];
        $agreements = [];

        for ($answers = 1; $answers <= \count($given); ++$answers) {
            $agreements[$answers] = TableAgreement::of(\array_slice($given, 0, $answers));
        }

        self::assertFalse($agreements[3]->hasEnoughAnswers());
        self::assertSame(3, $agreements[4]->respondents);
        self::assertSame(['gore'], $agreements[4]->shown()->veils());
        self::assertSame(['war' => ConsentLevel::Green], $agreements[4]->shown()->checklist());
        self::assertEquals($agreements[4]->shown(), $agreements[5]->shown());
        self::assertEquals($agreements[4]->shown(), $agreements[6]->shown());
        self::assertSame(6, $agreements[7]->respondents);
        self::assertSame(['gore'], $agreements[7]->shown()->lines());
        self::assertSame(['spiders'], $agreements[7]->shown()->veils());
        self::assertSame(ConsentLevel::Red, $agreements[7]->shown()->consent('war'));

        self::assertSame(['gore'], $agreements[5]->lines());
        self::assertSame(ConsentLevel::Red, $agreements[5]->consent('war'));
    }

    public function testTheThresholdIsChosen(): void
    {
        $alone = TableAgreement::of([$this->participation('ana', LinesAndVeils::none()->with('gore', Boundary::Line), ConsentChecklist::none())], 1);

        self::assertSame(1, $alone->respondents);
        self::assertSame(1, $alone->threshold);
        self::assertSame(1, $alone->shown()->threshold);
        self::assertSame(['gore'], $alone->shown()->lines());

        $this->expectException(InvalidAnswer::class);
        $this->expectExceptionMessageIsOrContains('A summary waits for at least 1 answer, not 0.');

        TableAgreement::of([], 0);
    }

    private function participation(string $who, LinesAndVeils $linesAndVeils, ConsentChecklist $consent): Participation
    {
        return new Participation(ResourceReference::of('table', '7'), ResourceReference::of('member', $who), $linesAndVeils, $consent, Answers::none(), new DateTimeImmutable('2026-11-12'));
    }
}
