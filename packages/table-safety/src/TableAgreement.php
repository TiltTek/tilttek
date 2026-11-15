<?php

declare(strict_types=1);

namespace Tilttek\TableSafety;

use Phil\ContentWarnings\Display;
use Phil\ContentWarnings\WarningPreferences;

/**
 * What a table agreed, gathered from its participants' answers without saying who gave which, in two parts.
 *
 * What protects the table — `lines()`, `veils()`, `boundary()`, `consent()`, `checklist()`, `themesAt()` and
 * `preferences()` — applies every answer, even under the threshold: for each theme, the strictest boundary and the
 * strictest consent level anyone gave; the most restrictive answer wins. A line or a veil someone set always counts.
 *
 * What is shown to people as a summary — `shown()`, `respondents` and `hasEnoughAnswers()` — holds answers only in whole
 * groups of `$threshold` participants, as `AgreementSummary` says.
 */
final readonly class TableAgreement
{
    /**
     * @param int          $respondents how many participants' answers the shown summary holds: 0, or a multiple of the threshold
     * @param positive-int $threshold
     */
    private function __construct(
        private AgreementSummary $everyAnswer,
        private AgreementSummary $shown,
        public int $respondents,
        public int $threshold,
    ) {
    }

    /**
     * @param list<Participation> $participations in the order they were given
     *
     * @throws Exception\InvalidAnswer when the threshold is under 1
     */
    public static function of(array $participations, int $threshold = RespondentThreshold::DEFAULT): self
    {
        $shown = AgreementSummary::of($participations, $threshold);

        return new self(AgreementSummary::of($participations, 1), $shown, $shown->respondents, $shown->threshold);
    }

    /**
     * The agreement as it is shown to people: the answers of whole groups of the threshold only.
     */
    public function shown(): AgreementSummary
    {
        return $this->shown;
    }

    /**
     * Whether the shown summary holds any answer; false means "not enough answers yet". The protective answers apply
     * either way.
     */
    public function hasEnoughAnswers(): bool
    {
        return $this->shown->hasEnoughAnswers();
    }

    /**
     * @return list<string> the themes behind a line, in key order, from every answer
     */
    public function lines(): array
    {
        return $this->everyAnswer->lines();
    }

    /**
     * @return list<string> the themes behind a veil and no line, in key order, from every answer
     */
    public function veils(): array
    {
        return $this->everyAnswer->veils();
    }

    /**
     * The strictest boundary of the theme, from every answer.
     */
    public function boundary(string $theme): ?Boundary
    {
        return $this->everyAnswer->boundary($theme);
    }

    /**
     * The strictest consent level of the theme, from every answer.
     */
    public function consent(string $theme): ?ConsentLevel
    {
        return $this->everyAnswer->consent($theme);
    }

    /**
     * @return array<string, ConsentLevel> the strictest level of each theme answered, by key, from every answer
     */
    public function checklist(): array
    {
        return $this->everyAnswer->checklist();
    }

    /**
     * @return list<string> the themes given the level, in key order, from every answer
     */
    public function themesAt(ConsentLevel $level): array
    {
        return $this->everyAnswer->themesAt($level);
    }

    /**
     * What the table's warned content becomes, as preferences of `phil/content-warnings`, from every answer: for each
     * theme, the strictest of its boundary and its consent level; the rest is shown.
     */
    public function preferences(): WarningPreferences
    {
        $preferences = WarningPreferences::defaults(Display::Show);
        $displays = [];

        foreach ($this->everyAnswer->boundaries() as $theme => $boundary) {
            $displays[$theme] = $boundary->display();
        }

        foreach ($this->everyAnswer->checklist() as $theme => $level) {
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
