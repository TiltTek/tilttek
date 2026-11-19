<?php

declare(strict_types=1);

namespace Tilttek\DiceProbabilityBundle\Tests\Unit\Infrastructure;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;
use Symfony\UX\LiveComponent\Attribute\PostHydrate;
use Tilttek\Dice\Custom\CustomDice;
use Tilttek\Dice\Custom\DieDefinition;
use Tilttek\Dice\Custom\Face;
use Tilttek\Dice\DiceRoller;
use Tilttek\DiceProbability\Probabilities;
use Tilttek\DiceProbabilityBundle\Application\DiceStatistics;
use Tilttek\DiceProbabilityBundle\Application\Exception\Throttled;
use Tilttek\DiceProbabilityBundle\Infrastructure\RateLimiter\ComputationThrottle;
use Tilttek\DiceProbabilityBundle\Infrastructure\Twig\Components\DistributionComponent;

#[CoversClass(DistributionComponent::class)]
#[CoversClass(Throttled::class)]
final class DistributionComponentTest extends TestCase
{
    public function testTheDefaults(): void
    {
        $component = $this->component();

        self::assertSame('2d6', $component->expression);
        self::assertSame('', $component->versus);
        self::assertSame('exactly', $component->show);
        self::assertNull($component->locale);
        self::assertNull($component->rounding);
        self::assertSame('6/36', (string) $component->distribution()?->probability(7));
        self::assertNull($component->comparison());
        self::assertNull($component->error());
        self::assertNull($component->versusError());
        self::assertSame([], $component->failures());
        self::assertSame(['exactly', 'at_least', 'at_most'], DistributionComponent::SHOWS);
    }

    public function testEachComputationTheBrowserAsksForIsCounted(): void
    {
        $throttle = new ComputationThrottle(new RateLimiterFactory(['id' => 'test', 'policy' => 'fixed_window', 'limit' => 1, 'interval' => '1 minute'], new InMemoryStorage()), new RequestStack(), 'secret');
        $method = new ReflectionMethod(DistributionComponent::class, 'countComputation');
        self::assertCount(1, $method->getAttributes(PostHydrate::class));

        $first = $this->component($throttle);
        $first->countComputation();
        self::assertSame('6/36', (string) $first->distribution()?->probability(7));

        $second = $this->component($throttle);
        $second->versus = '1d4';
        $second->countComputation();

        self::assertNull($second->distribution());
        self::assertNull($second->comparison());
        self::assertSame('Too many computations were asked for in a short time; the next ones wait for the rate limit.', $second->error());
        self::assertNull($second->versusError());
        self::assertSame([['expression' => '2d6', 'message' => 'Too many computations were asked for in a short time; the next ones wait for the rate limit.', 'key' => 'refusal.throttled', 'parameters' => []]], $second->failures());
        self::assertSame('refusal.throttled', new Throttled()->key());
        self::assertSame([], new Throttled()->parameters());
    }

    public function testWithoutARateLimitNothingIsCounted(): void
    {
        $component = $this->component();
        $component->countComputation();

        self::assertNotNull($component->distribution());
    }

    public function testTheFailuresSayWhyInTheWordsOfTheLibraryOrInAReasonOfTheBundle(): void
    {
        $component = $this->component();
        $component->expression = '2x6';

        self::assertSame([['expression' => '2x6', 'message' => 'Unexpected "x" at position 1 of "2x6".', 'key' => null, 'parameters' => []]], $component->failures());

        $component = $this->component();
        $component->versus = '10d1000';

        self::assertSame([['expression' => '10d1000', 'message' => 'The distribution of "10d1000" has 9991 values; at most 500 are drawn.', 'key' => 'refusal.outcomes', 'parameters' => ['%outcomes%' => 9991, '%limit%' => 500]]], $component->failures());

        $component = $this->component();
        $component->expression = '12d6g12';

        self::assertSame('refusal.groups', $component->failures()[0]['key'] ?? null);
        self::assertSame('The groups of "12d6g12" would take 245760000 steps to search over the samples of its estimate; at most 2000000 are taken.', $component->error());
    }

