<?php

declare(strict_types=1);

namespace Tilttek\Generator;

use Random\Engine\Secure;
use Random\Engine\Xoshiro256StarStar;
use Random\Randomizer;
use Tilttek\Dice\DiceRoller;
use Tilttek\Generator\Definition\Catalog;
use Tilttek\Generator\Engine\Producer;
use Tilttek\Generator\Exception\GeneratorException;
use Tilttek\Generator\Exception\InvalidCatalog;
use Tilttek\Generator\Exception\InvalidDefinition;
use Tilttek\Generator\Exception\LimitExceeded;
use Tilttek\Generator\Exception\UnknownGenerator;
use Tilttek\Generator\Grammar\Grammars;

/**
 * The library in one object: a catalog of lists and generators, checked when it is built, and drawn from.
 *
 * Every template is compiled in Twig's sandbox when the generator is built: a template that does not compile, uses a
 * tag, a filter or a function outside the generator's own, or names a list, a tag or a generator that does not exist
 * makes the catalog invalid, with every problem listed.
 */
final readonly class Generator
{
    private Producer $producer;

    /**
     * @param string       $locale     the language of the generators that do not name theirs
     * @param Randomizer   $randomizer the randomness of draws without a seed: a secure engine unless a test fixes it
     * @param string|false $cache      a directory where Twig keeps the compiled templates, or false
     *
     * @throws InvalidCatalog
     * @throws InvalidDefinition when no grammar is known for the default language
     */
    public function __construct(
        private Catalog $catalog,
        Limits $limits = new Limits(),
        string $locale = 'en',
        Grammars $grammars = new Grammars(),
        DiceRoller $dice = new DiceRoller(),
        private Randomizer $randomizer = new Randomizer(new Secure()),
        Timer $timer = new SystemTimer(),
        string|false $cache = false,
    ) {
        $grammars->for($locale);
        $this->producer = new Producer($catalog, $limits, $locale, $grammars, $dice, $timer, $cache);
        $problems = $this->producer->check();

        if ([] !== $problems) {
            throw new InvalidCatalog($problems);
        }

        $this->producer->compile();
    }

    public function catalog(): Catalog
    {
        return $this->catalog;
    }

    /**
     * Draws results from a generator. A draw with a seed gives the same results each time it is made with the same
     * catalog; a unique draw that cannot find enough different results returns those it found, and says so.
     *
     * @throws UnknownGenerator
     * @throws LimitExceeded
     * @throws GeneratorException when a template fails, a list runs out or a name given by a variable is unknown
     */
    public function draw(string $generator, Draw $draw = new Draw()): DrawResult
    {
        $randomizer = null === $draw->seed ? $this->randomizer : new Randomizer(new Xoshiro256StarStar(hash('sha256', $draw->seed, true)));

        return $this->producer->draw($generator, $draw, $randomizer);
    }
}
