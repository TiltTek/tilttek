<?php

declare(strict_types=1);

namespace Tilttek\TableSafety;

use DateTimeImmutable;
use Phil\ContentWarnings\Catalogue;
use Phil\Resource\ResourceReference;
use Tilttek\TableSafety\Contract\FeedbackStore;
use Tilttek\TableSafety\Contract\ParticipationStore;
use Tilttek\TableSafety\Contract\SignalObserver;
use Tilttek\TableSafety\Contract\SignalStore;
use Tilttek\TableSafety\Contract\TableStore;
use Tilttek\TableSafety\Exception\NotGameMaster;
use Tilttek\TableSafety\Exception\ToolNotEnabled;
use Tilttek\TableSafety\Feedback\Feedback;
use Tilttek\TableSafety\Feedback\FeedbackSummary;
use Tilttek\TableSafety\Feedback\StarsAndWishes;
use Tilttek\TableSafety\SessionZero\Answers;
use Tilttek\TableSafety\SessionZero\Pact;
use Tilttek\TableSafety\Signal\Audience;
use Tilttek\TableSafety\Signal\CheckInTally;
use Tilttek\TableSafety\Signal\Signal;
use Tilttek\TableSafety\Signal\SignalKind;

/**
 * The safety tools of the tables: their settings, the participants' answers before play, the signals during play, the
 * stars and wishes after, the anonymous summaries of each, and the export and erasure of a participant.
 *
 * The application says who the participant is and whether they are the table's game master; the library never stores
 * who raised an anonymous signal, and its summaries never say who gave which answer. The summaries shown to people —
 * the shown agreement, the pact, the stars and wishes — hold answers only in whole groups of a threshold of
 * participants; what protects the table — the lines, veils and consent levels that filter content — applies every
 * answer.
 */
