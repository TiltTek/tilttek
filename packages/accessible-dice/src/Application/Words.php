<?php

declare(strict_types=1);

namespace Tilttek\AccessibleDice\Application;

use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * The words of the interface: through the translator, in the package's domain, when there is one; in English
 * otherwise. The labels of the faces go through the domain of the dice.
 */
final readonly class Words
{
    public const string DOMAIN = 'tilttek_accessible_dice';

    /** The English words, which the translation files of the package repeat. */
    public const array ENGLISH = [
        'die.standard' => '%sides%-sided die',
        'face.value' => 'worth %value%',
        'face.description' => '%label%: %details%',
        'shape.circle' => 'a circle',
        'shape.square' => 'a square',
        'shape.triangle' => 'a triangle',
        'shape.diamond' => 'a diamond',
        'shape.pentagon' => 'a pentagon',
        'shape.hexagon' => 'a hexagon',
        'shape.star' => 'a star',
        'shape.cross' => 'a cross',
        'shape.heart' => 'a heart',
        'shape.crescent' => 'a crescent',
        'shape.blank' => 'a blank face',
        'roll.with_dice' => '%expression%: %dice%. %outcome%.',
        'roll.without_dice' => '%expression%: %outcome%.',
        'roll.total' => 'Total %total%',
        'roll.gave' => 'Result: %text%',
        'roll.nothing' => 'No result',
        'roll.dropped' => '%die% not counted',
        'roll.by' => '%name% rolled. %roll%',
    ];

    public function __construct(
        private ?TranslatorInterface $translator = null,
        private string $diceDomain = 'tilttek_dice',
    ) {
    }

    /**
     * @param array<string, string|int> $parameters
     */
    public function word(string $key, array $parameters = [], ?string $locale = null): string
    {
        if ($this->translator instanceof TranslatorInterface) {
            return $this->translator->trans($key, $parameters, self::DOMAIN, $locale);
        }

        return strtr(self::ENGLISH[$key] ?? $key, $parameters);
    }

    /**
     * A label of a face or a text a roll gave: a translation key of the dice, or a text.
     */
    public function label(string $label, ?string $locale = null): string
    {
        return $this->translator?->trans($label, [], $this->diceDomain, $locale) ?? $label;
    }
}
