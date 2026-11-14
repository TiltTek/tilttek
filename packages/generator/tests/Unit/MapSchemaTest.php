<?php

declare(strict_types=1);

namespace Tilttek\Generator\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tilttek\Generator\Exception\InvalidDefinition;
use Tilttek\Generator\MapSchema;
use Tilttek\Generator\Tests\Unit\Fixture\Catalogs;

#[CoversClass(MapSchema::class)]
final class MapSchemaTest extends TestCase
{
    public function testAnExportedMapNamesItsSchemaAndItsVersionFirst(): void
    {
        $generator = Catalogs::generator(['t' => ['forêt']], [
            'square' => ['map' => ['layout' => 'square', 'width' => 2, 'height' => 1, 'cell' => "{{ pick('t') }}"]],
            'hex' => ['map' => ['layout' => 'hex', 'width' => 1, 'height' => 2, 'cell' => "{{ pick('t') }}"]],
            'graph' => ['map' => ['layout' => 'graph', 'nodes' => 2, 'cell' => '{{ index }}']],
        ]);

        self::assertSame(
            '{"$schema":"urn:tilttek:generator:map:1","version":1,"layout":"square","width":2,"height":1,"cells":[{"x":0,"y":0,"value":"forêt"},{"x":1,"y":0,"value":"forêt"}]}',
            MapSchema::json((array) $generator->draw('square')->first()),
        );
        self::assertSame(
            ['$schema' => MapSchema::ID, 'version' => 1, 'layout' => 'hex', 'width' => 1, 'height' => 2, 'cells' => [['col' => 0, 'row' => 0, 'q' => 0, 'r' => 0, 'value' => 'forêt'], ['col' => 0, 'row' => 1, 'q' => 0, 'r' => 1, 'value' => 'forêt']]],
            MapSchema::export((array) $generator->draw('hex')->first()),
        );
        self::assertSame(
            ['$schema' => MapSchema::ID, 'version' => 1, 'layout' => 'graph', 'nodes' => [['id' => 0, 'value' => '0'], ['id' => 1, 'value' => '1']], 'edges' => [[0, 1]]],
            MapSchema::export((array) $generator->draw('graph')->first()),
        );
        self::assertStringContainsString("\n", MapSchema::json((array) $generator->draw('graph')->first(), \JSON_PRETTY_PRINT));
    }

    public function testOnlyAMapIsExported(): void
    {
        foreach ([[], ['layout' => 'round', 'width' => 1, 'height' => 1, 'cells' => []], ['layout' => 'square', 'cells' => []], ['layout' => 'graph', 'width' => 1, 'height' => 1, 'cells' => []], ['layout' => 'hex', 'width' => 1, 'height' => 1, 'cells' => [], 'more' => 1]] as $map) {
            try {
                MapSchema::export($map);
                self::fail('A map was exported: '.json_encode($map));
            } catch (InvalidDefinition $invalidDefinition) {
                self::assertSame('map: a map is the result of a generator with a "map": a square or hex grid, or a graph', $invalidDefinition->getMessage());
            }
        }
    }

    public function testTheSchemaDescribesTheThreeKindsOfMap(): void
    {
        $schema = MapSchema::schema();

        self::assertSame('https://json-schema.org/draft/2020-12/schema', $schema['$schema']);
        self::assertSame(MapSchema::ID, $schema['$id']);
        self::assertSame([['$ref' => '#/$defs/square'], ['$ref' => '#/$defs/hex'], ['$ref' => '#/$defs/graph']], $schema['oneOf']);

        foreach (['square' => ['x', 'y', 'value'], 'hex' => ['col', 'row', 'q', 'r', 'value']] as $layout => $place) {
            self::assertSame(['$schema', 'version', 'layout', 'width', 'height', 'cells'], $this->at($schema, '$defs', $layout, 'required'));
            self::assertSame(['const' => $layout], $this->at($schema, '$defs', $layout, 'properties', 'layout'));
            self::assertSame(['const' => 1], $this->at($schema, '$defs', $layout, 'properties', 'version'));
            self::assertSame($place, $this->at($schema, '$defs', $layout, 'properties', 'cells', 'items', 'required'));
            self::assertFalse($this->at($schema, '$defs', $layout, 'additionalProperties'));
        }

        self::assertSame(['$schema', 'version', 'layout', 'nodes', 'edges'], $this->at($schema, '$defs', 'graph', 'required'));
        self::assertSame(2, $this->at($schema, '$defs', 'graph', 'properties', 'edges', 'items', 'maxItems'));
    }

    public function testTheSchemaShippedWithTheLibraryIsTheSchemaOfThisVersion(): void
    {
        self::assertJsonStringEqualsJsonString(
            json_encode(MapSchema::schema(), \JSON_THROW_ON_ERROR),
            (string) file_get_contents(\dirname(__DIR__, 2).'/schema/map.v1.json'),
        );
    }

    /**
     * @param array<array-key, mixed> $schema
     */
    private function at(array $schema, string ...$path): mixed
    {
        $value = $schema;

        foreach ($path as $key) {
            self::assertIsArray($value);
            self::assertArrayHasKey($key, $value);
            $value = $value[$key];
        }

        return $value;
    }
}
