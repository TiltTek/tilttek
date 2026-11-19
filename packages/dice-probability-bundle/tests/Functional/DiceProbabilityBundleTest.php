<?php

declare(strict_types=1);

namespace Tilttek\DiceProbabilityBundle\Tests\Functional;

use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpKernel\KernelInterface;
use Tilttek\DiceProbability\Event\Count;
use Tilttek\DiceProbabilityBundle\Application\DiceStatistics;
use Tilttek\DiceProbabilityBundle\Infrastructure\DependencyInjection\KitSubdirectoryPass;
use Tilttek\DiceProbabilityBundle\Infrastructure\Twig\Components\DistributionComponent;
use Tilttek\DiceProbabilityBundle\Infrastructure\Twig\ProbabilityExtension;
use Tilttek\DiceProbabilityBundle\Tests\Functional\App\TestKernel;
use Tilttek\DiceProbabilityBundle\TilttekDiceProbabilityBundle;
use Twig\Environment;

/**
 * The bundle in an application: the expressions read with the custom dice of TilttekDiceBundle, the settings and the
 * rounding of the configuration, and the component, the table and the chart drawn with the recipes of the PHIL UX
 * kit, in French, wherever the Toolkit installed them.
 */
#[CoversClass(TilttekDiceProbabilityBundle::class)]
#[CoversClass(KitSubdirectoryPass::class)]
#[CoversClass(ProbabilityExtension::class)]
#[CoversClass(DistributionComponent::class)]
final class DiceProbabilityBundleTest extends KernelTestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function subdirectories(): iterable
    {
        yield 'PHIL UX in a subdirectory' => ['ui'];
        yield 'PHIL UX' => [''];
    }

    #[Override]
    protected function setUp(): void
    {
        if (!ContainerBuilder::willBeAvailable('symfony/event-dispatcher', EventDispatcher::class, ['symfony/dependency-injection'])) {
            self::markTestSkipped('The event dispatcher is installed for development only: no kernel can boot.');
        }
    }

    public function testTheStatisticsUseTheDiceAndTheSettingsOfTheApplication(): void
    {
        self::bootKernel();
        $statistics = self::getContainer()->get(DiceStatistics::class);
        self::assertInstanceOf(DiceStatistics::class, $statistics);

        self::assertSame('3/8', (string) $statistics->distribution('3d{coin}')->probability(2));
        self::assertSame('7/8', (string) $statistics->chance(Count::atLeastOne(1), '3d{coin}')->probability);
        self::assertSame([1, 2], $statistics->distribution('1d6/3')->values());
        self::assertSame('1/216', (string) $statistics->distribution('1d6!')->remaining->reduced());
        self::assertSame(200, $statistics->distribution('3d6o2')->estimate?->samples);
    }

    #[DataProvider('subdirectories')]
    public function testTheComponentIsDrawnWithTheKit(string $subdirectory): void
    {
        $html = $this->twig($subdirectory)->render('page.html.twig');

        self::assertStringContainsString('data-controller="live"', $html);
        self::assertMatchesRegularExpression('{<div role="group" data-slot="field" data-orientation="vertical" class="isField"><label data-slot="field-label" class="isFieldLabel" for="(live-[0-9-]+)-expression">Expression</label>\s*<input data-slot="input" type="text" id="\1-expression" name="expression" value="3d\{coin\}" data-model="debounce\(400\)\|expression" autocomplete="off" spellcheck="false" required></div>}', $html);
        self::assertMatchesRegularExpression('{<label data-slot="field-label" class="isFieldLabel" for="(live-[0-9-]+)-versus">Contre \(facultatif\)</label>\s*<input data-slot="input" type="text" id="\1-versus" name="versus" value="1d6/4" data-model="debounce\(400\)\|versus" autocomplete="off" spellcheck="false"></div>}', $html);
        self::assertStringContainsString('<fieldset data-slot="field-set" class="isFieldSet"><legend data-slot="field-legend" data-variant="legend" class="isFieldLegend">Le graphique montre</legend>', $html);
        self::assertStringContainsString('<div role="radiogroup" data-slot="radio-group" data-orientation="horizontal" class="isRadioGroup isHorizontal">', $html);
        self::assertStringContainsString('<label><input type="radio" data-slot="radio-group-item" name="show" value="exactly" data-model="show" checked> Exactement</label>', $html);
        self::assertStringContainsString('<label><input type="radio" data-slot="radio-group-item" name="show" value="at_least" data-model="show"> Au moins</label>', $html);
        self::assertStringContainsString('<div class="tilttek-dice-probability__results" aria-live="polite">', $html);
        self::assertStringContainsString('<section data-slot="card" data-size="default" class="isCard tilttek-dice-probabilities__versus"><header data-slot="card-header" class="isCardHeader"><h3 data-slot="card-title" class="isCardTitle">3d{coin} contre 1d6/4</h3>', $html);
        self::assertStringContainsString('<dt>3d{coin} est plus haut</dt>', $html);
        self::assertStringContainsString('<caption data-slot="table-caption" class="isTableCaption">Probabilités de 3d{coin}</caption>', $html);
        self::assertStringContainsString('<caption data-slot="table-caption" class="isTableCaption">Probabilités de 1d6/4</caption>', $html);
        self::assertStringContainsString('<caption data-slot="table-caption" class="isTableCaption">Probabilités de 1d6!</caption>', $html);
        self::assertStringContainsString('Les explosions sont suivies jusqu’à une profondeur : 0.4630 % des jets', $html);
        self::assertStringNotContainsString('isAlert', $html);
    }

    #[DataProvider('subdirectories')]
    public function testTheTableAndTheChart(string $subdirectory): void
    {
        $html = $this->render($subdirectory, "{{ tilttek_dice_probabilities('1d4+1') }}");

        self::assertStringContainsString('<div class="tilttek-dice-probabilities">', $html);
        self::assertStringContainsString('<section data-slot="card" data-size="default" class="isCard tilttek-dice-distribution"><div data-slot="card-content" class="isCardBody">', $html);
        self::assertStringContainsString('<div class="isScrollable" data-slot="table-container"><table data-slot="table" class="isTable tilttek-dice-distribution__table">', $html);
        self::assertStringContainsString('<th data-slot="table-head" class="isTableHead" scope="col">Valeur</th>', $html);
        self::assertStringContainsString('<th data-slot="table-head" class="isTableHead" scope="col">Au plus</th>', $html);
        self::assertStringContainsString('<th data-slot="table-head" class="isTableHead" scope="row">2</th>', $html);
        self::assertStringContainsString('<td data-slot="table-cell" class="isTableCell"><span class="tilttek-dice-probability">25.00 %</span> <span class="tilttek-dice-probability__fraction">(1/4)</span></td>', $html);
        self::assertStringContainsString('<div class="tilttek-dice-distribution__chart" aria-hidden="true"><figure data-slot="chart" data-type="bar" class="isChart">', $html);
        self::assertStringContainsString('<figcaption class="isChartCaption">Exactement</figcaption>', $html);
        self::assertStringContainsString('viewBox="0 0 640 200"', $html);
        self::assertStringContainsString('<thead><tr><td></td><th scope="col">1d4+1</th></tr></thead>', $html);
        self::assertStringContainsString('<tr><th scope="row">2</th><td>25</td></tr>', $html);
        self::assertStringContainsString('<dd>3.50</dd>', $html);
        self::assertStringContainsString('<dd>1.12</dd>', $html);
        self::assertStringContainsString('<dd>de 2 à 5</dd>', $html);
        self::assertStringNotContainsString('tilttek-dice-probabilities__versus', $html);
        self::assertStringNotContainsString('tilttek-dice-distribution__estimate', $html);
        self::assertStringNotContainsString('tilttek-dice-distribution__remaining', $html);
    }

    public function testTheChartDrawsWhatIsAskedFor(): void
    {
        $least = $this->render('ui', "{{ tilttek_dice_probabilities('1d4', show: 'at_least') }}");
        $most = $this->render('ui', "{{ tilttek_dice_probabilities('1d4', show: 'at_most') }}");

        self::assertStringContainsString('<figcaption class="isChartCaption">Au moins</figcaption>', $least);
        self::assertStringContainsString('<tr><th scope="row">1</th><td>100</td></tr>', $least);
        self::assertStringContainsString('<tr><th scope="row">4</th><td>25</td></tr>', $least);
        self::assertStringContainsString('<figcaption class="isChartCaption">Au plus</figcaption>', $most);
        self::assertStringContainsString('<tr><th scope="row">1</th><td>25</td></tr>', $most);
        self::assertStringContainsString('<tr><th scope="row">4</th><td>100</td></tr>', $most);
    }

    public function testTwoExpressionsAgainstEachOther(): void
    {
        $html = $this->render('ui', "{{ tilttek_dice_probabilities('1W6', '1W4', locale: 'de') }}");

        self::assertStringContainsString('<h3 data-slot="card-title" class="isCardTitle">1d6 contre 1d4</h3>', $html);
        self::assertStringContainsString('<dt>1d6 est plus haut</dt>', $html);
        self::assertStringContainsString('<dd><span class="tilttek-dice-probability">58.33 %</span> <span class="tilttek-dice-probability__fraction">(7/12)</span></dd>', $html);
        self::assertStringContainsString('<dt>Égalité</dt>', $html);
        self::assertStringContainsString('<caption data-slot="table-caption" class="isTableCaption">Probabilités de 1d6</caption>', $html);
        self::assertStringContainsString('<caption data-slot="table-caption" class="isTableCaption">Probabilités de 1d4</caption>', $html);
        self::assertStringNotContainsString('tilttek-dice-probabilities__estimate', $html);
    }

    public function testAnEstimateAndAnExplosionCutAtItsDepthAreSaid(): void
    {
        $estimated = $this->render('ui', "{{ tilttek_dice_probabilities('3d6o2', '1d4') }}");

        self::assertStringContainsString('<p class="tilttek-dice-distribution__estimate">', $estimated);
        self::assertStringContainsString('<p class="tilttek-dice-probabilities__estimate">', $estimated);
        self::assertStringContainsString('Les explosions sont suivies jusqu’à une profondeur : 0.4630 % des jets', $this->render('ui', "{{ tilttek_dice_probabilities('1d6!', rounding: 'floor') }}"));
    }

    public function testAnExpressionWithoutProbabilitiesSaysWhyInAnAlertOfTheKit(): void
    {
        $html = $this->render('ui', "{{ component('TilttekDiceProbability:Distribution', {expression: '2x6'}) }}");

        self::assertStringContainsString('<div data-slot="alert" data-variant="destructive" class="isAlert isDanger tilttek-dice-probability__error" role="status"><div data-slot="alert-description" class="isAlertDescription">', $html);
        self::assertStringContainsString('2x6', $html);
        self::assertStringNotContainsString('tilttek-dice-probabilities"', $html);
    }

    public function testASecondExpressionWithoutProbabilitiesSaysWhyBesideTheFirst(): void
    {
        $html = $this->render('', "{{ component('TilttekDiceProbability:Distribution', {expression: '1d6', versus: '2x6'}) }}");

        self::assertStringContainsString('<div data-slot="alert-description" class="isAlertDescription">', $html);
        self::assertStringContainsString('<caption data-slot="table-caption" class="isTableCaption">Probabilités de 1d6</caption>', $html);
    }

    /**
     * Seven characters that took minutes to draw: each now answers within a second or so, estimated — on the 200
     * samples of the application — or refused.
     */
    public function testShortExpressionsThatCostTheMostAreBounded(): void
    {
        $twig = $this->twig('ui');

        foreach (['10d1000' => 'Estimé sur 200 jets', '100d6!' => 'limited to 50', '50d6!' => 'Estimé sur 200 jets'] as $expression => $refusal) {
            $start = $this->processorTime();
            $html = $twig->createTemplate(\sprintf("{{ component('TilttekDiceProbability:Distribution', {expression: '%s'}) }}", $expression))->render();

            self::assertStringContainsString($refusal, $html);
            self::assertLessThan(3.0, $this->processorTime() - $start, $expression);
        }

        // Groups whose search took seconds on each sample are refused, in the words of the bundle, within a second.
        foreach (['12d6g12', '10d10g10'] as $expression) {
            $start = $this->processorTime();
            $html = $twig->createTemplate(\sprintf("{{ component('TilttekDiceProbability:Distribution', {expression: '%s'}) }}", $expression))->render();

            self::assertStringContainsString(\sprintf('« %s » n’a pas de probabilités : trouver les groupes d’autant de dés serait trop long ; lancez-en moins.', $expression), html_entity_decode($html, \ENT_QUOTES));
            self::assertLessThan(1.0, $this->processorTime() - $start, $expression);
        }

        $html = $twig->createTemplate("{{ component('TilttekDiceProbability:Distribution', {expression: '6d100'}) }}")->render();
        self::assertStringContainsString('sa distribution a 595 valeurs ; au plus 500 sont dessinées.', html_entity_decode($html, \ENT_QUOTES));

        // A table whose fractions took seconds to reduce.
        $start = $this->processorTime();
        $html = $twig->createTemplate("{{ tilttek_dice_probabilities('16d30') }}")->render();

        self::assertStringContainsString('<caption data-slot="table-caption" class="isTableCaption">Probabilités de 16d30</caption>', $html);
        self::assertLessThan(3.0, $this->processorTime() - $start);

        // The longest table drawn at the defaults: 491 values.
        $start = $this->processorTime();
        $html = $twig->createTemplate("{{ tilttek_dice_probabilities('10d50') }}")->render();

        self::assertStringContainsString('<caption data-slot="table-caption" class="isTableCaption">Probabilités de 10d50</caption>', $html);
        self::assertLessThan(10.0, $this->processorTime() - $start);
    }

    /**
     * @param array<mixed> $options
     */
    #[Override]
    protected static function createKernel(array $options = []): KernelInterface
    {
        $subdirectory = $options['subdirectory'] ?? 'ui';
        \assert(\is_string($subdirectory));

        return new TestKernel('test', true, $subdirectory);
    }

    private function processorTime(): float
    {
        $usage = getrusage();
        self::assertIsArray($usage);
        self::assertIsInt($usage['ru_utime.tv_sec']);
        self::assertIsInt($usage['ru_utime.tv_usec']);

        return $usage['ru_utime.tv_sec'] + $usage['ru_utime.tv_usec'] / 1_000_000;
    }

    private function render(string $subdirectory, string $template): string
    {
        return $this->twig($subdirectory)->createTemplate($template)->render();
    }

    private function twig(string $subdirectory): Environment
    {
        self::ensureKernelShutdown();
        self::bootKernel(['subdirectory' => $subdirectory]);
        $twig = self::getContainer()->get('twig');
        self::assertInstanceOf(Environment::class, $twig);

        return $twig;
    }
}
