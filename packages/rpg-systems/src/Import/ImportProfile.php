<?php

declare(strict_types=1);

namespace Tilttek\RpgSystems\Import;

use Tilttek\RpgSystems\Exception\InvalidRecord;
use Tilttek\RpgSystems\Licence;
use Tilttek\RpgSystems\Text;

/**
 * How an extract of an open source turns into a system: the system's key and name, the licences its material may be
 * under, the table of its attributes and their bounds, the tables of its terms, and the records the source states once
 * for all — the rolls the system names, its rule variants, its difficulty numbers and its party roles —, which the
 * profile declares as the source gives them.
 *
 * @phpstan-type DifficultyData array{key: string, name: string, description?: string, method?: string, bands?: list<array{key: string, name: string, number?: int}>, numbers?: array<array-key, mixed>}
 */
final readonly class ImportProfile
{
    /**
     * @param string                $system       the key of the system made, `srd-5.2`
     * @param string                $name         its name, in the extract's locale
     * @param list<Licence>         $licences     the licences the source may be under; any other is refused
     * @param ?TableMapping         $attributes   the table of the attributes
     * @param list<TableMapping>    $terms        the tables of the terms, each with its category
     * @param array<string, string> $dice         the rolls the system names: key => notation of `tilttek/dice`
     * @param array<string, string> $diceNames    the names of those rolls, in the extract's locale
     * @param string                $locale       the language of the extract
     * @param array<string, string> $variants     the rule variants the source offers: key => name, in the extract's locale
     * @param list<DifficultyData>  $difficulties the difficulty numbers the source declares, names in the extract's locale
     * @param array<string, string> $partyRoles   the roles the source declares for a party: key => name
     *
     * @throws InvalidRecord
     */
    public function __construct(
        public string $system,
        public string $name,
        public array $licences,
        public ?string $version = null,
        public ?TableMapping $attributes = null,
        public array $terms = [],
        public array $dice = [],
        public array $diceNames = [],
        public string $locale = 'en',
        public ?int $attributeMinimum = null,
        public ?int $attributeMaximum = null,
        public array $variants = [],
        public array $difficulties = [],
        public array $partyRoles = [],
    ) {
        Text::key($system, 'a system');

        if ([] === $licences) {
            throw InvalidRecord::because(\sprintf('The profile of "%s" accepts no licence.', $system));
        }
    }

    /**
     * The same profile for another version of its source — `5.2.1` for the 5.2 profile, a year for a source without
     * numbered versions —, so that the system made carries the version of the files it is imported from.
     *
     * @throws InvalidRecord when the version is empty, holds control characters or is too long
     */
    public function withVersion(string $version): self
    {
        Text::check($version, \sprintf('The version of the profile "%s"', $this->system));

        return new self(
            $this->system,
            $this->name,
            $this->licences,
            $version,
            $this->attributes,
            $this->terms,
            $this->dice,
            $this->diceNames,
            $this->locale,
            $this->attributeMinimum,
            $this->attributeMaximum,
            $this->variants,
            $this->difficulties,
            $this->partyRoles,
        );
    }

    /**
     * The System Reference Document 5.1, under CC BY 4.0: the same tables as the 5.2 profile — `ability-scores`,
     * `skills`, `conditions`, `damage-types` and `magic-schools` —, the d20 roll with advantage and disadvantage, the
     * two variants of its rules — encumbrance, and skills with different abilities — and its typical difficulty
     * classes. It has no rules of encounter difficulty and declares no party role.
     */
    public static function srd51(): self
    {
        return new self(
            'srd-5.1',
            'System Reference Document 5.1',
            [Licence::CcBy40],
            '5.1',
            new TableMapping('ability-scores', name: 'full_name', abbreviation: 'name', description: 'desc'),
            [
                new TableMapping('skills', 'skill', description: 'desc'),
                new TableMapping('conditions', 'condition', description: 'desc'),
                new TableMapping('damage-types', 'damage-type', description: 'desc'),
                new TableMapping('magic-schools', 'magic-school', description: 'desc'),
            ],
            ['d20-roll' => '1d20', 'advantage' => '2d20kh1', 'disadvantage' => '2d20kl1'],
            ['d20-roll' => 'd20 Roll', 'advantage' => 'Advantage', 'disadvantage' => 'Disadvantage'],
            attributeMinimum: 1,
            attributeMaximum: 30,
            variants: ['encumbrance' => 'Encumbrance', 'skills-with-different-abilities' => 'Skills with Different Abilities'],
            difficulties: [self::typicalDifficultyClasses()],
        );
    }

    /**
     * Fate Core, under CC BY 3.0: no attributes; the skills, the approaches of Fate Accelerated and the steps of the
     * ladder as terms, and the roll of four Fate dice. The extract holds the tables `skills`, `approaches` and
     * `ladder` — `key`, `name` —, each with an optional `description`.
     */
    public static function fateCore(): self
    {
        return new self(
            'fate-core',
            'Fate Core System',
            [Licence::CcBy30],
            null,
            null,
            [
                new TableMapping('skills', 'skill', 'key', description: 'description'),
                new TableMapping('approaches', 'approach', 'key', description: 'description'),
                new TableMapping('ladder', 'ladder', 'key', description: 'description'),
            ],
            ['fate-roll' => '4dF'],
            ['fate-roll' => 'Fate roll'],
        );
    }

    /**
     * The System Reference Document 5.2, under CC BY 4.0: the six ability scores from 1 to 30, the skills,
     * conditions, damage types and schools of magic, the d20 test with advantage and disadvantage, its typical
     * difficulty classes, and the difficulty of a combat encounter — the XP budget of each character by level for a
     * low, moderate or high difficulty, and the XP of a creature by its challenge rating, read by the method `budget`.
     * It names no rule variant and declares no party role. The extract holds the tables `ability-scores` — `index`,
     * `name` the abbreviation, `full_name` —, `skills`, `conditions`, `damage-types` and `magic-schools` — `index`,
     * `name` —, each with an optional `desc`.
     */
    public static function srd52(): self
    {
        return new self(
            'srd-5.2',
            'System Reference Document 5.2',
            [Licence::CcBy40],
            '5.2',
            new TableMapping('ability-scores', name: 'full_name', abbreviation: 'name', description: 'desc'),
            [
                new TableMapping('skills', 'skill', description: 'desc'),
                new TableMapping('conditions', 'condition', description: 'desc'),
                new TableMapping('damage-types', 'damage-type', description: 'desc'),
                new TableMapping('magic-schools', 'magic-school', description: 'desc'),
            ],
            ['d20-test' => '1d20', 'advantage' => '2d20kh1', 'disadvantage' => '2d20kl1'],
            ['d20-test' => 'D20 Test', 'advantage' => 'Advantage', 'disadvantage' => 'Disadvantage'],
            attributeMinimum: 1,
            attributeMaximum: 30,
            difficulties: [self::typicalDifficultyClasses(), self::srd52Encounters()],
        );
    }

    /**
     * Pathfinder Second Edition, under the ORC licence: the attributes, skills, conditions and traits, the check
     * with fortune and misfortune, four variant rules — automatic bonus progression, dual-class characters, free
     * archetype, proficiency without level —, and its difficulty numbers: the simple DCs by proficiency rank, the DCs
     * by level with the adjustments of each difficulty, and the encounter budget of a party of four with its character
     * adjustment and the XP of a creature by its level against the party's, read by the method `level`. It declares no
     * party role. The extract holds the tables `attributes` — `slug`, `name`, `abbreviation` —,
     * `skills`, `conditions` and `traits` — `slug`, `name` —, each with an optional `description`.
     */
    public static function pathfinder2e(): self
    {
        return new self(
            'pf2e',
            'Pathfinder Second Edition',
            [Licence::Orc],
            null,
            new TableMapping('attributes', key: 'slug', abbreviation: 'abbreviation', description: 'description'),
            [
                new TableMapping('skills', 'skill', 'slug', description: 'description'),
                new TableMapping('conditions', 'condition', 'slug', description: 'description'),
                new TableMapping('traits', 'trait', 'slug', description: 'description'),
            ],
            ['check' => '1d20', 'fortune' => '2d20kh1', 'misfortune' => '2d20kl1'],
            ['check' => 'Check', 'fortune' => 'Fortune', 'misfortune' => 'Misfortune'],
            variants: [
                'automatic-bonus-progression' => 'Automatic Bonus Progression',
                'dual-class' => 'Dual-Class Characters',
                'free-archetype' => 'Free Archetype',
                'proficiency-without-level' => 'Proficiency Without Level',
            ],
            difficulties: self::pathfinder2eDifficulties(),
        );
    }

    /**
     * The typical difficulty classes of the System Reference Documents 5.1 and 5.2, read by the method `check`: each
     * band's number is the class of a check of that difficulty.
     *
     * @return DifficultyData
     */
    private static function typicalDifficultyClasses(): array
    {
        return [
            'key' => 'check',
            'name' => 'Typical Difficulty Classes',
            'method' => 'check',
            'bands' => [
                ['key' => 'very-easy', 'name' => 'Very easy', 'number' => 5],
                ['key' => 'easy', 'name' => 'Easy', 'number' => 10],
                ['key' => 'medium', 'name' => 'Medium', 'number' => 15],
                ['key' => 'hard', 'name' => 'Hard', 'number' => 20],
                ['key' => 'very-hard', 'name' => 'Very hard', 'number' => 25],
                ['key' => 'nearly-impossible', 'name' => 'Nearly impossible', 'number' => 30],
            ],
        ];
    }

    /**
     * The difficulty of a combat encounter in the System Reference Document 5.2, read by the method `budget`: the XP
     * budget of a character by its level for each difficulty, and the XP of a creature by its challenge rating — 10
     * for challenge 0, the value of a creature with an effective attack.
     *
     * @return DifficultyData
     */
    private static function srd52Encounters(): array
    {
        $levels = [
            [50, 75, 100], [100, 150, 200], [150, 225, 400], [250, 375, 500], [500, 750, 1_100],
            [600, 1_000, 1_400], [750, 1_300, 1_700], [1_000, 1_700, 2_100], [1_300, 2_000, 2_600], [1_600, 2_300, 3_100],
            [1_900, 2_900, 4_100], [2_200, 3_700, 4_700], [2_600, 4_200, 5_400], [2_900, 4_900, 6_200], [3_300, 5_400, 7_800],
            [3_800, 6_100, 9_800], [4_500, 7_200, 11_700], [5_000, 8_700, 14_200], [5_500, 10_700, 17_200], [6_400, 13_200, 22_000],
        ];
        $budgets = [];

        foreach ($levels as $index => [$low, $moderate, $high]) {
            $budgets[(string) ($index + 1)] = ['low' => $low, 'moderate' => $moderate, 'high' => $high];
        }

        return [
            'key' => 'encounter',
            'name' => 'XP Budget per Character',
            'method' => 'budget',
            'bands' => [
                ['key' => 'low', 'name' => 'Low'],
                ['key' => 'moderate', 'name' => 'Moderate'],
                ['key' => 'high', 'name' => 'High'],
            ],
            'numbers' => [
                'budgets' => $budgets,
                'costs' => [
                    '0' => 10, '1/8' => 25, '1/4' => 50, '1/2' => 100, '1' => 200, '2' => 450, '3' => 700, '4' => 1_100,
                    '5' => 1_800, '6' => 2_300, '7' => 2_900, '8' => 3_900, '9' => 5_000, '10' => 5_900, '11' => 7_200,
                    '12' => 8_400, '13' => 10_000, '14' => 11_500, '15' => 13_000, '16' => 15_000, '17' => 18_000,
                    '18' => 20_000, '19' => 22_000, '20' => 25_000, '21' => 33_000, '22' => 41_000, '23' => 50_000,
                    '24' => 62_000, '25' => 75_000, '26' => 90_000, '27' => 105_000, '28' => 120_000, '29' => 135_000,
                    '30' => 155_000,
                ],
            ],
        ];
    }

    /**
     * The difficulty numbers of Pathfinder Second Edition: the simple DCs, read by the method `check`; the DCs by
     * level, read by the method `level-dc` — the DC of each level under `levels`, and each band's number the
     * adjustment of its difficulty —; and the encounter budget, read by the method `level`.
     *
     * @return list<DifficultyData>
     */
    private static function pathfinder2eDifficulties(): array
    {
        $levels = [14, 15, 16, 18, 19, 20, 22, 23, 24, 26, 27, 28, 30, 31, 32, 34, 35, 36, 38, 39, 40, 42, 44, 46, 48, 50];

        return [
            [
                'key' => 'simple',
                'name' => 'Simple DCs',
                'method' => 'check',
                'bands' => [
                    ['key' => 'untrained', 'name' => 'Untrained', 'number' => 10],
                    ['key' => 'trained', 'name' => 'Trained', 'number' => 15],
                    ['key' => 'expert', 'name' => 'Expert', 'number' => 20],
                    ['key' => 'master', 'name' => 'Master', 'number' => 30],
                    ['key' => 'legendary', 'name' => 'Legendary', 'number' => 40],
                ],
            ],
            [
                'key' => 'level',
                'name' => 'DCs by Level',
                'method' => 'level-dc',
                'bands' => [
                    ['key' => 'incredibly-easy', 'name' => 'Incredibly easy', 'number' => -10],
                    ['key' => 'very-easy', 'name' => 'Very easy', 'number' => -5],
                    ['key' => 'easy', 'name' => 'Easy', 'number' => -2],
                    ['key' => 'hard', 'name' => 'Hard', 'number' => 2],
                    ['key' => 'very-hard', 'name' => 'Very hard', 'number' => 5],
                    ['key' => 'incredibly-hard', 'name' => 'Incredibly hard', 'number' => 10],
                ],
                'numbers' => ['levels' => $levels],
            ],
            [
                'key' => 'encounter',
                'name' => 'Encounter Budget',
                'method' => 'level',
                'bands' => [
                    ['key' => 'trivial', 'name' => 'Trivial'],
                    ['key' => 'low', 'name' => 'Low'],
                    ['key' => 'moderate', 'name' => 'Moderate'],
                    ['key' => 'severe', 'name' => 'Severe'],
                    ['key' => 'extreme', 'name' => 'Extreme'],
                ],
                'numbers' => [
                    'party-size' => 4,
                    'budgets' => ['trivial' => 40, 'low' => 60, 'moderate' => 80, 'severe' => 120, 'extreme' => 160],
                    'adjustments' => ['trivial' => 10, 'low' => 15, 'moderate' => 20, 'severe' => 30, 'extreme' => 40],
                    'costs' => ['-4' => 10, '-3' => 15, '-2' => 20, '-1' => 30, '0' => 40, '1' => 60, '2' => 80, '3' => 120, '4' => 160],
                ],
            ],
        ];
    }
}
