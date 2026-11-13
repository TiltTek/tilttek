<?php

declare(strict_types=1);

namespace Tilttek\TableSafety\Tests\Unit;

use DateTimeImmutable;
use Phil\ContentWarnings\Catalogue;
use Phil\ContentWarnings\Category;
use Phil\ContentWarnings\LocalizedText;
use Phil\ContentWarnings\Theme;
use Phil\Resource\ResourceReference;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tilttek\TableSafety\Boundary;
use Tilttek\TableSafety\ConsentChecklist;
use Tilttek\TableSafety\ConsentLevel;
use Tilttek\TableSafety\Exception\InvalidAnswer;
use Tilttek\TableSafety\Feedback\Feedback;
use Tilttek\TableSafety\Feedback\StarsAndWishes;
use Tilttek\TableSafety\LinesAndVeils;
use Tilttek\TableSafety\Participation;
use Tilttek\TableSafety\SessionZero\Answers;
use Tilttek\TableSafety\SessionZero\Pact;
use Tilttek\TableSafety\SessionZero\Question;
use Tilttek\TableSafety\SessionZero\QuestionKind;
use Tilttek\TableSafety\SessionZero\Template;
use Tilttek\TableSafety\Signal\Audience;
use Tilttek\TableSafety\Signal\CheckInTally;
use Tilttek\TableSafety\Signal\Signal;
use Tilttek\TableSafety\Signal\SignalKind;
use Tilttek\TableSafety\Store\InMemorySignalStore;
use Tilttek\TableSafety\TableAgreement;
use Tilttek\TableSafety\TableThemes;
use Tilttek\TableSafety\Tests\Unit\Fixture\Catalogues;

/**
 * The edges the other tests step over: the bounds of a message, the order of two lists, a default, a sum.
 */
#[CoversClass(InvalidAnswer::class)]
#[CoversClass(ConsentLevel::class)]
#[CoversClass(Feedback::class)]
#[CoversClass(StarsAndWishes::class)]
#[CoversClass(Pact::class)]
#[CoversClass(Question::class)]
#[CoversClass(CheckInTally::class)]
#[CoversClass(InMemorySignalStore::class)]
#[CoversClass(TableAgreement::class)]
#[CoversClass(TableThemes::class)]
final class EdgeCasesTest extends TestCase
{
    public function testARefusedValueIsQuotedOnItsFirstEightyCharacters(): void
    {
        self::assertSame(str_repeat('é', 80), InvalidAnswer::excerpt(str_repeat('é', 200)));
        self::assertSame(str_repeat('é', 79), InvalidAnswer::excerpt(str_repeat('é', 79)));
        self::assertSame(str_repeat('é', 80), InvalidAnswer::excerpt(str_repeat('é', 80)));
        self::assertSame('', InvalidAnswer::excerpt(''));
    }

    public function testEveryLevelIsStricterThanTheOnesBelowIt(): void
    {
        $order = [ConsentLevel::Green, ConsentLevel::Yellow, ConsentLevel::Red];

        foreach ($order as $i => $level) {
            foreach ($order as $j => $other) {
                self::assertSame($i > $j, $level->isStricterThan($other), $level->value.' against '.$other->value);
            }
        }

        self::assertSame(ConsentLevel::Green, ConsentLevel::strictest());
        self::assertSame(ConsentLevel::Red, ConsentLevel::strictest(ConsentLevel::Yellow, ConsentLevel::Red, ConsentLevel::Green));
    }

    public function testAFeedbackNamesAValidSession(): void
    {
        $this->expectException(InvalidAnswer::class);
        $this->expectExceptionMessageIsOrContains('"Not A Key!" is not a session key.');

        new Feedback(ResourceReference::of('table', '7'), 'Not A Key!', ResourceReference::of('member', 'ana'), new StarsAndWishes(), new DateTimeImmutable('2026-11-12'));
    }

    public function testAnEmptyTextComesBeforeAKeptOne(): void
    {
        $feedback = new StarsAndWishes(['', 'First', ' ', 'Second'], ['', 'Wish']);

        self::assertSame(['First', 'Second'], $feedback->stars);
        self::assertSame(['Wish'], $feedback->wishes);
        self::assertEquals(new StarsAndWishes(['a', 'b'], ['c']), StarsAndWishes::fromArray(['stars' => ['a', 3, 'b'], 'wishes' => ['c', null]]));
    }

    public function testAQuestionLeftUnansweredDoesNotStopTheCount(): void
    {
        $template = new Template('mine', [
            new Question('first', QuestionKind::Choice, options: ['a' => null, 'b' => null]),
            new Question('second', QuestionKind::Choice, options: ['x' => null, 'y' => null]),
            new Question('third', QuestionKind::Text),
        ]);
        $pact = Pact::of($template, [new Answers(['second' => 'y', 'third' => 'Hello'])]);

        self::assertSame(1, $pact->respondents);
        self::assertSame(['a' => 0, 'b' => 0], $pact->tally('first'));
        self::assertSame(['x' => 0, 'y' => 1], $pact->tally('second'));
        self::assertSame(['Hello'], $pact->texts('third'));
    }

