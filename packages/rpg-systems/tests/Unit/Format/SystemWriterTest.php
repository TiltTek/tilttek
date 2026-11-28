<?php

declare(strict_types=1);

namespace Tilttek\RpgSystems\Tests\Unit\Format;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tilttek\RpgSystems\Format\Syntax;
use Tilttek\RpgSystems\Format\SystemReader;
use Tilttek\RpgSystems\Format\SystemWriter;
use Tilttek\RpgSystems\GameSystem;
use Tilttek\RpgSystems\Licence;
use Tilttek\RpgSystems\Provenance;

#[CoversClass(SystemWriter::class)]
final class SystemWriterTest extends TestCase
{
    private const string EXAMPLES = __DIR__.'/../../../examples';

    public function testASystemIsWrittenWithItsSourceOnceAndReadBackEqual(): void
    {
        $system = $this->lanterns();
        $writer = new SystemWriter();
        $document = $writer->toArray($system);

        self::assertSame(['format', 'system', 'source', 'attributes', 'dice', 'terms', 'variants', 'difficulties', 'roles', 'translations'], array_keys($document));
        self::assertSame(['key' => 'lanterns', 'names' => ['en' => 'Lanterns in the Dark', 'fr' => 'Lanternes dans le noir', 'es' => 'Linternas en la oscuridad'], 'version' => '1.0'], $document['system']);
        self::assertSame(['title' => 'Lanterns in the Dark', 'licence' => 'owned', 'author' => 'The application'], $document['source']);
        self::assertSame(
            ['key' => 'glow', 'names' => ['en' => 'Glow', 'fr' => 'Lueur'], 'minimum' => 0, 'maximum' => 10],
            $this->item($document, 'attributes', 2),
        );
        self::assertSame(['key' => 'action', 'notation' => '2d6', 'names' => ['en' => 'Action roll', 'fr' => "Jet d'action", 'es' => 'Tirada de acción']], $this->item($document, 'dice', 0));
        self::assertSame(['key' => 'range.near', 'names' => ['en' => 'Near', 'fr' => 'Proche'], 'category' => 'range'], $this->item($document, 'terms', 2));
        self::assertSame(['key' => 'long-night', 'names' => ['en' => 'The long night', 'fr' => 'La longue nuit', 'es' => 'La larga noche'], 'descriptions' => ['en' => "A lantern's glow falls by one more at each scene."]], $this->item($document, 'variants', 0));
        self::assertSame([
            'key' => 'check',
            'names' => ['en' => 'Difficulty of an action', 'fr' => "Difficulté d'une action", 'es' => 'Dificultad de una acción'],
            'method' => 'check',
            'bands' => [
                ['key' => 'easy', 'names' => ['en' => 'Easy', 'fr' => 'Facile', 'es' => 'Fácil'], 'number' => 6],
                ['key' => 'hard', 'names' => ['en' => 'Hard', 'fr' => 'Difficile', 'es' => 'Difícil'], 'number' => 9],
                ['key' => 'desperate', 'names' => ['en' => 'Desperate', 'fr' => 'Désespérée'], 'number' => 11],
            ],
        ], $this->item($document, 'difficulties', 0));
        self::assertSame(['budgets' => [1 => ['calm' => 2, 'tense' => 4], 2 => ['calm' => 3, 'tense' => 6]], 'costs' => ['1/2' => 1, 1 => 2, 2 => 4]], $this->item($document, 'difficulties', 1)['numbers'] ?? null);
        self::assertSame(['key' => 'watcher', 'names' => ['en' => 'Watcher', 'fr' => 'Guetteur']], $this->item($document, 'roles', 1));
        self::assertSame(['es' => [[
            'title' => 'Linternas en la oscuridad, traducción',
            'licence' => 'CC-BY-4.0',
            'attribution' => 'Spanish translation of Lanterns in the Dark by the translators of the application, under CC BY 4.0.',
        ]]], $document['translations']);

        foreach ([[Syntax::Yaml, $writer->toYaml($system)], [Syntax::Json, $writer->toJson($system)]] as [$syntax, $text]) {
            self::assertEquals($system, new SystemReader()->system(new SystemReader()->decode($text, $syntax)));
        }

        self::assertStringStartsWith("format: tilttek-rpg-systems/1\nsystem:\n  key: lanterns\n", $writer->toYaml($system));
        self::assertStringContainsString("\"fr\": \"Jet d'action\"", $writer->toJson($system));
        self::assertStringContainsString('"url": "https://example.org/ranges"', $writer->toJson($this->withRecordSource()));
        self::assertStringEndsWith("}\n", $writer->toJson($system));
    }

    public function testARecordSourceIsWrittenOnlyWhereItDiffers(): void
    {
        $system = $this->withRecordSource();
        $document = new SystemWriter()->toArray($system);
        $term = $this->item($document, 'terms', 0);

        self::assertSame(['title' => 'Open ranges', 'licence' => 'CC-BY-4.0', 'attribution' => 'Ranges, CC BY 4.0.', 'author' => 'Someone', 'url' => 'https://example.org/ranges'], $term['source'] ?? null);
        self::assertSame(['key', 'names', 'source'], array_keys($term));
        self::assertSame(['key', 'names', 'descriptions'], array_keys($this->item($document, 'terms', 1)));
        self::assertSame(['format', 'system', 'source', 'terms'], array_keys($document));
        self::assertEquals($system, new SystemReader()->system($document));
    }

    private function lanterns(): GameSystem
    {
        return new SystemReader()->load(self::EXAMPLES.'/lanterns.yaml', self::EXAMPLES.'/lanterns.es.yaml')->get('lanterns');
    }

    private function withRecordSource(): GameSystem
    {
        return new SystemReader()->system([
            'format' => SystemReader::FORMAT,
            'system' => ['key' => 'x', 'names' => 'X'],
            'source' => ['title' => 'X', 'licence' => 'CC0-1.0'],
            'terms' => [
                ['key' => 'near', 'names' => 'Near', 'source' => ['title' => 'Open ranges', 'licence' => 'CC-BY-4.0', 'attribution' => 'Ranges, CC BY 4.0.', 'author' => 'Someone', 'url' => 'https://example.org/ranges']],
                ['key' => 'far', 'names' => 'Far', 'descriptions' => 'Far away.', 'source' => ['title' => 'X', 'licence' => 'CC0-1.0']],
            ],
        ]);
    }

    /**
     * @param array<string, mixed> $document
     *
     * @return array<mixed>
     */
    private function item(array $document, string $list, int $index): array
    {
        $items = $document[$list] ?? null;
        self::assertIsArray($items);
        $item = $items[$index] ?? null;
        self::assertIsArray($item);

        return $item;
    }

    public function testASourceWritesOnlyItsFilledFields(): void
    {
        self::assertSame(['title' => 'Public', 'licence' => 'CC0-1.0'], SystemWriter::source(new Provenance('Public', Licence::Cc0)));
    }
}
