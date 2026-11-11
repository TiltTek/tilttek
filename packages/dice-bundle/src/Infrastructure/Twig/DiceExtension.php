<?php

declare(strict_types=1);

namespace Tilttek\DiceBundle\Infrastructure\Twig;

use Phil\Resource\ResourceReference;
use Symfony\Contracts\Translation\TranslatorInterface;
use Tilttek\Dice\Custom\Face;
use Tilttek\Dice\Die\FudgeDie;
use Tilttek\Dice\Die\StandardDie;
use Tilttek\Dice\Exception\DiceException;
use Tilttek\Dice\Expression\Dice;
use Tilttek\Dice\Expression\Pool;
use Tilttek\Dice\Result\DiceResult;
use Tilttek\Dice\Result\Outputs;
use Tilttek\Dice\Result\PoolResult;
use Tilttek\Dice\Result\RolledDie;
use Tilttek\DiceBundle\Application\DiceRolls;
use Tilttek\DiceBundle\Application\DieAppearance;
use Twig\Attribute\AsTwigFilter;
use Twig\Attribute\AsTwigFunction;
use Twig\Environment;

/**
 * The dice in templates:
 *
 * - `expression|dice_notation(locale)` writes an expression in the notation of a language, or leaves an invalid one as
 *   it is;
 * - `expression|dice_dialect('roll20')` writes it in a table's dialect, or gives null when the dialect cannot;
 * - `dice_roll(expression, locale)` rolls it;
 * - `dice_roll_detail(result, member, locale)` shows every die of a roll, how the result came, a face by its label and
 *   its shape as well as its colour, and the member's dice appearance.
 *
 * The words of the detail — dropped, rerolled, exploded, success, failure — and the labels of the faces go through the
 * translator, in the configured domain, when there is one.
 */
