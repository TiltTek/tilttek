<?php

declare(strict_types=1);

namespace Tilttek\TableSafety\Tests\Benchmark;

use DateTimeImmutable;
use Generator;
use Phil\ContentWarnings\BaseCatalogue;
use Phil\ContentWarnings\Catalogue;
use Phil\Resource\ResourceReference;
use Tilttek\TableSafety\Boundary;
use Tilttek\TableSafety\ConsentChecklist;
use Tilttek\TableSafety\ConsentLevel;
use Tilttek\TableSafety\LinesAndVeils;
use Tilttek\TableSafety\Participation;
use Tilttek\TableSafety\SessionZero\Answers;
use Tilttek\TableSafety\TableAgreement;

/**
 * What a table's screen computes at each display: the agreement of its participants — every one of them answering
 * every theme of the base list, lines and veils and checklist — and the warning preferences it stands for; and what a
 * store does at each read: a participant's answers read back from their array.
 *
 * @BeforeMethods("setUp")
 */
final class AgreementBench
{
    /** @var list<Participation> */
    private array $participations = [];

    /** @var array<string, string> */
    private array $stored = [];

    private readonly Catalogue $catalogue;

    public function __construct()
    {
        $this->catalogue = BaseCatalogue::load();
    }

    /**
     * @param array{participants: int} $params
     */
    public function setUp(array $params): void
    {
        $themes = array_keys($this->catalogue->themes());
        $boundaries = Boundary::cases();
        $levels = ConsentLevel::cases();
        $this->participations = [];

        for ($participant = 0; $participant < $params['participants']; ++$participant) {
            $lines = [];
            $checklist = [];

            foreach ($themes as $index => $theme) {
                $lines[$theme] = $boundaries[($index + $participant) % 2]->value;
                $checklist[$theme] = $levels[($index + $participant) % 3]->value;
            }

            $this->stored = $lines;
            $this->participations[] = new Participation(
                ResourceReference::of('table', '1'),
                ResourceReference::of('member', (string) $participant),
                LinesAndVeils::fromArray($lines),
                ConsentChecklist::fromArray($checklist),
                Answers::none(),
                new DateTimeImmutable('2026-11-12 20:00 UTC'),
            );
        }
    }

    /**
     * @return Generator<string, array{participants: int}>
     */
    public function provideTables(): Generator
    {
        yield 'a table of 6' => ['participants' => 6];
        yield 'a convention table of 30' => ['participants' => 30];
    }

    /**
     * @ParamProviders("provideTables")
     *
     * @Revs(50)
     */
    public function benchAgreement(): void
    {
        TableAgreement::of($this->participations)->preferences();
    }

    /**
     * @ParamProviders("provideTables")
     *
     * @Revs(200)
     */
    public function benchReadingAnswersBack(): void
    {
        LinesAndVeils::fromArray($this->stored);
    }
}
