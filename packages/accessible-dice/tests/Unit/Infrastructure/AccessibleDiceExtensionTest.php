<?php

declare(strict_types=1);

namespace Tilttek\AccessibleDice\Tests\Unit\Infrastructure;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tilttek\AccessibleDice\Application\AccessibleDice;
use Tilttek\AccessibleDice\Application\FaceDescriber;
use Tilttek\AccessibleDice\Application\Politeness;
use Tilttek\AccessibleDice\Application\RollAnnouncer;
use Tilttek\AccessibleDice\Application\Words;
use Tilttek\AccessibleDice\Infrastructure\Twig\AccessibleDiceExtension;
use Tilttek\AccessibleDice\Tests\Unit\Fixture\Dice;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

#[CoversClass(AccessibleDiceExtension::class)]
final class AccessibleDiceExtensionTest extends TestCase
{
    public function testTheViewOfTheForm(): void
    {
        $view = $this->extension()->view('/roll');

        self::assertSame(['/roll', 'dice', null, 'tilttek-accessible-dice', 100, 'polite', ''], [$view['action'], $view['field'], $view['token'], $view['id'], $view['most'], $view['politeness'], $view['announcement']]);
        self::assertSame(['d6', 'coin'], array_column($view['choices'], 'name'));
        self::assertSame(['d6', 'd{coin}'], array_column($view['choices'], 'notation'));
        self::assertSame([], $view['choices'][0]['faces']);
        self::assertSame(['id' => 'heads', 'label' => 'Heads', 'description' => 'Heads: a circle, worth 1', 'value' => 1, 'shape' => 'circle', 'color' => null, 'image' => null], $view['choices'][1]['faces'][0]);
        self::assertSame(['heads' => 'Heads', 'tails' => 'Tails'], $view['labels']['coin']);
        self::assertSame(['with_dice' => '%expression%: %dice%. %outcome%.', 'without_dice' => '%expression%: %outcome%.', 'total' => 'Total %total%', 'gave' => 'Result: %text%', 'nothing' => 'No result', 'dropped' => '%die% not counted', 'by' => '%name% rolled. %roll%'], $view['sentences']);
    }

    public function testTheFormAnsweredWithARoll(): void
    {
        $words = new Words(Dice::translator());
        $extension = $this->extension($words, Politeness::Assertive);
        $result = Dice::roll('1d6');
        $view = $extension->view('/roll', 'roll', ['name' => '_token', 'value' => 'abc'], $result, 'Aelis', 'polite', 'second', 'en');
        $total = $result->totals()[0];
        self::assertNotNull($total);

        self::assertSame(['roll', ['name' => '_token', 'value' => 'abc'], 'second', 'polite'], [$view['field'], $view['token'], $view['id'], $view['politeness']]);
        self::assertSame(\sprintf('Aelis rolled. 1d6: %1$d. Total %1$d.', $total), $view['announcement']);
        self::assertSame('6-sided die', $view['choices'][0]['label']);
        self::assertSame('assertive', $extension->view('/roll')['politeness']);
        self::assertSame('Total %total%', $extension->view('/roll', locale: 'fr')['sentences']['total']);
        self::assertSame('%name% a lancé. %roll%', $extension->view('/roll')['sentences']['by']);
        self::assertSame('Pile', $extension->view('/roll')['labels']['coin']['heads']);
    }

    public function testTheTemplateIsRenderedWithTheView(): void
    {
        $twig = new Environment(new ArrayLoader([AccessibleDiceExtension::TEMPLATE => '{{ dice.action }} {{ dice.field }} {{ dice.id }} {{ dice.politeness }}']));

        self::assertSame('/roll dice tilttek-accessible-dice polite', $this->extension()->form($twig, '/roll'));
        self::assertSame('/go pick two assertive', $this->extension()->form($twig, '/go', 'pick', politeness: 'assertive', id: 'two'));
    }

    public function testTheAnnouncementAndTheFaces(): void
    {
        $extension = $this->extension();

        self::assertSame('3: Total 3.', $extension->announcement(Dice::roll('3')));
        self::assertSame('Brom rolled. 3: Total 3.', $extension->announcement(Dice::roll('3'), 'Brom'));
        self::assertSame('3 : Total 3.', $this->extension(new Words(Dice::translator()))->announcement(Dice::roll('3'), locale: 'fr'));
        self::assertSame(['Pearl', 'Void'], array_map(static fn (object $face): string => $face->label, \array_slice($extension->faces('gem'), 1)));
        self::assertSame('Rubis', $this->extension(new Words(Dice::translator()))->faces('gem', 'fr')[0]->label);
    }

    private function extension(Words $words = new Words(), Politeness $politeness = Politeness::Polite): AccessibleDiceExtension
    {
        $describer = new FaceDescriber(Dice::customDice(), $words);

        return new AccessibleDiceExtension(new AccessibleDice(Dice::customDice(), $describer, $words, [6], ['coin']), $describer, new RollAnnouncer($words), $words, $politeness);
    }
}
