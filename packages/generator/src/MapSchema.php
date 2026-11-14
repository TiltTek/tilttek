<?php

declare(strict_types=1);

namespace Tilttek\Generator;

use JsonException;
use Tilttek\Generator\Exception\InvalidDefinition;

/**
 * The JSON form of the maps a generator makes — square and hex grids, graphs of rooms and passages —, versioned, and
 * its JSON Schema: an exported map names the schema it follows, so that a reader knows which version it reads.
 */
final class MapSchema
{
    /**
     * The version of the schema, raised whenever an exported map changes in a way an older reader would misread.
     */
    public const int VERSION = 1;

    /**
     * The identifier of this version of the schema, which an exported map carries as `$schema`.
     */
    public const string ID = 'urn:tilttek:generator:map:1';

    private function __construct()
    {
    }

    /**
     * A map a generator made, with the schema it follows and its version first.
     *
     * @param array<array-key, mixed> $map
     *
     * @return array<string, mixed>
     *
     * @throws InvalidDefinition when the array is not a map a generator makes
     */
    public static function export(array $map): array
    {
        $layout = $map['layout'] ?? null;
        $keys = 'graph' === $layout ? ['layout', 'nodes', 'edges'] : ['layout', 'width', 'height', 'cells'];

        if (!\in_array($layout, ['square', 'hex', 'graph'], true) || array_keys($map) !== $keys) {
            throw InvalidDefinition::at('map', 'a map is the result of a generator with a "map": a square or hex grid, or a graph');
        }

        /** @var array<string, mixed> $map */
        return ['$schema' => self::ID, 'version' => self::VERSION] + $map;
    }

    /**
     * The exported map as JSON.
     *
     * @param array<array-key, mixed> $map
     *
     * @throws InvalidDefinition when the array is not a map a generator makes
     * @throws JsonException     when a value of a cell cannot be written as JSON
     */
    public static function json(array $map, int $flags = 0): string
    {
        return json_encode(self::export($map), $flags | \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES);
    }

    /**
     * The JSON Schema (draft 2020-12) of an exported map.
     *
     * @return array<string, mixed>
     */
    public static function schema(): array
    {
        $index = ['type' => 'integer', 'minimum' => 0];
        $size = ['type' => 'integer', 'minimum' => 1];
        $header = [
            '$schema' => ['const' => self::ID],
            'version' => ['const' => self::VERSION],
        ];
        $grid = static fn (string $layout, array $place): array => [
            'type' => 'object',
            'required' => ['$schema', 'version', 'layout', 'width', 'height', 'cells'],
            'additionalProperties' => false,
            'properties' => $header + [
                'layout' => ['const' => $layout],
                'width' => $size,
                'height' => $size,
                'cells' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'required' => [...array_keys($place), 'value'],
                        'additionalProperties' => false,
                        'properties' => $place + ['value' => ['$ref' => '#/$defs/value']],
                    ],
                ],
            ],
        ];

        return [
            '$schema' => 'https://json-schema.org/draft/2020-12/schema',
            '$id' => self::ID,
            'title' => 'Map made by tilttek/generator',
            'description' => 'A square grid, a hex grid in rows where every odd row is pushed half a cell to the right, or a graph of nodes joined by passages, every node reachable.',
            'oneOf' => [
                ['$ref' => '#/$defs/square'],
                ['$ref' => '#/$defs/hex'],
                ['$ref' => '#/$defs/graph'],
            ],
            '$defs' => [
                'value' => [
                    'description' => 'What the cell or the node holds: a text, or the fields of a structured result.',
                    'type' => ['string', 'number', 'boolean', 'null', 'object', 'array'],
                ],
                'square' => $grid('square', ['x' => $index, 'y' => $index]),
                'hex' => $grid('hex', ['col' => $index, 'row' => $index, 'q' => ['type' => 'integer'], 'r' => $index]),
                'graph' => [
                    'type' => 'object',
                    'required' => ['$schema', 'version', 'layout', 'nodes', 'edges'],
                    'additionalProperties' => false,
                    'properties' => $header + [
                        'layout' => ['const' => 'graph'],
                        'nodes' => [
                            'type' => 'array',
                            'minItems' => 1,
                            'items' => [
                                'type' => 'object',
                                'required' => ['id', 'value'],
                                'additionalProperties' => false,
                                'properties' => ['id' => $index, 'value' => ['$ref' => '#/$defs/value']],
                            ],
                        ],
                        'edges' => [
                            'type' => 'array',
                            'items' => [
                                'description' => 'A passage between two nodes, the smaller identifier first.',
                                'type' => 'array',
                                'prefixItems' => [$index, $index],
                                'minItems' => 2,
                                'maxItems' => 2,
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }
}
