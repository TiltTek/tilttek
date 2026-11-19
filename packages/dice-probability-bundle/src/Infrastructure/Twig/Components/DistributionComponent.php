<?php

declare(strict_types=1);

namespace Tilttek\DiceProbabilityBundle\Infrastructure\Twig\Components;

use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\Attribute\PostHydrate;
use Symfony\UX\LiveComponent\DefaultActionTrait;
use Throwable;
use Tilttek\Dice\Exception\DiceException;
use Tilttek\DiceProbability\Distribution;
use Tilttek\DiceProbability\Exception\ProbabilityException;
use Tilttek\DiceProbability\Versus;
use Tilttek\DiceProbabilityBundle\Application\DiceStatistics;
use Tilttek\DiceProbabilityBundle\Application\Exception\Refusal;
use Tilttek\DiceProbabilityBundle\Application\Exception\Throttled;
use Tilttek\DiceProbabilityBundle\Application\Rounding;
use Tilttek\DiceProbabilityBundle\Infrastructure\RateLimiter\ComputationThrottle;

/**
 * The probabilities of an expression on a page, `<twig:TilttekDiceProbability:Distribution expression="4d6kh3" />`:
 * the expression, and a second one to compare it with, typed and computed again without reloading the page; a
 * summary, a chart and a table that is always there, the chart's accessible form.
 *
 * Each time the browser sends the expressions back, one computation is counted against the client's rate limit, when
 * the bundle has one; past it, the expressions are not computed and the component says so.
 */
#[AsLiveComponent(name: 'TilttekDiceProbability:Distribution', template: '@TilttekDiceProbability/components/Distribution.html.twig', exposePublicProps: false)]
final class DistributionComponent
{
    use DefaultActionTrait;

    /** What the chart may draw: the probability of each value, of at least it, or of at most it. */
    public const array SHOWS = ['exactly', 'at_least', 'at_most'];

    #[LiveProp(writable: true)]
    public string $expression = '2d6';

    /** The expression compared with the first; none when empty. */
    #[LiveProp(writable: true)]
    public string $versus = '';

    #[LiveProp(writable: true)]
    public string $show = 'exactly';

    /** The language whose notation the expressions are written in; the canonical one when null. */
    #[LiveProp]
    public ?string $locale = null;

    /** How a division rounds: floor, ceil, round, towards_zero or away_from_zero; the configured one when null. */
    #[LiveProp]
    public ?string $rounding = null;

    /**
     * @var ?array{distribution: ?Distribution, versus: ?Versus, error: ?Throwable, versusError: ?Throwable}
     */
    private ?array $computed = null;

    /** Whether the rate limit refused this render's computation. */
    private bool $throttled = false;

    public function __construct(
        private readonly DiceStatistics $statistics,
        private readonly ?ComputationThrottle $throttle = null,
    ) {
    }

    /**
     * Counts the computation the browser asks for against the client's rate limit; the first render, drawn by the
     * page itself, is not counted.
     */
    #[PostHydrate]
    public function countComputation(): void
    {
        $this->throttled = false === $this->throttle?->allows();
    }

    public function distribution(): ?Distribution
    {
        return $this->compute()['distribution'];
    }

    public function comparison(): ?Versus
    {
        return $this->compute()['versus'];
    }

    /**
     * Why the expression has no probabilities, in the words of the library; null when it has.
     */
    public function error(): ?string
    {
        return $this->compute()['error']?->getMessage();
    }

    /**
     * Why the second expression has no probabilities; null when it has, or when there is none.
     */
    public function versusError(): ?string
    {
        return $this->compute()['versusError']?->getMessage();
    }

    /**
     * Each expression without probabilities, with why: the message of the library, or the key and the parameters of a
     * reason the bundle translates.
     *
     * @return list<array{expression: string, message: string, key: ?string, parameters: array<string, int|string>}>
     */
    public function failures(): array
    {
        $failures = [];

        foreach ([[$this->expression, $this->compute()['error']], [$this->versus, $this->compute()['versusError']]] as [$expression, $error]) {
            if ($error instanceof Throwable) {
                $failures[] = [
                    'expression' => $expression,
                    'message' => $error->getMessage(),
                    'key' => $error instanceof Refusal ? $error->key() : null,
                    'parameters' => $error instanceof Refusal ? $error->parameters() : [],
                ];
            }
        }

        return $failures;
    }

    /**
     * What the chart draws, one of `SHOWS`: an unknown value draws the probability of each value.
     */
    public function shown(): string
    {
        return \in_array($this->show, self::SHOWS, true) ? $this->show : 'exactly';
    }

    public function canonical(): string
    {
        return $this->statistics->canonical($this->expression, $this->locale);
    }

    public function canonicalVersus(): string
    {
        return $this->statistics->canonical($this->versus, $this->locale);
    }

    /**
     * @return array{distribution: ?Distribution, versus: ?Versus, error: ?Throwable, versusError: ?Throwable}
     */
    private function compute(): array
    {
        if (null !== $this->computed) {
            return $this->computed;
        }

        $rounding = null === $this->rounding ? null : Rounding::tryFrom($this->rounding);
        $computed = ['distribution' => null, 'versus' => null, 'error' => null, 'versusError' => null];

        if ($this->throttled) {
            $computed['error'] = new Throttled();

            return $this->computed = $computed;
        }

        try {
            $computed['distribution'] = $this->statistics->distribution($this->expression, $this->locale, $rounding);
        } catch (DiceException|ProbabilityException|Refusal $exception) {
            $computed['error'] = $exception;
        }

        if ('' !== trim($this->versus) && $computed['distribution'] instanceof Distribution) {
            try {
                $computed['versus'] = new Versus($computed['distribution'], $this->statistics->distribution($this->versus, $this->locale, $rounding));
            } catch (DiceException|ProbabilityException|Refusal $exception) {
                $computed['versusError'] = $exception;
            }
        }

        return $this->computed = $computed;
    }
}
