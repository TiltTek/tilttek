<?php

declare(strict_types=1);

namespace Tilttek\GeneratorBundle\Infrastructure\Catalog;

use Random\Engine\Secure;
use Random\Randomizer;
use Tilttek\Dice\DiceRoller;
use Tilttek\Generator\Definition\Catalog;
use Tilttek\Generator\Exception\InvalidCatalog;
use Tilttek\Generator\Exception\InvalidDefinition;
use Tilttek\Generator\Generator;
use Tilttek\Generator\Grammar\Grammars;
use Tilttek\Generator\Limits;
use Tilttek\Generator\SystemTimer;

/**
 * Builds the application's generator from its catalog with the configured limits, language, grammars and dice, a
 * secure randomness, and Twig's compiled templates kept in the cache.
 */
final readonly class GeneratorFactory
{
    public function __construct(
        private CachedCatalog $catalog,
        private Limits $limits,
        private string $locale,
        private Grammars $grammars,
        private ?DiceRoller $dice = null,
        private string|false $cache = false,
    ) {
    }

    /**
     * The generator of the cached catalog.
     *
     * @throws InvalidDefinition
     * @throws InvalidCatalog
     */
    public function generator(): Generator
    {
        return $this->build($this->catalog->catalog());
    }

    /**
     * @throws InvalidDefinition
     * @throws InvalidCatalog
     */
    public function build(Catalog $catalog): Generator
    {
        return new Generator($catalog, $this->limits, $this->locale, $this->grammars, $this->dice ?? new DiceRoller(), new Randomizer(new Secure()), new SystemTimer(), $this->cache);
    }

    public function catalog(): CachedCatalog
    {
        return $this->catalog;
    }
}
