<?php

declare(strict_types=1);

namespace Tilttek\TableSafetyBundle\Infrastructure\Http;

use DateTimeImmutable;
use Phil\ContentWarnings\Theme;
use Phil\Resource\ResourceReference;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Tilttek\TableSafety\ConsentLevel;
use Tilttek\TableSafety\SafetyTool;
use Tilttek\TableSafety\SessionZero\QuestionKind;
use Tilttek\TableSafety\Signal\SignalKind;
use Tilttek\TableSafety\TableSafety;

/**
 * The views the templates draw from: plain arrays of labels and counts, never a participant's reference.
 */
final readonly class Views
{
    /** The tools that have a printed card, in the order they are printed. */
    public const array PRINTED_TOOLS = [SafetyTool::XCard, SafetyTool::LinesAndVeils, SafetyTool::ConsentChecklist];

    public function __construct(
        private TableSafety $safety,
        private UrlGeneratorInterface $urls,
    ) {
    }

    /**
     * The signals the viewer may raise now: those of the table's tools, the check-in for the game master alone, and the
     * answers to a check-in while one is open.
     *
     * @return list<SignalKind>
     */
    public function signalKinds(ResourceReference $table, bool $gameMaster): array
    {
        $settings = $this->safety->settings($table);
        $open = $settings->uses(SafetyTool::CheckIn) && $this->safety->checkIn($table)->askedAt instanceof DateTimeImmutable;

        return array_values(array_filter(
            SignalKind::cases(),
            static fn (SignalKind $kind): bool => $settings->uses($kind->tool()) && (!$kind->isGameMasters() || $gameMaster) && (!$kind->isAnswer() || $open),
        ));
    }

    /**
     * What the table agreed, by labels in the viewer's locale, without saying who gave which answer. The game master
     * sees the protective list, made of every answer from the first one; the players see the summary shown to people,
     * in whole groups of the answers threshold, empty until the first group is full.
     *
     * @return array{protective: bool, enough: bool, threshold: int, respondents: int, lines: list<string>, veils: list<string>, red: list<string>, yellow: list<string>, green: list<string>}
     */
    public function agreement(ResourceReference $table, string $locale, bool $gameMaster): array
    {
        $agreement = $this->safety->agreement($table);
        $view = $gameMaster ? $agreement : $agreement->shown();
        $themes = $this->safety->themes($table);
        $labels = static fn (array $keys): array => array_map(static fn (string $key): string => $themes->label($key, $locale), $keys);

        return [
            'protective' => $gameMaster,
            'enough' => $agreement->hasEnoughAnswers(),
            'threshold' => $agreement->threshold,
            'respondents' => $agreement->respondents,
            'lines' => $labels($view->lines()),
            'veils' => $labels($view->veils()),
            'red' => $labels($view->themesAt(ConsentLevel::Red)),
            'yellow' => $labels($view->themesAt(ConsentLevel::Yellow)),
            'green' => $labels($view->themesAt(ConsentLevel::Green)),
        ];
    }

    /**
     * The tools of the table that have a printed card, in the order they are printed.
     *
     * @return list<SafetyTool>
     */
    public function printedTools(ResourceReference $table): array
    {
        $settings = $this->safety->settings($table);

        return array_values(array_filter(self::PRINTED_TOOLS, $settings->uses(...)));
    }

    /**
     * The themes a printed card lists, by labels in the viewer's locale: one group per category of the catalogue, then
     * the table's own themes in a group without a category label.
     *
     * @return list<array{category: ?string, themes: list<string>}>
     */
    public function printableThemes(ResourceReference $table, string $locale): array
    {
        $themes = $this->safety->themes($table);
        $groups = [];

        foreach ($themes->catalogue->categories() as $category) {
            $labels = array_values(array_map(static fn (Theme $theme): string => $themes->label($theme->key, $locale), $themes->catalogue->themesIn($category->key)));

            if ([] !== $labels) {
                $groups[] = ['category' => $category->label($locale), 'themes' => $labels];
            }
        }

        if ([] !== $themes->custom) {
            $groups[] = ['category' => null, 'themes' => array_map(static fn (int|string $key): string => $themes->label((string) $key, $locale), array_keys($themes->custom))];
        }

        return $groups;
    }

    /**
     * The table's pact: each question with its prompt or translation key, and its tallies or texts.
     *
     * @return array{respondents: int, questions: list<array{key: string, prompt: ?string, kind: string, options: list<array{key: string, label: ?string, count: int}>, texts: list<string>}>}
     */
    public function pact(ResourceReference $table): array
    {
        $pact = $this->safety->pact($table);
        $questions = [];

        foreach ($pact->template->questions() as $question) {
            $tally = $pact->tally($question->key);
            $options = [];

            foreach ($question->options as $option => $label) {
                $options[] = ['key' => (string) $option, 'label' => $label, 'count' => $tally[(string) $option] ?? 0];
            }

            $questions[] = [
                'key' => $question->key,
                'prompt' => $question->prompt,
                'kind' => $question->kind->value,
                'options' => QuestionKind::Text === $question->kind ? [] : $options,
                'texts' => $pact->texts($question->key),
            ];
        }

        return ['respondents' => $pact->respondents, 'questions' => $questions];
    }

    /**
     * What the Stimulus controller of a screen reads: the hub to subscribe to, the feed to catch up from, and the
     * moment of the last signal shown.
     *
     * @return array{hub: ?string, feed: string, last: string}
     */
    public function live(ResourceReference $table, bool $gameMaster, ?string $hub): array
    {
        $signals = $this->safety->signals($table, $gameMaster);
        $last = [] === $signals ? '' : $signals[\count($signals) - 1]->raisedAt->format(\DATE_ATOM);

        return ['hub' => $hub, 'feed' => $this->urls->generate(TableController::FEED, ['table' => $table->toString()]), 'last' => $last];
    }
}
