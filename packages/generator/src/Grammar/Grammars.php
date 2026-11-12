<?php

declare(strict_types=1);

namespace Tilttek\Generator\Grammar;

use Tilttek\Generator\Exception\InvalidDefinition;

/**
 * The grammars by language: French, English, Spanish, German, Italian and Portuguese built in — Portuguese of Brazil
 * under `pt` and of Portugal under `pt_PT` —, more added or replaced by an application. A locale is found as written,
 * then in its usual form (`pt-pt` as `pt_PT`), then by its language: `fr_CA` finds the grammar of `fr` when it has none
 * of its own.
 */
final readonly class Grammars
{
    /**
     * @var array<string, Grammar>
     */
    private array $grammars;

    /**
     * @param array<string, Grammar> $grammars more grammars, by locale or language
     */
    public function __construct(array $grammars = [])
    {
        $this->grammars = $grammars + [
            'fr' => new French(),
            'en' => new English(),
            'es' => new Spanish(),
            'de' => new German(),
            'it' => new Italian(),
            'pt' => new Portuguese(),
            'pt_PT' => new Portuguese(zeroIsSingular: false),
        ];
    }

    public function has(string $locale): bool
    {
        return $this->find($locale) instanceof Grammar;
    }

    public function for(string $locale): Grammar
    {
        return $this->find($locale) ?? throw InvalidDefinition::at($locale, 'no grammar is known for this language');
    }

    private function find(string $locale): ?Grammar
    {
        $parts = explode('_', strtr($locale, '-', '_'));
        $language = strtolower($parts[0]);

        return $this->grammars[$locale] ?? $this->grammars[$language.'_'.strtoupper($parts[1] ?? '')] ?? $this->grammars[$language] ?? null;
    }
}
