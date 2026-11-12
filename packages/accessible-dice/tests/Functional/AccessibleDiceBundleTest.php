<?php

declare(strict_types=1);

namespace Tilttek\AccessibleDice\Tests\Functional;

use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Tilttek\AccessibleDice\Application\AccessibleDice;
use Tilttek\AccessibleDice\Infrastructure\DependencyInjection\AccessibleDiceServices;
use Tilttek\AccessibleDice\Infrastructure\Twig\AccessibleDiceExtension;
use Tilttek\AccessibleDice\Tests\Functional\App\TestKernel;
use Tilttek\AccessibleDice\TilttekAccessibleDiceBundle;
use Tilttek\DiceBundle\Application\DiceRolls;
use Twig\Environment;

/**
 * The bundle in an application: the custom dice and the limits of TilttekDiceBundle, the form drawn in French with
 * its faces described, a roll announced, and the expression of a choice rolled by the dice of the application.
 */
#[CoversClass(TilttekAccessibleDiceBundle::class)]
#[CoversClass(AccessibleDiceServices::class)]
#[CoversClass(AccessibleDiceExtension::class)]
final class AccessibleDiceBundleTest extends KernelTestCase
{
    #[Override]
    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }

    #[Override]
    protected function setUp(): void
    {
        if (!ContainerBuilder::willBeAvailable('symfony/event-dispatcher', EventDispatcher::class, ['symfony/dependency-injection'])) {
            self::markTestSkipped('The event dispatcher is installed for development only: no kernel can boot.');
        }
    }

    public function testAChoiceIsRolledByTheDiceOfTheApplication(): void
    {
        self::bootKernel();
        $dice = self::getContainer()->get(AccessibleDice::class);
        self::assertInstanceOf(AccessibleDice::class, $dice);
        $rolls = self::getContainer()->get(DiceRolls::class);
        self::assertInstanceOf(DiceRolls::class, $rolls);

        self::assertSame(30, $dice->most());
        self::assertSame('30d{coin}+2', $dice->expression(['die' => 'coin', 'count' => '30', 'modifier' => '2']));
        self::assertCount(30, $rolls->roll($dice->expression(['die' => 'coin', 'count' => '30']))->results[0]->result->sets()[0]->dice);
    }

    public function testTheFormIsDrawnInFrench(): void
    {
        self::bootKernel();
        $rolls = self::getContainer()->get(DiceRolls::class);
        self::assertInstanceOf(DiceRolls::class, $rolls);
        $twig = self::getContainer()->get('twig');
        self::assertInstanceOf(Environment::class, $twig);
        $result = $rolls->roll('2d6+1');
        $html = $twig->render('page.html.twig', ['path' => '/roll', 'result' => $result]);
        $dice = $result->results[0]->result->sets()[0]->dice;
        $total = $result->totals()[0];
        self::assertNotNull($total);

        self::assertStringContainsString('<form class="tilttek-accessible-dice" method="post" action="/roll"', $html);
        self::assertStringContainsString('data-controller="tilttek--accessible-dice--dice"', $html);
        self::assertStringContainsString('data-action="submit->tilttek--accessible-dice--dice#roll tilttek--dice-tower--tower:drop@window->tilttek--accessible-dice--dice#announce"', $html);
        self::assertStringContainsString('data-tilttek--accessible-dice--dice-labels-value="{&quot;coin&quot;:{&quot;heads&quot;:&quot;Pile&quot;,&quot;tails&quot;:&quot;Face&quot;}}"', $html);
        self::assertStringContainsString('&quot;by&quot;:&quot;%name% a lancé. %roll%&quot;', $html);
        self::assertStringContainsString('<input type="hidden" name="_token" value="abc">', $html);
        self::assertStringContainsString('<legend>Dé</legend>', $html);
        self::assertMatchesRegularExpression('{<input type="radio" name="dice\[die\]" value="d6" data-notation="d6"\s+data-tilttek--accessible-dice--dice-target="die" data-action="tilttek--accessible-dice--dice#update" checked>\s+<span>dé à 6 faces</span>}', $html);
        self::assertMatchesRegularExpression('{<input type="radio" name="dice\[die\]" value="coin" data-notation="d\{coin\}"\s+data-tilttek--accessible-dice--dice-target="die" data-action="tilttek--accessible-dice--dice#update" aria-describedby="tilttek-accessible-dice-faces-coin">}', $html);
        self::assertStringContainsString('<summary>Faces du coin</summary>', $html);
        self::assertStringContainsString('<ul id="tilttek-accessible-dice-faces-coin">', $html);
        self::assertMatchesRegularExpression('{<li class="tilttek-accessible-dice__face"><span class="tilttek-accessible-dice__shape tilttek-accessible-dice__shape--circle" aria-hidden="true"></span><span>Pile : un cercle, vaut 1</span>\s*</li>}', $html);
        self::assertStringContainsString('<li class="tilttek-accessible-dice__face" style="--tilttek-accessible-dice-face-color: #336699">', $html);
        self::assertStringContainsString('<span>Face : un carré, vaut 0</span>', $html);
        self::assertStringContainsString('<label for="tilttek-accessible-dice-count">Combien</label>', $html);
        self::assertStringContainsString('<button type="button" aria-controls="tilttek-accessible-dice-count" aria-label="Un de moins" data-action="tilttek--accessible-dice--dice#fewer">−</button>', $html);
        self::assertStringContainsString('name="dice[count]" value="1" min="1" max="30"', $html);
        self::assertStringContainsString('name="dice[modifier]" value="0" min="-1000" max="1000"', $html);
        self::assertStringContainsString('<output data-tilttek--accessible-dice--dice-target="expression" for="tilttek-accessible-dice-count tilttek-accessible-dice-modifier">1d6</output>', $html);
        self::assertMatchesRegularExpression('{<input type="checkbox" data-tilttek--accessible-dice--dice-target="speak">\s+Lire les résultats à voix haute}', $html);
        self::assertStringContainsString('<button type="submit" class="tilttek-accessible-dice__roll">Lancer les dés</button>', $html);
        self::assertStringContainsString(\sprintf(
            '<div class="tilttek-accessible-dice__result" role="status" aria-live="polite" aria-atomic="true" aria-label="Résultat" data-tilttek--accessible-dice--dice-target="live">Aelis a lancé. 2d6+1 : %d, %d. Total %d.</div>',
            $dice[0]->value,
            $dice[1]->value,
            $total,
        ), $html);
        self::assertStringNotContainsString('checked>', substr($html, (int) strpos($html, 'target="speak"'), 40));
    }
}
