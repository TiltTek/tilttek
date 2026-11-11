<?php

declare(strict_types=1);

namespace Tilttek\DiceBundle\Tests\Unit\Infrastructure;

use Phil\Resource\ResourceReference;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Translation\Loader\ArrayLoader;
use Symfony\Component\Translation\Translator;
use Tilttek\DiceBundle\Infrastructure\Twig\DiceExtension;
use Tilttek\DiceBundle\Tests\Unit\Fixture\Dice;
use Tilttek\DiceBundle\Tests\Unit\Fixture\ScriptedEngine;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

#[CoversClass(DiceExtension::class)]
final class DiceExtensionTest extends TestCase
{
    public function testAnExpressionIsWrittenInALanguageOrADialect(): void
    {
        $extension = new DiceExtension(Dice::rolls(ScriptedEngine::randomizer()));

        self::assertSame(['2W6+1', '2W6+1', '2d6+1', 'not dice'], [$extension->notation('2d6+1', 'de'), $extension->notation('2W6+1', 'de'), $extension->notation('2d6+1'), $extension->notation('not dice')]);
        self::assertSame(['/r 4d6ro1', null, null, null], [$extension->dialect('4d6ro1', 'roll20'), $extension->dialect('4dF', 'avrae'), $extension->dialect('not dice', 'roll20'), $extension->dialect('2d6', 'fantasy')]);
        self::assertSame([4], new DiceExtension(Dice::rolls(ScriptedEngine::randomizer(3)))->roll('1n6', 'fi')->totals());
    }

    public function testTheWordsGoThroughTheTranslatorInItsDomain(): void
    {
        $translator = new Translator('fr');
        $translator->addLoader('array', new ArrayLoader());
        $translator->addResource('array', ['dropped' => 'écarté', 'Heads' => 'Pile'], 'fr', 'dice');

        self::assertSame(['écarté', 'rerolled'], [new DiceExtension(Dice::rolls(ScriptedEngine::randomizer()), $translator, 'dice')->text('dropped'), new DiceExtension(Dice::rolls(ScriptedEngine::randomizer()), $translator, 'dice')->text('rerolled')]);
        self::assertSame('dropped', new DiceExtension(Dice::rolls(ScriptedEngine::randomizer()), $translator)->text('dropped'));
        self::assertSame('dropped', new DiceExtension(Dice::rolls(ScriptedEngine::randomizer()))->text('dropped'));
    }

    public function testTheViewHoldsEveryDieOfTheRoll(): void
    {
        $dice = Dice::rolls(ScriptedEngine::randomizer(0, 0, 5, 1, 3, 0, 1, 2, 0, 1, 0, 1, 2, 0, 1, 2));
        $result = $dice->roll('-(3d6ro1!kh2)+1d{coin}, 2d{colours}, 3d{colours}, 2dF>=1, (1d6+1d4)*2+1d8');
        $view = new DiceExtension($dice)->view($result, ResourceReference::of('member', '1'), 'de');

        self::assertSame('-3W6ro1!kh2+1W{coin}', $view[0]['notation']);
        self::assertSame(-9, $view[0]['total']);
        self::assertSame(['1 × Heads'], $view[0]['tally']);
        self::assertSame(['3W6ro1!kh2', '1W{coin}'], array_column($view[0]['sets'], 'notation'));
        self::assertSame([10, 1], array_column($view[0]['sets'], 'value'));
        self::assertSame([null, null], array_column($view[0]['sets'], 'appearance'));
        self::assertSame([
            ['label' => '1', 'value' => 1, 'shape' => null, 'color' => null, 'image' => null, 'classes' => ['is-rerolled'], 'states' => ['rerolled']],
            ['label' => '1', 'value' => 1, 'shape' => null, 'color' => null, 'image' => null, 'classes' => ['is-dropped', 'is-from-reroll'], 'states' => ['dropped']],
            ['label' => '6', 'value' => 6, 'shape' => null, 'color' => null, 'image' => null, 'classes' => ['is-exploded'], 'states' => ['exploded']],
            ['label' => '2', 'value' => 2, 'shape' => null, 'color' => null, 'image' => null, 'classes' => ['is-dropped', 'is-from-explosion'], 'states' => ['dropped']],
            ['label' => '4', 'value' => 4, 'shape' => null, 'color' => null, 'image' => null, 'classes' => [], 'states' => []],
        ], $view[0]['sets'][0]['dice']);
        self::assertSame(['label' => 'Heads', 'value' => 1, 'shape' => 'circle', 'color' => null, 'image' => null, 'classes' => [], 'states' => []], $view[0]['sets'][1]['dice'][0]);
        self::assertSame([null, ['1 × Star', '1 × Blank']], [$view[1]['total'], $view[1]['tally']]);
        self::assertSame(['label' => 'Star', 'value' => 0, 'shape' => null, 'color' => null, 'image' => 'star.svg', 'classes' => [], 'states' => []], $view[1]['sets'][0]['dice'][0]);
        self::assertSame(['2 × Red', '1 × Star'], $view[2]['tally']);
        self::assertSame(['label' => 'Red', 'value' => 0, 'shape' => 'circle', 'color' => '#ff0000', 'image' => null, 'classes' => [], 'states' => []], $view[2]['sets'][0]['dice'][0]);
        self::assertSame([['is-failure'], ['is-success']], array_column($view[3]['sets'][0]['dice'], 'classes'));
        self::assertSame([['failure'], ['success']], array_column($view[3]['sets'][0]['dice'], 'states'));
        self::assertSame([['1W6', '1W4', '1W8'], 9], [array_column($view[4]['sets'], 'notation'), $view[4]['total']]);

        $translator = new Translator('fr');
        $translator->addLoader('array', new ArrayLoader());
        $translator->addResource('array', ['failure' => 'échec'], 'fr', 'tilttek_dice');
        self::assertSame([['échec'], ['success']], array_column(new DiceExtension($dice, $translator)->view($result)[3]['sets'][0]['dice'], 'states'));
    }