    public function testAnAnsweredTextDoesNotStopTheCount(): void
    {
        $template = new Template('mine', [
            new Question('note', QuestionKind::Text),
            new Question('mood', QuestionKind::Choice, options: ['a' => null, 'b' => null]),
        ]);
        $pact = Pact::of($template, [new Answers(['note' => 'Hello', 'mood' => 'b'])]);

        self::assertSame(['a' => 0, 'b' => 1], $pact->tally('mood'));
        self::assertSame(['Hello'], $pact->texts('note'));
    }

    public function testTheLabelOfAnOptionIsTrimmedBeforeItIsChecked(): void
    {
        $this->expectException(InvalidAnswer::class);
        $this->expectExceptionMessageIsOrContains('The label of the option "a" has 1 to');

        new Question('mood', QuestionKind::Choice, options: ['a' => '   ', 'b' => null]);
    }

    public function testARequiredQuestionTakesAnAnswer(): void
    {
        self::assertSame('Hi', new Question('note', QuestionKind::Text, required: true)->accept('Hi'));
        self::assertSame('a', new Question('mood', QuestionKind::Choice, options: ['a' => null, 'b' => null], required: true)->accept('a'));
        self::assertSame(['b'], new Question('moods', QuestionKind::Choices, options: ['a' => null, 'b' => null], required: true)->accept(['b']));
    }

    public function testAStoredQuestionIsOptionalUnlessItSaysOtherwise(): void
    {
        self::assertFalse(Question::fromArray(['key' => 'note', 'kind' => 'text'])->required);
        self::assertTrue(Question::fromArray(['key' => 'note', 'kind' => 'text', 'required' => true])->required);
        self::assertFalse(Question::fromArray(['key' => 'note', 'kind' => 'text', 'required' => 'yes'])->required);
    }

    public function testTheAnswersOfACheckInAreAllAdded(): void
    {
        $at = static fn (SignalKind $kind, string $time): Signal => new Signal(Signal::newId(), ResourceReference::of('table', '7'), $kind, Audience::Table, new DateTimeImmutable('2026-11-12 '.$time));
        $tally = CheckInTally::of([
            $at(SignalKind::CheckIn, '19:00'),
            $at(SignalKind::AllGood, '19:01'),
            $at(SignalKind::Unsure, '19:02'),
            $at(SignalKind::NotOkay, '19:03'),
            $at(SignalKind::NotOkay, '19:04'),
        ]);

        self::assertSame(4, $tally->answers());
    }

    public function testTheRecentSignalsAreTheLastHundredByDefault(): void
    {
        $store = new InMemorySignalStore();
        $table = ResourceReference::of('table', '7');
        $start = new DateTimeImmutable('2026-11-12 12:00:00');

        for ($i = 0; $i < 101; ++$i) {
            $store->add(new Signal(Signal::newId(), $table, SignalKind::AllGood, Audience::Table, $start->modify(\sprintf('+%d seconds', $i))));
        }

        $recent = $store->recent($table);

        self::assertCount(100, $recent);
        self::assertEquals($start->modify('+1 second'), $recent[0]->raisedAt);
        self::assertEquals($start->modify('+100 seconds'), $recent[99]->raisedAt);
        self::assertCount(101, $store->recent($table, null, 500));
        self::assertCount(2, $store->recent($table, $start->modify('+98 seconds')));
    }

    public function testAnAgreementCountsPastAnEmptyParticipation(): void
    {
        $table = ResourceReference::of('table', '7');
        $at = new DateTimeImmutable('2026-11-12');
        $agreement = TableAgreement::of([
            Participation::empty($table, ResourceReference::of('member', 'ana'), $at),
            new Participation($table, ResourceReference::of('member', 'bo'), LinesAndVeils::none()->with('gore', Boundary::Line), ConsentChecklist::none(), Answers::none(), $at),
        ]);

        self::assertSame(1, $agreement->respondents);
        self::assertSame(['gore'], $agreement->lines());
    }

    public function testNumericKeysAreThemeKeysToo(): void
    {
        $themes = new TableThemes(Catalogues::small());

        $this->expectException(InvalidAnswer::class);
        $this->expectExceptionMessageIsOrContains('The table does not know the theme "123".');

        // PHP keeps "123" as the integer 123 in the keys, which is what an application's form data holds.
        $themes->assertKnown(['123' => 'x']); // @phpstan-ignore argument.type
    }

    public function testALabelFallsBackToEnglishByDefault(): void
    {
        $catalogue = new Catalogue(
            [new Category('violence', LocalizedText::of(['fr' => 'Violence', 'en' => 'Violence']))],
            [new Theme('gore', 'violence', LocalizedText::of(['fr' => 'Sang', 'en' => 'Gore']))],
        );

        self::assertSame('Gore', new TableThemes($catalogue)->label('gore', 'de'));
    }
}
