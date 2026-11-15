<?php

declare(strict_types=1);

namespace Tilttek\TableSafety\Tests\Unit\SessionZero;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tilttek\TableSafety\Exception\InvalidAnswer;
use Tilttek\TableSafety\SessionZero\Answers;
use Tilttek\TableSafety\SessionZero\Pact;
use Tilttek\TableSafety\SessionZero\Templates;

#[CoversClass(Pact::class)]
final class PactTest extends TestCase
{
    public function testItGathersTheAnswersWithoutTheirAuthors(): void
    {
        $template = Templates::get('online');
        $pact = Pact::of($template, [
            $template->answer(['tone' => 'dark', 'pillars' => ['combat', 'social'], 'recording' => 'never', 'comfort' => 'zebra pace']),
            $template->answer(['tone' => 'dark', 'pillars' => ['social'], 'recording' => 'ask-first', 'comfort' => 'Captions']),
            $template->answer(['tone' => 'light', 'recording' => 'never', 'comfort' => 'breaks']),
            Answers::none(),
        ]);

        self::assertSame($template, $pact->template);
        self::assertSame(3, $pact->respondents);
        self::assertSame(['light' => 1, 'mixed' => 0, 'serious' => 0, 'dark' => 2], $pact->tally('tone'));
        self::assertSame(['combat' => 1, 'exploration' => 0, 'social' => 2, 'mystery' => 0, 'drama' => 0], $pact->tally('pillars'));
        self::assertSame(['never' => 2, 'ask-first' => 1, 'allowed' => 0], $pact->tally('recording'));
        self::assertSame(['video' => 0, 'voice' => 0, 'text' => 0], $pact->tally('camera'));
        self::assertSame(['breaks', 'Captions', 'zebra pace'], $pact->texts('comfort'));
        self::assertSame([], $pact->texts('themes-welcome'));
        self::assertSame([], $pact->texts('tone'));
        self::assertSame([], $pact->tally('comfort'));
        self::assertSame([], $pact->tally('snacks'));
    }

    public function testAnswersTheTemplateNoLongerHasAreLeftOut(): void
    {
        $pact = Pact::of(Templates::get('convention'), [
            new Answers(['tone' => 'grim', 'camera' => 'video', 'comfort' => ['a', 'b'], 'experience' => ['new', 'odd']]),
            new Answers(['camera' => 'video']),
        ], 1);

        self::assertSame(1, $pact->respondents);
        self::assertSame(['light' => 0, 'mixed' => 0, 'serious' => 0, 'dark' => 0], $pact->tally('tone'));
        self::assertSame(['new' => 1, 'some' => 0, 'seasoned' => 0], $pact->tally('experience'));
        self::assertSame(['a, b'], $pact->texts('comfort'));
    }

    public function testUnderTheThresholdThePactHoldsNothing(): void
    {
        $template = Templates::get('online');
        $pact = Pact::of($template, [
            $template->answer(['tone' => 'dark', 'pillars' => ['combat'], 'recording' => 'never', 'comfort' => 'Breaks']),
            $template->answer(['tone' => 'light', 'recording' => 'allowed']),
            new Answers(['snacks' => 'chips']),
        ]);

        self::assertSame(0, $pact->respondents);
        self::assertSame(3, $pact->threshold);
        self::assertFalse($pact->hasEnoughAnswers());
        self::assertSame(['light' => 0, 'mixed' => 0, 'serious' => 0, 'dark' => 0], $pact->tally('tone'));
        self::assertSame(['combat' => 0, 'exploration' => 0, 'social' => 0, 'mystery' => 0, 'drama' => 0], $pact->tally('pillars'));
        self::assertSame([], $pact->texts('comfort'));
    }

    public function testOneMoreAnswerPastTheThresholdChangesNothing(): void
    {
        $template = Templates::get('convention');
        $given = [
            $template->answer(['tone' => 'dark', 'comfort' => 'b']),
            $template->answer(['tone' => 'light']),
            Answers::none(),
            $template->answer(['tone' => 'dark', 'comfort' => 'a']),
            $template->answer(['tone' => 'serious', 'comfort' => 'only the fourth']),
            $template->answer(['tone' => 'mixed']),
            $template->answer(['tone' => 'mixed', 'experience' => 'new']),
        ];
        $pacts = [];

        for ($answers = 1; $answers <= \count($given); ++$answers) {
            $pacts[$answers] = Pact::of($template, \array_slice($given, 0, $answers));
        }

        self::assertFalse($pacts[3]->hasEnoughAnswers());
        self::assertTrue($pacts[4]->hasEnoughAnswers());
        self::assertSame(3, $pacts[4]->respondents);
        self::assertSame(['light' => 1, 'mixed' => 0, 'serious' => 0, 'dark' => 2], $pacts[4]->tally('tone'));
        self::assertSame(['a', 'b'], $pacts[4]->texts('comfort'));
        self::assertEquals($pacts[4], $pacts[5]);
        self::assertEquals($pacts[4], $pacts[6]);
        self::assertSame(6, $pacts[7]->respondents);
        self::assertSame(['light' => 1, 'mixed' => 2, 'serious' => 1, 'dark' => 2], $pacts[7]->tally('tone'));
        self::assertSame(['new' => 1, 'some' => 0, 'seasoned' => 0], $pacts[7]->tally('experience'));
        self::assertSame(['a', 'b', 'only the fourth'], $pacts[7]->texts('comfort'));
    }

    public function testTheThresholdIsChosen(): void
    {
        $template = Templates::get('convention');
        $pact = Pact::of($template, [$template->answer(['tone' => 'dark']), $template->answer(['tone' => 'light']), $template->answer(['tone' => 'dark'])], 2);

        self::assertSame(2, $pact->respondents);
        self::assertSame(2, $pact->threshold);
        self::assertSame(['light' => 1, 'mixed' => 0, 'serious' => 0, 'dark' => 1], $pact->tally('tone'));

        $this->expectException(InvalidAnswer::class);
        $this->expectExceptionMessageIsOrContains('A summary waits for at least 1 answer, not 0.');

        Pact::of($template, [], 0);
    }
}
