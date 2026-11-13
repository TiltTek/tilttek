<?php

declare(strict_types=1);

namespace Tilttek\TableSafety\Tests\Unit\SessionZero;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
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
        ]);

        self::assertSame(1, $pact->respondents);
        self::assertSame(['light' => 0, 'mixed' => 0, 'serious' => 0, 'dark' => 0], $pact->tally('tone'));
        self::assertSame(['new' => 1, 'some' => 0, 'seasoned' => 0], $pact->tally('experience'));
        self::assertSame(['a, b'], $pact->texts('comfort'));
    }
}
