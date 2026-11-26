<?php

declare(strict_types=1);

namespace Tilttek\FanAwards;

use Phil\Poll\Ballot;
use Phil\Poll\BallotKind;
use Phil\Poll\Counting\ChoiceCount;
use Phil\Poll\CountingMethods;
use Phil\Poll\Exception\InvalidBallot;
use Phil\Poll\PollRules;
use Phil\Poll\Result;
use Phil\Resource\ResourceReference;
use Psr\Clock\ClockInterface;
use Tilttek\FanAwards\Contract\AwardStore;
use Tilttek\FanAwards\Contract\NomineeEligibility;
use Tilttek\FanAwards\Contract\VoterEligibility;
use Tilttek\FanAwards\Eligibility\AnyNominee;
use Tilttek\FanAwards\Eligibility\AnyVoter;
use Tilttek\FanAwards\Exception\AwardRefused;
use Tilttek\FanAwards\Exception\InvalidAward;
use Tilttek\FanAwards\Exception\UnknownAward;

/**
 * The fans' awards: an edition's categories go through open nominations, a semifinal where voters may still add a
 * nominee, and a ranked final with "no award" among its options; the winners are announced, then the detailed counts
 * published. Every count is made by `phil/poll`: nominations and the semifinal by the count of choices, the final by
 * the category's ranked method.
 *
 * ```php
 * $awards->create('2027', 'Fan Awards 2027', [new Category('novel', 'Best novel')]);
 * $awards->advance('2027');                                  // nominations open
 * $awards->vote('2027', 'novel', $voter, ['the-long-road', new Nominee('night-ferry', 'Night Ferry')]);
 * $awards->advance('2027');                                  // the semifinal, on the nominations' shortlist
 * ```
 *
 * A vote is never paid for nor tied to a purchase: who may vote is the application's `VoterEligibility`.
 */
