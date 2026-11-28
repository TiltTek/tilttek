<?php

declare(strict_types=1);

namespace Tilttek\RpgSystems\Tests\Benchmark;

use Symfony\Component\Yaml\Yaml;
use Tilttek\RpgSystems\Attribution\AttributionRenderer;
use Tilttek\RpgSystems\Format\Syntax;
use Tilttek\RpgSystems\Format\SystemReader;
use Tilttek\RpgSystems\GameSystem;

/**
 * What an application does with a reference: read a system of 1 000 terms from YAML and from JSON — at warm-up, or
 * when a cache is rebuilt —, look a term up, list a category, and render the attribution of a page.
 */
final readonly class ReaderBench
{
    private string $yaml;

    private string $json;

    private GameSystem $system;

    public function __construct()
    {
        $document = [
            'format' => SystemReader::FORMAT,
            'system' => ['key' => 'bench', 'names' => ['en' => 'Bench', 'fr' => 'Banc']],
            'source' => ['title' => 'Bench', 'licence' => 'CC-BY-4.0', 'attribution' => 'Bench material, CC BY 4.0.'],
            'attributes' => array_map(static fn (int $index): array => ['key' => 'a'.$index, 'names' => ['en' => 'Attribute '.$index], 'minimum' => 1, 'maximum' => 30], range(1, 6)),
            'dice' => [['key' => 'check', 'notation' => '1d20', 'names' => 'Check'], ['key' => 'advantage', 'notation' => '2d20kh1', 'names' => 'Advantage']],
            'terms' => array_map(static fn (int $index): array => ['key' => 'c'.($index % 10).'.t'.$index, 'category' => 'c'.($index % 10), 'names' => ['en' => 'Term '.$index, 'fr' => 'Terme '.$index], 'descriptions' => ['en' => str_repeat('A description. ', 20)]], range(1, 1000)),
        ];
        $this->json = json_encode($document, \JSON_THROW_ON_ERROR);
        $this->yaml = Yaml::dump($document, 5, 2);
        $this->system = new SystemReader()->system($document);
    }

    /**
     * @Revs(5)
     *
     * @Iterations(3)
     */
    public function benchReadYaml(): void
    {
        $reader = new SystemReader();
        $reader->system($reader->decode($this->yaml, Syntax::Yaml));
    }

    /**
     * @Revs(5)
     *
     * @Iterations(3)
     */
    public function benchReadJson(): void
    {
        $reader = new SystemReader();
        $reader->system($reader->decode($this->json, Syntax::Json));
    }

    public function benchLookUpATerm(): ?string
    {
        return $this->system->term('c7.t517')->names->in('fr_CA');
    }

    public function benchListACategory(): int
    {
        return \count($this->system->terms('c3'));
    }

    public function benchRenderTheAttribution(): void
    {
        new AttributionRenderer()->html($this->system, 'fr');
    }
}
