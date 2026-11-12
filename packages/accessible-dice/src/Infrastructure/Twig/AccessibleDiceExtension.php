<?php

declare(strict_types=1);

namespace Tilttek\AccessibleDice\Infrastructure\Twig;

use Tilttek\AccessibleDice\Application\AccessibleDice;
use Tilttek\AccessibleDice\Application\DieChoice;
use Tilttek\AccessibleDice\Application\FaceDescriber;
use Tilttek\AccessibleDice\Application\FaceDescription;
use Tilttek\AccessibleDice\Application\Politeness;
use Tilttek\AccessibleDice\Application\RollAnnouncer;
use Tilttek\AccessibleDice\Application\Words;
use Tilttek\Dice\Result\PoolResult;
use Twig\Attribute\AsTwigFunction;
use Twig\Environment;

/**
 * The accessible dice in templates:
 *
 * - `accessible_dice(action, ...)` draws the form: the dice offered as large choices, each face of a custom die
 *   described, how many and a modifier, the expression they make, an opt-in to hear the results read aloud, and the
 *   region where a result is announced — the one the form was answered with, then each roll of a dice tower on the
 *   same page;
 * - `accessible_dice_announcement(result, name)` gives the sentence a roll is announced with;
 * - `accessible_dice_faces(die)` describes the faces of a custom die.
 */
final readonly class AccessibleDiceExtension
{
    public const string TEMPLATE = '@TilttekAccessibleDice/dice.html.twig';

    /** The sentences the Stimulus controller announces a roll of a dice tower with, their placeholders kept. */
    public const array SENTENCES = ['with_dice' => 'roll.with_dice', 'without_dice' => 'roll.without_dice', 'total' => 'roll.total', 'gave' => 'roll.gave', 'nothing' => 'roll.nothing', 'dropped' => 'roll.dropped', 'by' => 'roll.by'];

    public function __construct(
        private AccessibleDice $dice,
        private FaceDescriber $describer,
        private RollAnnouncer $announcer,
        private Words $words = new Words(),
        private Politeness $politeness = Politeness::Polite,
    ) {
    }

    /**
     * @param string                              $action     where the form is sent
     * @param string                              $field      the name the choice is sent under: `dice[die]`, `dice[count]`, `dice[modifier]`
     * @param ?array{name: string, value: string} $token      a hidden field the application checks, such as its CSRF token
     * @param ?PoolResult                         $result     the roll the form was answered with, announced
     * @param ?string                             $name       the roller's name, said before that roll
     * @param ?string                             $politeness polite or assertive; the configured one when null
     * @param string                              $id         what the identifiers of the form start with, distinct for each form of a page
     */
    #[AsTwigFunction('accessible_dice', needsEnvironment: true, isSafe: ['html'])]
    public function form(Environment $twig, string $action, string $field = 'dice', ?array $token = null, ?PoolResult $result = null, ?string $name = null, ?string $politeness = null, string $id = 'tilttek-accessible-dice', ?string $locale = null): string
    {
        return $twig->render(self::TEMPLATE, ['dice' => $this->view($action, $field, $token, $result, $name, $politeness, $id, $locale)]);
    }

    /**
     * What the form draws.
     *
     * @param ?array{name: string, value: string} $token
     *
     * @return array{action: string, field: string, token: ?array{name: string, value: string}, id: string, choices: list<array{name: string, notation: string, label: string, faces: list<array{id: string, label: string, description: string, value: ?int, shape: ?string, color: ?string, image: ?string}>}>, most: int, politeness: string, announcement: string, labels: array<string, array<string, string>>, sentences: array<string, string>}
     */
    public function view(string $action, string $field = 'dice', ?array $token = null, ?PoolResult $result = null, ?string $name = null, ?string $politeness = null, string $id = 'tilttek-accessible-dice', ?string $locale = null): array
    {
        return [
            'action' => $action,
            'field' => $field,
            'token' => $token,
            'id' => $id,
            'choices' => array_map(static fn (DieChoice $choice): array => [
                'name' => $choice->name,
                'notation' => $choice->notation,
                'label' => $choice->label,
                'faces' => array_map(static fn (FaceDescription $face): array => (array) $face, $choice->faces),
            ], $this->dice->choices($locale)),
            'most' => $this->dice->most(),
            'politeness' => (null === $politeness ? $this->politeness : Politeness::from($politeness))->value,
            'announcement' => $result instanceof PoolResult ? $this->announcer->announce($result, $name, $locale) : '',
            'labels' => $this->describer->labels($locale),
            'sentences' => array_map(fn (string $key): string => $this->words->word($key, [], $locale), self::SENTENCES),
        ];
    }

    #[AsTwigFunction('accessible_dice_announcement')]
    public function announcement(PoolResult $result, ?string $name = null, ?string $locale = null): string
    {
        return $this->announcer->announce($result, $name, $locale);
    }

    /**
     * @return list<FaceDescription>
     */
    #[AsTwigFunction('accessible_dice_faces')]
    public function faces(string $die, ?string $locale = null): array
    {
        return $this->describer->describe($die, $locale);
    }
}