final readonly class FanAwards
{
    private CountingMethods $methods;

    public function __construct(
        private AwardStore $store,
        private ClockInterface $clock,
        ?CountingMethods $methods = null,
        private NomineeEligibility $nominees = new AnyNominee(),
        private VoterEligibility $voters = new AnyVoter(),
    ) {
        $this->methods = $methods ?? CountingMethods::defaults();
    }

    /**
     * Creates an edition in its draft, with its categories and its semifinal or not.
     *
     * @param list<Category> $categories
     */
    public function create(string $key, string $name, array $categories, bool $semifinal = true): Edition
    {
        $edition = new Edition($key, $name, $categories, $semifinal, Phase::Draft, $this->clock->now());

        foreach ($edition->categories as $category) {
            if (!\in_array($category->method, $this->methods->namesFor(BallotKind::Ranked), true)) {
                throw InvalidAward::because(\sprintf('The final of "%s" is counted by a ranked method of the application, not "%s".', $category->key, $category->method));
            }
        }

        if ($this->store->edition($key) instanceof Edition) {
            throw AwardRefused::because(Refusal::EditionExists);
        }

        $this->store->saveEdition($edition);

        return $edition;
    }

    /**
     * @throws UnknownAward
     */
    public function edition(string $key): Edition
    {
        return $this->store->edition($key) ?? throw UnknownAward::edition($key);
    }

    /**
     * An organiser puts a nominee forward in a category, before or during the nominations; it must be eligible.
     */
    public function propose(string $edition, string $category, Nominee $nominee): void
    {
        $found = $this->edition($edition);
        $rules = $found->category($category);

        if (Phase::Draft !== $found->phase && Phase::Nomination !== $found->phase) {
            throw AwardRefused::because(Refusal::WrongPhase);
        }

        $candidates = $this->store->candidates($edition, $category, Phase::Nomination);

        if ($this->find($candidates, $nominee->key) instanceof Nominee) {
            return;
        }

        $this->admit($found, $rules, $nominee, \count($candidates));
        $this->store->addCandidate($edition, $category, Phase::Nomination, $nominee);
    }

    /**
     * The nominees of the category in the edition's current voting phase — in the draft, those proposed so far.
     *
     * @return list<Nominee>
     */
    public function candidates(string $edition, string $category): array
    {
        $found = $this->edition($edition);
        $found->category($category);

        return $this->store->candidates($edition, $category, Phase::Draft === $found->phase ? Phase::Nomination : $found->phase);
    }

    /**
     * The final ballot's options of the category: its finalists, then "no award" when the category offers it.
     *
     * @return list<string>
     */
    public function finalOptions(string $edition, string $category): array
    {
        $rules = $this->edition($edition)->category($category);
        $options = array_map(static fn (Nominee $nominee): string => $nominee->key, $this->store->candidates($edition, $category, Phase::Final));

        return $rules->noAward && [] !== $options ? [...$options, Nominee::NO_AWARD] : $options;
    }

    /**
     * Casts or replaces the voter's ballot in a category of the current phase.
     *
     * - Nominations: 1 to the category's count of nominees, among the candidates or written in as a `Nominee`.
     * - Semifinal: exactly one, among the shortlist or written in.
     * - Final: a ranking of the final's options, keys only, "no award" among them when the category offers it.
     *
     * A nominee written in is checked by the application's eligibility before it joins the candidates.
     *
     * @param list<string|Nominee> $choices
     */
    public function vote(string $edition, string $category, ResourceReference $voter, array $choices): void
    {
        $found = $this->edition($edition);
        $rules = $found->category($category);
        $phase = $found->phase;

        if (!$phase->isVoting()) {
            throw AwardRefused::because(Refusal::WrongPhase);
        }

        if (!$this->voters->mayVote($voter, $found)) {
            throw AwardRefused::because(Refusal::VoterNotEligible);
        }

        $candidates = $this->store->candidates($edition, $category, $phase);
        $added = [];
        $keys = [];

        foreach ($choices as $choice) {
            if (\is_string($choice)) {
                $keys[] = $choice;

                continue;
            }

            $keys[] = $choice->key;

            if ($this->find([...$candidates, ...$added], $choice->key) instanceof Nominee) {
                continue;
            }

            if (!$phase->takesWriteIns()) {
                throw InvalidAward::because('A final ballot ranks the finalists by their keys; no nominee is written in.');
            }

            $this->admit($found, $rules, $choice, \count($candidates) + \count($added));
            $added[] = $choice;
        }

        $options = Phase::Final === $phase ? $this->finalOptions($edition, $category) : array_map(static fn (Nominee $nominee): string => $nominee->key, [...$candidates, ...$added]);

        if ($phase->takesWriteIns() && \count($options) < 2 && ([] === $keys || $keys !== $options)) {
            throw InvalidAward::because('While a category offers one nominee, a ballot holds that nominee alone, or one written in.');
        }

        $ballot = $phase->takesWriteIns() && \count($options) < 2 ? Ballot::of($keys) : $this->check($this->rules($phase, $rules, $options), $keys);

        foreach ($added as $nominee) {
            $this->store->addCandidate($edition, $category, $phase, $nominee);
        }

        $this->store->saveBallot($edition, $category, $phase, $voter->toString(), $ballot->choices);
    }

    /**
     * The voter's own ballot in a category of the current phase, for them to read again, or null.
     *
     * @return ?list<string>
     */
    public function ballot(string $edition, string $category, ResourceReference $voter): ?array
    {
        $found = $this->edition($edition);
        $found->category($category);

        return $this->store->ballot($edition, $category, $found->phase, $voter->toString());
    }

    /**
     * Moves the edition to its next phase. Closing a voting phase counts every category: the nominations and the
     * semifinal pass their shortlist on, the final gives the winners. From counted, the next phase is the announcement,
     * which publishes the winners and the detailed counts.
     */
    public function advance(string $edition): Edition
    {
        $found = $this->edition($edition);
        $next = $found->phase->next($found->semifinal) ?? throw AwardRefused::because(Refusal::WrongPhase);

        if ($found->phase->isVoting()) {
            foreach ($found->categories as $category) {
                $this->countPhase($found, $category, $found->phase, $next);
            }
        }

        $moved = $found->in($next, $this->clock->now());
        $this->store->saveEdition($moved);

        return $moved;
    }

    /**
     * The results of a category once announced: the winners and the detailed counts, for everyone.
     */
    public function results(string $edition, string $category): Results
    {
        if (Phase::Announced !== $this->edition($edition)->phase) {
            throw AwardRefused::because(Refusal::NotAnnounced);
        }

        return $this->preview($edition, $category);
    }

    /**
     * The results of a category once counted, before the announcement: for the organisers alone, the application
     * guarding who reads them.
     */
    public function preview(string $edition, string $category): Results
    {
        $found = $this->edition($edition);
        $rules = $found->category($category);

        if (Phase::Counted !== $found->phase && Phase::Announced !== $found->phase) {
            throw AwardRefused::because(Refusal::NotCounted);
        }

        $counts = $this->store->counts($edition, $category);
        $final = array_last($counts) ?? throw AwardRefused::because(Refusal::NotCounted);
        $noAward = null !== $final->result && [] === $final->advanced && [] !== $final->result->winners();

        return new Results($rules, $final->advanced, $noAward, $counts);
    }

    /**
     * Forgets the voter on every ballot they cast; the ballots stay, anonymous, in the counts.
     */
    public function erase(ResourceReference $voter): void
    {
        $this->store->forget($voter->toString());
    }

    private function countPhase(Edition $edition, Category $category, Phase $phase, Phase $next): void
    {
        $candidates = $this->store->candidates($edition->key, $category->key, $phase);
        $options = Phase::Final === $phase ? $this->finalOptions($edition->key, $category->key) : array_map(static fn (Nominee $nominee): string => $nominee->key, $candidates);
        $result = null;
        $advanced = $candidates;

        if (\count($options) >= 2) {
            $rules = $this->rules($phase, $category, $options);
            $result = $this->methods->count(Phase::Final === $phase ? $category->method : ChoiceCount::NAME, $rules, $this->read($rules, $this->store->ballots($edition->key, $category->key, $phase)));
            $advanced = Phase::Final === $phase ? $this->winners($result, $candidates) : $this->shortlist($result, $candidates, Phase::Semifinal === $next ? $category->semifinalists : $category->finalists);
        }

        $this->store->saveCount($edition->key, $category->key, new PhaseCount($phase, $candidates, $result, $advanced));

        if (Phase::Counted !== $next) {
            foreach ($advanced as $nominee) {
                $this->store->addCandidate($edition->key, $category->key, $next, $nominee);
            }
        }
    }

    /**
     * @param iterable<list<string>> $ballots
     *
     * @return iterable<Ballot>
     */
    private function read(PollRules $rules, iterable $ballots): iterable
    {
        foreach ($ballots as $choices) {
            yield $rules->ballot($choices);
        }
    }

    /**
     * @param list<string> $options
     */
    private function rules(Phase $phase, Category $category, array $options): PollRules
    {
        if (\count($options) < 2) {
            throw InvalidAward::because('This category offers fewer than two nominees: there is nothing to vote on.');
        }

        return match ($phase) {
            Phase::Nomination => PollRules::multiple($options, 1, min($category->nominations, \count($options))),
            Phase::Semifinal => PollRules::single($options),
            default => PollRules::ranked($options),
        };
    }

    /**
     * @param list<string> $keys
     */
    private function check(PollRules $rules, array $keys): Ballot
    {
        try {
            return $rules->ballot($keys);
        } catch (InvalidBallot $invalidBallot) {
            throw InvalidAward::because($invalidBallot->getMessage());
        }
    }

    private function admit(Edition $edition, Category $category, Nominee $nominee, int $candidates): void
    {
        if ($candidates >= PollRules::MAX_OPTIONS) {
            throw AwardRefused::because(Refusal::TooManyCandidates);
        }

        $reason = $this->nominees->refusal($edition, $category, $nominee);

        if (null !== $reason) {
            throw AwardRefused::because(Refusal::Ineligible, $reason);
        }
    }

    /**
     * The nominees placed within the size, ties at the edge included, who received at least one vote.
     *
     * @param list<Nominee> $candidates
     *
     * @return list<Nominee>
     */
    private function shortlist(Result $result, array $candidates, int $size): array
    {
        $shortlist = [];

        foreach ($result->standings as $standing) {
            $nominee = $this->find($candidates, $standing->option);

            if ($nominee instanceof Nominee && $standing->place <= $size && $standing->score > 0) {
                $shortlist[] = $nominee;
            }
        }

        return $shortlist;
    }

    /**
     * The nominees in first place; none when "no award" holds it alone or nobody voted.
     *
     * @param list<Nominee> $candidates
     *
     * @return list<Nominee>
     */
    private function winners(Result $result, array $candidates): array
    {
        $winners = [];

        foreach ($result->winners() as $option) {
            $winner = $this->find($candidates, $option);

            if ($winner instanceof Nominee) {
                $winners[] = $winner;
            }
        }

        return $winners;
    }

    /**
     * @param list<Nominee> $nominees
     */
    private function find(array $nominees, string $key): ?Nominee
    {
        foreach ($nominees as $nominee) {
            if ($nominee->key === $key) {
                return $nominee;
            }
        }

        return null;
    }
}