final readonly class TableSafety
{
    /** How many signals a screen reads at most; a check-in's tally reads every answer. */
    public const int SIGNALS_LIMIT = 100;

    public function __construct(
        private Catalogue $catalogue,
        private TableStore $tables,
        private ParticipationStore $participations,
        private SignalStore $signals,
        private FeedbackStore $feedback,
        private ?SignalObserver $observer = null,
        private ?TableSettings $defaults = null,
        private int $feedbackThreshold = FeedbackSummary::DEFAULT_THRESHOLD,
        private int $answersThreshold = RespondentThreshold::DEFAULT,
    ) {
        FeedbackSummary::assertThreshold($feedbackThreshold);
        RespondentThreshold::assert($answersThreshold);
    }

    /**
     * The table's settings, or the defaults for a table that kept none: those given to the constructor, else
     * `TableSettings::defaults()`.
     */
    public function settings(ResourceReference $table): TableSettings
    {
        return $this->tables->settings($table) ?? $this->defaults ?? TableSettings::defaults();
    }

    /**
     * Keeps the table's settings, once their added themes are checked against the catalogue.
     */
    public function configure(ResourceReference $table, TableSettings $settings): void
    {
        new TableThemes($this->catalogue, $settings->customThemes);
        $this->tables->save($table, $settings);
    }

    /**
     * The themes the table answers about: the catalogue's and those it added.
     */
    public function themes(ResourceReference $table): TableThemes
    {
        return new TableThemes($this->catalogue, $this->settings($table)->customThemes);
    }

    /**
     * The participant's answers at the table, empty when they gave none.
     */
    public function participation(ResourceReference $table, ResourceReference $participant, DateTimeImmutable $at): Participation
    {
        return $this->participations->find($table, $participant) ?? Participation::empty($table, $participant, $at);
    }

    public function answerLinesAndVeils(ResourceReference $table, ResourceReference $participant, LinesAndVeils $answers, DateTimeImmutable $at): Participation
    {
        $this->assertUses($table, SafetyTool::LinesAndVeils);
        $this->themes($table)->assertKnown($answers->all());

        return $this->keep($this->participation($table, $participant, $at)->withLinesAndVeils($answers, $at));
    }

    public function answerConsent(ResourceReference $table, ResourceReference $participant, ConsentChecklist $checklist, DateTimeImmutable $at): Participation
    {
        $this->assertUses($table, SafetyTool::ConsentChecklist);
        $this->themes($table)->assertKnown($checklist->all());

        return $this->keep($this->participation($table, $participant, $at)->withConsent($checklist, $at));
    }

    /**
     * @param array<array-key, mixed> $answers by question key, as the table's session zero accepts them
     */
    public function answerSessionZero(ResourceReference $table, ResourceReference $participant, array $answers, DateTimeImmutable $at): Participation
    {
        $settings = $this->settings($table);

        if (!$settings->uses(SafetyTool::SessionZero)) {
            throw ToolNotEnabled::at(SafetyTool::SessionZero);
        }

        return $this->keep($this->participation($table, $participant, $at)->withSessionZero($settings->sessionZero->answer($answers), $at));
    }

    /**
     * Takes the participant's answers off the table, when they leave it.
     */
    public function leave(ResourceReference $table, ResourceReference $participant): void
    {
        $this->participations->remove($table, $participant);
    }

    /**
     * What the table agreed, without saying who gave it: the most restrictive answer of each theme, from every answer,
     * for what protects the table; and the summary shown to people, in whole groups of the answers threshold given to
     * the constructor, 3 by default, taken in the order of the participants' first answers.
     */
    public function agreement(ResourceReference $table): TableAgreement
    {
        return TableAgreement::of($this->participationsOf($table), $this->answersThreshold);
    }

    /**
     * The table's pact: its answers to the session zero, without saying who gave which, in whole groups of the answers
     * threshold given to the constructor, 3 by default, taken in the order of the participants' first answers.
     */
    public function pact(ResourceReference $table): Pact
    {
        $template = $this->settings($table)->sessionZero;
        $answers = array_map(static fn (Participation $participation): Answers => $participation->sessionZero, $this->participationsOf($table));

        return Pact::of($template, $answers, $this->answersThreshold);
    }

    /**
     * Raises a signal at the table: anonymous unless the table chose named signals, seen by whom the table's settings
     * say. Only the game master asks for a check-in.
     */
    public function raise(ResourceReference $table, ResourceReference $participant, SignalKind $kind, bool $gameMaster, DateTimeImmutable $at): Signal
    {
        $settings = $this->settings($table);

        if (!$settings->uses($kind->tool())) {
            throw ToolNotEnabled::at($kind->tool());
        }

        if ($kind->isGameMasters() && !$gameMaster) {
            throw NotGameMaster::for($kind);
        }

        $signal = new Signal(Signal::newId(), $table, $kind, $settings->audienceOf($kind), $at, $settings->names($kind) ? $participant : null);
        $this->signals->add($signal);
        $this->observer?->raised($signal);

        return $signal;
    }

    /**
     * The game master marks a signal as seen; false when the table has no such signal or it was already seen.
     */
    public function acknowledge(ResourceReference $table, string $id, DateTimeImmutable $at): bool
    {
        $signal = $this->signals->acknowledge($table, $id, $at);

        if (!$signal instanceof Signal) {
            return false;
        }

        $this->observer?->acknowledged($signal);

        return true;
    }

    /**
     * The table's last signals, or those after a moment, in the order they were raised: all of them for the game
     * master, those the whole table sees for the others.
     *
     * @return list<Signal>
     */
    public function signals(ResourceReference $table, bool $gameMaster, ?DateTimeImmutable $after = null): array
    {
        $signals = $this->signals->recent($table, $after, self::SIGNALS_LIMIT);

        return $gameMaster ? $signals : array_values(array_filter($signals, static fn (Signal $signal): bool => Audience::Table === $signal->audience));
    }

    /**
     * The answers to the table's last check-in, counted: every one of them, however many signals followed.
     */
    public function checkIn(ResourceReference $table): CheckInTally
    {
        return CheckInTally::of($this->signals->lastCheckIn($table));
    }

    public function giveFeedback(ResourceReference $table, string $session, ResourceReference $participant, StarsAndWishes $starsAndWishes, DateTimeImmutable $at): Feedback
    {
        $this->assertUses($table, SafetyTool::StarsAndWishes);
        $feedback = new Feedback($table, $session, $participant, $starsAndWishes, $at);
        $this->feedback->save($feedback);

        return $feedback;
    }

    /**
     * The session's stars and wishes, without saying who gave which: those of the first participants who gave them, in
     * whole groups of the feedback threshold given to the constructor, 3 by default.
     */
    public function feedback(ResourceReference $table, string $session): FeedbackSummary
    {
        Feedback::assertSession($session);

        $given = $this->feedback->ofSession($table, $session);
        usort($given, static fn (Feedback $a, Feedback $b): int => $a->givenAt <=> $b->givenAt);

        return FeedbackSummary::of(array_map(static fn (Feedback $feedback): StarsAndWishes => $feedback->starsAndWishes, $given), $this->feedbackThreshold);
    }

    /**
     * Everything kept about the participant: their answers at each table, the named signals they raised and their
     * stars and wishes.
     *
     * @return array{participations: list<array{table: string, lines_and_veils: array<string, string>, consent: array<string, string>, session_zero: array<string, string|list<string>>, first_answered_at: string, updated_at: string}>, signals: list<array{id: string, table: string, kind: string, audience: string, raised_at: string, raised_by: ?string, acknowledged_at: ?string}>, feedback: list<array{table: string, session: string, stars: list<string>, wishes: list<string>, given_at: string}>}
     */
    public function export(ResourceReference $participant): array
    {
        return [
            'participations' => array_map(static fn (Participation $participation): array => [
                'table' => $participation->table->toString(),
                'lines_and_veils' => $participation->linesAndVeils->toArray(),
                'consent' => $participation->consent->toArray(),
                'session_zero' => $participation->sessionZero->toArray(),
                'first_answered_at' => $participation->firstAnsweredAt->format(\DATE_ATOM),
                'updated_at' => $participation->updatedAt->format(\DATE_ATOM),
            ], $this->participations->ofParticipant($participant)),
            'signals' => array_map(static fn (Signal $signal): array => $signal->toArray(), $this->signals->ofParticipant($participant)),
            'feedback' => array_map(static fn (Feedback $feedback): array => [
                'table' => $feedback->table->toString(),
                'session' => $feedback->session,
                ...$feedback->starsAndWishes->toArray(),
                'given_at' => $feedback->givenAt->format(\DATE_ATOM),
            ], $this->feedback->ofParticipant($participant)),
        ];
    }

    /**
     * Deletes the participant's answers and stars and wishes, and takes their name off the signals they raised.
     * Running it again on an erased participant changes nothing.
     */
    public function erase(ResourceReference $participant): void
    {
        $this->participations->eraseParticipant($participant);
        $this->feedback->eraseParticipant($participant);
        $this->signals->forgetParticipant($participant);
    }

    /**
     * The table's participations, in the order of their first answer: a new participant joins the end and waits for
     * their group, and a participant who changes their answers keeps their place.
     *
     * @return list<Participation>
     */
    private function participationsOf(ResourceReference $table): array
    {
        $participations = $this->participations->ofTable($table);
        usort($participations, Participation::inOrderOfFirstAnswer(...));

        return $participations;
    }

    private function assertUses(ResourceReference $table, SafetyTool $tool): void
    {
        if (!$this->settings($table)->uses($tool)) {
            throw ToolNotEnabled::at($tool);
        }
    }

    private function keep(Participation $participation): Participation
    {
        if ($participation->isEmpty()) {
            $this->participations->remove($participation->table, $participation->participant);
        } else {
            $this->participations->save($participation);
        }

        return $participation;
    }
}
