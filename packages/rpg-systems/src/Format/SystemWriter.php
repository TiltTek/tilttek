<?php

declare(strict_types=1);

namespace Tilttek\RpgSystems\Format;

use Phil\Url\Url;
use Symfony\Component\Yaml\Yaml;
use Tilttek\RpgSystems\Attribute;
use Tilttek\RpgSystems\DiceExpression;
use Tilttek\RpgSystems\Difficulty;
use Tilttek\RpgSystems\DifficultyBand;
use Tilttek\RpgSystems\GameSystem;
use Tilttek\RpgSystems\PartyRole;
use Tilttek\RpgSystems\Provenance;
use Tilttek\RpgSystems\RuleVariant;
use Tilttek\RpgSystems\Term;
use Tilttek\RpgSystems\Translations;

/**
 * Writes a system as a document of the library's format, which `SystemReader` reads back to an equal system: the
 * system's source once, and a record's own source only where it differs.
 */
final readonly class SystemWriter
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(GameSystem $system): array
    {
        $document = [
            'format' => SystemReader::FORMAT,
            'system' => array_filter(['key' => $system->key, 'names' => $system->names->texts, 'version' => $system->version], $this->filled(...)),
            'source' => self::source($system->provenance),
            'attributes' => array_map(fn (Attribute $attribute): array => $this->withSource([
                'key' => $attribute->key,
                'names' => $attribute->names->texts,
                'abbreviations' => $this->texts($attribute->abbreviations),
                'descriptions' => $this->texts($attribute->descriptions),
                'minimum' => $attribute->minimum,
                'maximum' => $attribute->maximum,
            ], $attribute->provenance, $system), $system->attributes()),
            'dice' => array_map(fn (DiceExpression $dice): array => $this->withSource([
                'key' => $dice->key,
                'notation' => $dice->notation,
                'names' => $dice->names->texts,
            ], $dice->provenance, $system), $system->diceExpressions()),
            'terms' => array_map(fn (Term $term): array => $this->withSource([
                'key' => $term->key,
                'names' => $term->names->texts,
                'category' => $term->category,
                'descriptions' => $this->texts($term->descriptions),
            ], $term->provenance, $system), $system->terms()),
            'variants' => array_map(fn (RuleVariant $variant): array => $this->withSource([
                'key' => $variant->key,
                'names' => $variant->names->texts,
                'descriptions' => $this->texts($variant->descriptions),
            ], $variant->provenance, $system), $system->variants()),
            'difficulties' => array_map(fn (Difficulty $difficulty): array => $this->withSource([
                'key' => $difficulty->key,
                'names' => $difficulty->names->texts,
                'descriptions' => $this->texts($difficulty->descriptions),
                'method' => $difficulty->method,
                'bands' => array_map(fn (DifficultyBand $band): array => array_filter([
                    'key' => $band->key,
                    'names' => $band->names->texts,
                    'number' => $band->number,
                ], $this->filled(...)), $difficulty->bands),
                'numbers' => $difficulty->numbers,
            ], $difficulty->provenance, $system), $system->difficulties()),
            'roles' => array_map(fn (PartyRole $role): array => $this->withSource([
                'key' => $role->key,
                'names' => $role->names->texts,
                'descriptions' => $this->texts($role->descriptions),
            ], $role->provenance, $system), $system->partyRoles()),
            'translations' => array_map(static fn (array $sources): array => array_map(self::source(...), $sources), $system->translationSources),
        ];

        return array_filter($document, $this->filled(...));
    }

    public function toYaml(GameSystem $system): string
    {
        return Yaml::dump($this->toArray($system), 5, 2, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK | Yaml::DUMP_EMPTY_ARRAY_AS_SEQUENCE);
    }

    public function toJson(GameSystem $system): string
    {
        return json_encode($this->toArray($system), \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_THROW_ON_ERROR)."\n";
    }

    /**
     * @return array<string, string>
     */
    public static function source(Provenance $provenance): array
    {
        $source = ['title' => $provenance->title, 'licence' => $provenance->licence->value];

        if ('' !== $provenance->attribution) {
            $source['attribution'] = $provenance->attribution;
        }

        if (null !== $provenance->author) {
            $source['author'] = $provenance->author;
        }

        if ($provenance->url instanceof Url) {
            $source['url'] = $provenance->url->toString();
        }

        return $source;
    }

    /**
     * @param array<string, mixed> $record
     *
     * @return array<string, mixed>
     */
    private function withSource(array $record, Provenance $provenance, GameSystem $system): array
    {
        if (!$provenance->equals($system->provenance)) {
            $record['source'] = self::source($provenance);
        }

        return array_filter($record, $this->filled(...));
    }

    /**
     * @return ?array<string, string>
     */
    private function texts(Translations $translations): ?array
    {
        return $translations->isEmpty() ? null : $translations->texts;
    }

    private function filled(mixed $value): bool
    {
        return null !== $value && '' !== $value && [] !== $value;
    }
}
