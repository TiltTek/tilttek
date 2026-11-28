<?php

declare(strict_types=1);

namespace Tilttek\Factions\View;

use Tilttek\Factions\Relation;
use Tilttek\Factions\Scale;
use Tilttek\Factions\Tier;

/**
 * A roster as shown to its reader: the scale, the factions they know of in the order given, and the relations they
 * know of between them.
 */
final readonly class RosterView
{
    /**
     * @param list<FactionView> $factions
     * @param list<Relation>    $relations
     */
    public function __construct(
        public string $campaign,
        public int $version,
        public Scale $scale,
        public array $factions,
        public array $relations,
    ) {
    }

    /**
     * @return array{campaign: string, version: int, scale: array{min: int, max: int, start: int, tiers: list<array{key: string, label: string, from: int}>}, factions: list<array<string, mixed>>, relations: list<array{first: string, second: string, stance: string, note: string, secret: bool}>}
     */
    public function toArray(): array
    {
        return [
            'campaign' => $this->campaign,
            'version' => $this->version,
            'scale' => [
                'min' => $this->scale->min,
                'max' => $this->scale->max,
                'start' => $this->scale->start,
                'tiers' => array_map(static fn (Tier $tier): array => ['key' => $tier->key, 'label' => $tier->label, 'from' => $tier->from], $this->scale->tiers),
            ],
            'factions' => array_map(static fn (FactionView $faction): array => $faction->toArray(), $this->factions),
            'relations' => array_map(static fn (Relation $relation): array => [
                'first' => $relation->first,
                'second' => $relation->second,
                'stance' => $relation->stance->value,
                'note' => $relation->note,
                'secret' => $relation->secret,
            ], $this->relations),
        ];
    }
}