final readonly class DiceExtension
{
    public const string TEMPLATE = '@TilttekDice/roll_detail.html.twig';

    public function __construct(
        private DiceRolls $dice,
        private ?TranslatorInterface $translator = null,
        private string $domain = 'tilttek_dice',
    ) {
    }

    #[AsTwigFilter('dice_notation')]
    public function notation(string $expression, ?string $locale = null): string
    {
        try {
            return $this->dice->write($this->dice->parse($expression, $locale), $locale);
        } catch (DiceException) {
            return $expression;
        }
    }

    #[AsTwigFilter('dice_dialect')]
    public function dialect(string $expression, string $dialect): ?string
    {
        try {
            $translation = $this->dice->translate($this->dice->parse($expression), $dialect);
        } catch (DiceException) {
            return null;
        }

        return $translation->isComplete() ? $translation->text() : null;
    }

    #[AsTwigFunction('dice_roll')]
    public function roll(string $expression, ?string $locale = null): PoolResult
    {
        return $this->dice->roll($expression, $locale);
    }

    #[AsTwigFunction('dice_roll_detail', needsEnvironment: true, isSafe: ['html'])]
    public function detail(Environment $twig, PoolResult $result, ?ResourceReference $member = null, ?string $locale = null): string
    {
        return $twig->render(self::TEMPLATE, ['roll' => $this->view($result, $member, $locale)]);
    }

    /**
     * What the template shows: for each instruction, its notation, its total, the text or list it gave — each text
     * through the translator, as a translation key or a text —, the faces it counted, and each set of dice with every
     * die.
     *
     * @return list<array{notation: string, total: ?int, text: ?string, tally: list<string>, sets: list<array{notation: string, value: int, appearance: ?array{name: string, color: ?string, material: ?string, image: ?string}, dice: list<array{label: string, value: int, shape: ?string, color: ?string, image: ?string, classes: list<string>, states: list<string>}>}>}>
     */
    public function view(PoolResult $result, ?ResourceReference $member = null, ?string $locale = null): array
    {
        $view = [];

        foreach ($result->results as $expression) {
            $sets = $expression->result->sets();
            $view[] = [
                'notation' => $this->dice->write(new Pool($expression->expression), $locale),
                'total' => $expression->total(),
                'text' => $this->output($expression->result->output(), $expression->result instanceof DiceResult),
                'tally' => $this->tally($sets),
                'sets' => array_map(fn (DiceResult $set): array => $this->set($set, $member, $locale), $sets),
            ];
        }

        return $view;
    }

    #[AsTwigFunction('dice_text')]
    public function text(string $message): string
    {
        return $this->translator?->trans($message, [], $this->domain) ?? $message;
    }

    /**
     * @return array{notation: string, value: int, appearance: ?array{name: string, color: ?string, material: ?string, image: ?string}, dice: list<array{label: string, value: int, shape: ?string, color: ?string, image: ?string, classes: list<string>, states: list<string>}>}
     */
    private function set(DiceResult $set, ?ResourceReference $member, ?string $locale): array
    {
        $die = $set->expression instanceof Dice ? $set->expression->die : null;
        $sides = match (true) {
            $die instanceof StandardDie => $die->sides,
            $die instanceof FudgeDie => 6,
            default => null,
        };
        $appearance = !$member instanceof ResourceReference || null === $sides ? null : $this->dice->appearance($member, $sides);

        return [
            'notation' => $this->dice->write(new Pool($set->expression), $locale),
            'value' => $set->value(),
            'appearance' => $appearance instanceof DieAppearance ? ['name' => $appearance->name, 'color' => $appearance->color, 'material' => $appearance->material, 'image' => $appearance->image] : null,
            'dice' => array_map($this->rolledDie(...), $set->dice),
        ];
    }

    /**
     * @return array{label: string, value: int, shape: ?string, color: ?string, image: ?string, classes: list<string>, states: list<string>}
     */
    private function rolledDie(RolledDie $die): array
    {
        $flags = [
            'dropped' => !$die->kept && !$die->rerolled,
            'rerolled' => $die->rerolled,
            'exploded' => $die->exploded,
            'success' => true === $die->success,
            'failure' => false === $die->success,
        ];
        $states = array_keys(array_filter($flags));

        return [
            'label' => $die->face instanceof Face ? $this->label($die->face) : \sprintf('%d', $die->value),
            'value' => $die->value,
            'shape' => $die->face?->shape?->value,
            'color' => $die->face?->color,
            'image' => $die->face?->image,
            'classes' => array_map(static fn (string $state): string => 'is-'.$state, [...$states, ...('initial' === $die->origin->value ? [] : ['from-'.$die->origin->value])]),
            'states' => array_map($this->text(...), $states),
        ];
    }

    /**
     * A text or a list as words, each text through the translator; none for a number, or for the faces of custom dice,
     * which the tally shows.
     *
     * @param int|string|list<int|string> $output
     */
    private function output(int|string|array $output, bool $faces): ?string
    {
        if (\is_int($output) || $faces) {
            return null;
        }

        return Outputs::text(array_map(fn (int|string $item): string => \is_int($item) ? \sprintf('%d', $item) : $this->text($item), Outputs::items($output)));
    }

    private function label(Face $face): string
    {
        return $this->text($face->label);
    }

    /**
     * The faces the kept dice show, by label and in the order they first fell: "2 × Star".
     *
     * @param list<DiceResult> $sets
     *
     * @return list<string>
     */
    private function tally(array $sets): array
    {
        $counts = [];
        $faces = [];

        foreach ($sets as $set) {
            foreach ($set->kept() as $die) {
                if (null !== $die->face) {
                    $faces[$die->face->id] = $die->face;
                    $counts[$die->face->id] = ($counts[$die->face->id] ?? 0) + 1;
                }
            }
        }

        return array_map(fn (string $id): string => \sprintf('%d × %s', $counts[$id], $this->label($faces[$id])), array_keys($counts));
    }
}