    public function testTheViewHoldsTheTextsAndListsOfARoll(): void
    {
        $translator = new Translator('fr');
        $translator->addLoader('array', new ArrayLoader());
        $translator->addResource('array', ['weapon.sword' => 'épée', 'hit' => 'touché'], 'fr', 'tilttek_dice');

        $dice = Dice::rolls(ScriptedEngine::randomizer(0, 1, 5, 0, 1, 0, 1));
        $view = new DiceExtension($dice, $translator)->view($dice->roll('2L[weapon.sword, bow]; 1d6i[6]{"hit"}; repeat(3, 2); 2d{colours}; 4; 2Ln[ka,ro]'));

        self::assertSame(['épée, bow', 'touché', '3, 3', null, null], array_column(\array_slice($view, 0, 5), 'text'));
        self::assertSame([null, null, null, null, 4], array_column(\array_slice($view, 0, 5), 'total'));
    }

    public function testTheDetailEscapesTheTextsOfARoll(): void
    {
        $loader = new FilesystemLoader();
        $loader->addPath(\dirname(__DIR__, 3).'/templates', 'TilttekDice');

        $dice = Dice::rolls(ScriptedEngine::randomizer());
        $html = new DiceExtension($dice)->detail(new Environment($loader), $dice->roll('"<b>bold</b>"'));

        self::assertStringContainsString('<code class="tilttek-dice-notation">&quot;&lt;b&gt;bold&lt;/b&gt;&quot;</code> : <output class="tilttek-dice-text">&lt;b&gt;bold&lt;/b&gt;</output>', $html);
    }

    public function testTheDetailEscapesItsTextsWhenTheApplicationTurnsAutoescapingOff(): void
    {
        $loader = new FilesystemLoader();
        $loader->addPath(\dirname(__DIR__, 3).'/templates', 'TilttekDice');

        $dice = Dice::rolls(ScriptedEngine::randomizer());
        $html = new DiceExtension($dice)->detail(new Environment($loader, ['autoescape' => false]), $dice->roll('"<img src=x onerror=alert(1)>"'));

        self::assertStringNotContainsString('<img src=x', $html);
        self::assertStringContainsString('<output class="tilttek-dice-text">&lt;img src=x onerror=alert(1)&gt;</output>', $html);
    }

    public function testAMembersDiceAreDressed(): void
    {
        $dice = Dice::rolls(ScriptedEngine::randomizer(0, 0, 0, 0), appearance: Dice::redDice());
        $result = $dice->roll('1d6, 1d8, 1dF, 1d{coin}');
        $extension = new DiceExtension($dice);
        $member = $extension->view($result, ResourceReference::of('member', '1'));

        self::assertSame([['name' => 'Ember', 'color' => '#aa0000', 'material' => 'resin', 'image' => 'ember.png'], null, ['name' => 'Ember', 'color' => '#aa0000', 'material' => 'resin', 'image' => 'ember.png'], null], array_map(static fn (array $expression): ?array => $expression['sets'][0]['appearance'], $member));
        self::assertSame([null, null, null, null], array_map(static fn (array $expression): ?array => $expression['sets'][0]['appearance'], $extension->view($result)));
        self::assertSame('1d6', $extension->view($result)[0]['notation']);
    }

    public function testTheDetailIsRenderedFromTheTemplate(): void
    {
        $loader = new FilesystemLoader();
        $loader->addPath(\dirname(__DIR__, 3).'/templates', 'TilttekDice');

        $dice = Dice::rolls(ScriptedEngine::randomizer(0, 0, 5, 0), appearance: Dice::redDice());
        $html = new DiceExtension($dice)->detail(new Environment($loader), $dice->roll('2d6ro1kh1, 1d{colours}'), ResourceReference::of('member', '1'));

        self::assertStringContainsString('<code class="tilttek-dice-notation">2d6ro1kh1</code> = <output class="tilttek-dice-total">6</output>', $html);
        self::assertStringContainsString('data-dice-appearance="Ember" data-dice-material="resin" style="--tilttek-dice-color: #aa0000"', $html);
        self::assertStringContainsString('<li class="tilttek-dice-die is-rerolled" data-value="1"><span class="tilttek-dice-face">1</span> <span class="tilttek-dice-state">(rerolled)</span></li>', $html);
        self::assertStringContainsString('<li class="tilttek-dice-die is-dropped is-from-reroll" data-value="1">', $html);
        self::assertStringContainsString('<output class="tilttek-dice-tally">1 × Red</output>', $html);
        self::assertStringContainsString('style="--tilttek-dice-face-color: #ff0000"><span class="tilttek-dice-shape tilttek-dice-shape-circle" aria-hidden="true"></span><span class="tilttek-dice-face">Red</span>', $html);
        self::assertSame('@TilttekDice/roll_detail.html.twig', DiceExtension::TEMPLATE);
    }
}
