<?php

declare(strict_types=1);

namespace Tilttek\TableSafetyBundle\Infrastructure\Form;

use Tilttek\TableSafety\Boundary;
use Tilttek\TableSafety\ConsentChecklist;
use Tilttek\TableSafety\ConsentLevel;
use Tilttek\TableSafety\LinesAndVeils;
use Tilttek\TableSafety\TableThemes;

/**
 * Turns the library's lines and veils and checklist into the data of `ThemeAnswersType`, grouped by category, and
 * back.
 */
final class ThemeAnswers
{
    private function __construct()
    {
    }

    /**
     * @param array<string, Boundary|ConsentLevel> $answers by theme key
     *
     * @return array<string, array<string, Boundary|ConsentLevel>> by category, then theme key
     */
    public static function grouped(TableThemes $themes, array $answers): array
    {
        $grouped = [];

        foreach ($answers as $theme => $answer) {
            $group = $themes->isCustom($theme) ? ThemeAnswersType::TABLE_GROUP : $themes->catalogue->theme($theme)?->category;

            if (null !== $group) {
                $grouped[$group][$theme] = $answer;
            }
        }

        return $grouped;
    }

    /**
     * @param array<array-key, mixed> $data what the form returned
     */
    public static function linesAndVeils(array $data): LinesAndVeils
    {
        return LinesAndVeils::fromArray(self::values($data, Boundary::class));
    }

    /**
     * @param array<array-key, mixed> $data what the form returned
     */
    public static function checklist(array $data): ConsentChecklist
    {
        return ConsentChecklist::fromArray(self::values($data, ConsentLevel::class));
    }

    /**
     * The value of each theme given an answer of the class, by theme key.
     *
     * @param array<array-key, mixed>                           $data
     * @param class-string<Boundary>|class-string<ConsentLevel> $class
     *
     * @return array<string, string>
     */
    private static function values(array $data, string $class): array
    {
        $values = [];

        foreach ($data as $group) {
            foreach (\is_array($group) ? $group : [] as $theme => $answer) {
                if ($answer instanceof $class) {
                    $values[(string) $theme] = $answer->value;
                }
            }
        }

        return $values;
    }
}
