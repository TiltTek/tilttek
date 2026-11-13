<?php

declare(strict_types=1);

namespace Tilttek\TableSafety;

use Phil\ContentWarnings\Display;
use Phil\ContentWarnings\WarningPreferences;

/**
 * What a table agreed, gathered from its participants' answers without saying who gave which: for each theme, the
 * strictest boundary and the strictest consent level anyone gave — the most restrictive answer wins —, and how many
 * participants answered.
 */
final readonly class TableAgreement
{
    /**
     * @param array<string, Boundary>     $boundaries by theme key, in key order
     * @param array<string, ConsentLevel> $consent    by theme key, in key order
     */
    private function __construct(
        private array $boundaries,
        private array $consent,
        public int $respondents,
    ) {
    }

    /**
     * @param list<Participation> $participations
     */
    public static function of(array $participations): self
    {
        $boundaries = [];
        $consent = [];
        $respondents = 0;

        foreach ($participations as $participation) {
            if ($participation->linesAndVeils->isEmpty() && $participation->consent->isEmpty()) {
                continue;
            }

            ++$respondents;

            foreach ($participation->linesAndVeils->all() as $theme => $boundary) {
                $kept = $boundaries[$theme] ?? null;
                $boundaries[$theme] = null !== $kept && $kept->isStricterThan($boundary) ? $kept : $boundary;
            }

            foreach ($participation->consent->all() as $theme => $level) {
                $consent[$theme] = ConsentLevel::strictest($level, ...(isset($consent[$theme]) ? [$consent[$theme]] : []));
            }
        }

        ksort($boundaries, \SORT_STRING);
        ksort($consent, \SORT_STRING);

        return new self($boundaries, $consent, $respondents);
    }

    /**
     * @return list<string> the themes behind a line, in key order
     */
    public function lines(): array
    {
        return array_keys(array_filter($this->boundaries, static fn (Boundary $boundary): bool => Boundary::Line === $boundary));
    }

    /**
     * @return list<string> the themes behind a veil and no line, in key order
     */
    public function veils(): array
    {
        return array_keys(array_filter($this->boundaries, static fn (Boundary $boundary): bool => Boundary::Veil === $boundary));
    }

    public function boundary(string $theme): ?Boundary
    {
        return $this->boundaries[$theme] ?? null;
    }

    public function consent(string $theme): ?ConsentLevel
    {
        return $this->consent[$theme] ?? null;
    }

    /**
     * @return array<string, ConsentLevel> the strictest level of each theme answered, by key
     */
    public function checklist(): array
    {
        return $this->consent;
    }

    /**
     * @return list<string> the themes given the level, in key order
     */
    public function themesAt(ConsentLevel $level): array
    {
        return array_keys(array_filter($this->consent, static fn (ConsentLevel $each): bool => $each === $level));
    }

    /**
     * What the table's warned content becomes, as preferences of `phil/content-warnings`: for each theme, the strictest
     * of its boundary and its consent level; the rest is shown.
     */
    public function preferences(): WarningPreferences
    {
        $preferences = WarningPreferences::defaults(Display::Show);
        $displays = [];

        foreach ($this->boundaries as $theme => $boundary) {
            $displays[$theme] = $boundary->display();
        }

        foreach ($this->consent as $theme => $level) {
            $displays[$theme] = Display::strictest($level->display(), ...(isset($displays[$theme]) ? [$displays[$theme]] : []));
        }

        foreach ($displays as $theme => $display) {
            if (Display::Show !== $display) {
                $preferences = $preferences->withTheme($theme, $display);
            }
        }

        return $preferences;
    }
}