    public function testTheResultsAreComputedOncePerRender(): void
    {
        $component = $this->component();
        $component->versus = '1d4';

        self::assertSame($component->distribution(), $component->distribution());
        self::assertSame($component->comparison(), $component->comparison());
        self::assertSame($component->distribution(), $component->comparison()?->firstDistribution);
    }

    public function testAnExpressionAgainstAnother(): void
    {
        $component = $this->component();
        $component->expression = '1W6';
        $component->versus = ' 1W4 ';
        $component->locale = 'de';

        self::assertSame('14/24', (string) $component->comparison()?->first);
        self::assertSame('1d6', $component->canonical());
        self::assertSame('1d4', $component->canonicalVersus());
    }

    public function testABlankSecondExpressionComparesNothing(): void
    {
        $component = $this->component();
        $component->versus = '   ';

        self::assertNull($component->comparison());
        self::assertNull($component->versusError());
    }

    public function testTheRoundingOfTheComponent(): void
    {
        $component = $this->component();
        $component->expression = '1d6/3';
        $component->rounding = 'ceil';

        self::assertSame([1, 2], $component->distribution()?->values());

        $unknown = $this->component();
        $unknown->expression = '1d6/3';
        $unknown->rounding = 'sideways';

        self::assertSame([0, 1, 2], $unknown->distribution()?->values());
    }

    public function testAnExpressionWithoutProbabilitiesSaysWhy(): void
    {
        $component = $this->component();
        $component->expression = '2x6';
        $component->versus = '1d4';

        self::assertNull($component->distribution());
        self::assertSame('Unexpected "x" at position 1 of "2x6".', $component->error());
        self::assertNull($component->comparison());
        self::assertNull($component->versusError());
        self::assertSame('2x6', $component->canonical());
    }

    public function testASecondExpressionWithoutProbabilitiesSaysWhy(): void
    {
        $component = $this->component();
        $component->versus = '1d{symbols}';

        self::assertNotNull($component->distribution());
        self::assertNull($component->comparison());
        self::assertSame('The faces of the die "symbols" carry no value: they have no distribution.', $component->versusError());
    }

    public function testASecondExpressionThatCannotBeReadSaysWhy(): void
    {
        $component = $this->component();
        $component->versus = '1x4';

        self::assertNull($component->comparison());
        self::assertSame('Unexpected "x" at position 1 of "1x4".', $component->versusError());
    }

    public function testAProbabilityErrorIsSaidToo(): void
    {
        $component = $this->component();
        $component->expression = '1d6/(1d2-1)';

        self::assertSame('An outcome of the expression cannot be rolled: the expression divides by zero.', $component->error());
    }

    public function testADistributionOfTooManyValuesSaysWhy(): void
    {
        $component = $this->component();
        $component->expression = '10d1000';

        self::assertNull($component->distribution());
        self::assertSame('The distribution of "10d1000" has 9991 values; at most 500 are drawn.', $component->error());

        $component = $this->component();
        $component->versus = '10d1000';

        self::assertNull($component->comparison());
        self::assertSame('The distribution of "10d1000" has 9991 values; at most 500 are drawn.', $component->versusError());
    }

    public function testTheChartShowsAKnownColumn(): void
    {
        $component = $this->component();

        foreach (DistributionComponent::SHOWS as $show) {
            $component->show = $show;
            self::assertSame($show, $component->shown());
        }

        $component->show = 'sideways';
        self::assertSame('exactly', $component->shown());
    }

    private function component(?ComputationThrottle $throttle = null): DistributionComponent
    {
        $symbols = new CustomDice(new DieDefinition('symbols', new Face('star', 'Star'), new Face('moon', 'Moon')));

        return new DistributionComponent(new DiceStatistics(new DiceRoller(customDice: $symbols), new Probabilities($symbols)), $throttle);
    }
}
