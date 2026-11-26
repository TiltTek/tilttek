<?php

declare(strict_types=1);

namespace Tilttek\FanAwards\Tests\Unit;

use Phil\Poll\Counting\Borda;
use Phil\Poll\CountingMethods;
use Phil\Poll\Standing;
use Phil\Resource\ResourceReference;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Tilttek\FanAwards\Category;
use Tilttek\FanAwards\Edition;
use Tilttek\FanAwards\Exception\AwardRefused;
use Tilttek\FanAwards\Exception\InvalidAward;
use Tilttek\FanAwards\Exception\UnknownAward;
use Tilttek\FanAwards\FanAwards;
use Tilttek\FanAwards\Nominee;
use Tilttek\FanAwards\Phase;
use Tilttek\FanAwards\PhaseCount;
use Tilttek\FanAwards\Refusal;
use Tilttek\FanAwards\Results;
use Tilttek\FanAwards\Store\InMemoryAwardStore;
use Tilttek\FanAwards\Tests\Unit\Fixture\Rules;

#[CoversClass(FanAwards::class)]
#[CoversClass(InMemoryAwardStore::class)]
final class FanAwardsTest extends TestCase
{
    private MockClock $clock;

    private InMemoryAwardStore $store;

    private FanAwards $awards;

    protected function setUp(): void
    {
        $this->clock = new MockClock('2027-01-10 10:00:00', 'UTC');
        $this->store = new InMemoryAwardStore();
        $rules = new Rules(['member:1', 'member:2', 'member:3', 'member:4', 'member:5']);
        $this->awards = new FanAwards($this->store, $this->clock, null, $rules, $rules);
    }

    public function testAnEditionGoesFromNominationsToTheAnnouncement(): void
    {
        $edition = $this->awards->create('2027', 'Fan Awards 2027', [new Category('novel', 'Best novel', 2, 3, 2)]);

        self::assertSame([Phase::Draft, '2027-01-10 10:00:00', true], [$edition->phase, $edition->since->format('Y-m-d H:i:s'), $edition->semifinal]);
        self::assertSame($edition, $this->awards->edition('2027'));

        foreach (['a' => 'Arrival', 'b' => 'Beacon', 'c' => 'Cinder'] as $key => $title) {
            $this->awards->propose('2027', 'novel', new Nominee($key, $title));
        }

        $this->awards->propose('2027', 'novel', new Nominee('a', 'Another title'));
        self::assertSame(['a', 'b', 'c'], $this->keys($this->awards->candidates('2027', 'novel')));
        self::assertSame('Arrival', $this->awards->candidates('2027', 'novel')[0]->title);

        $this->clock->modify('+1 day');
        $nomination = $this->awards->advance('2027');
        self::assertSame([Phase::Nomination, '2027-01-11'], [$nomination->phase, $nomination->since->format('Y-m-d')]);

        $this->awards->propose('2027', 'novel', new Nominee('e', 'Ember'));
        $this->vote('1', ['a']);
        $this->vote('1', ['a', 'b']);
        $this->vote('2', ['a', new Nominee('d', 'Driftwood')]);
        $this->vote('3', ['b', new Nominee('d', 'Driftwood again')]);
        $this->vote('4', ['a']);
        $this->vote('5', ['c']);

        self::assertSame(['a', 'b'], $this->awards->ballot('2027', 'novel', $this->member('1')));
        self::assertNull($this->awards->ballot('2027', 'novel', $this->member('9')));
        self::assertSame(['a', 'b', 'c', 'e', 'd'], $this->keys($this->awards->candidates('2027', 'novel')));
        self::assertSame('Driftwood', $this->awards->candidates('2027', 'novel')[4]->title);

        $this->awards->advance('2027');

        self::assertSame(['a', 'b', 'd'], $this->keys($this->awards->candidates('2027', 'novel')));

        $this->vote('1', ['a']);
        $this->vote('2', [new Nominee('f', 'Fathom')]);
        $this->vote('3', ['b']);
        $this->vote('4', ['a']);
        $this->vote('5', ['f']);
        $this->awards->advance('2027');

        self::assertSame(['a', 'f'], $this->keys($this->awards->candidates('2027', 'novel')));
        self::assertSame(['a', 'f', 'no-award'], $this->awards->finalOptions('2027', 'novel'));

        $this->vote('1', ['a', 'f']);
        $this->vote('2', ['f', 'a']);
        $this->vote('3', ['a']);
        $this->vote('4', ['a', 'no-award']);
        $this->vote('5', ['f', 'no-award']);
        $this->assertRefused(Refusal::NotCounted, fn (): Results => $this->awards->preview('2027', 'novel'));
        $this->awards->advance('2027');

        $this->assertRefused(Refusal::NotAnnounced, fn (): Results => $this->awards->results('2027', 'novel'));
        $preview = $this->awards->preview('2027', 'novel');
        self::assertSame(['a'], $this->keys($preview->winners));
        self::assertFalse($preview->noAward);
        self::assertSame('novel', $preview->category->key);
        self::assertSame(['nomination', 'semifinal', 'final'], array_map(static fn (PhaseCount $count): string => $count->phase->value, $preview->counts));

        $nominations = $preview->count(Phase::Nomination);
        self::assertNotNull($nominations);
        self::assertSame(['a 3 1', 'b 2 2', 'd 2 2', 'c 1 4', 'e 0 5'], $this->standings($nominations));
        self::assertSame(['a', 'b', 'c', 'e', 'd'], $this->keys($nominations->candidates));
        self::assertSame(['a', 'b', 'd'], $this->keys($nominations->advanced));
        self::assertSame(['a 2 1', 'f 2 1', 'b 1 3', 'd 0 4'], $this->standings($preview->count(Phase::Semifinal)));
        self::assertSame('choices', $nominations->result?->method);
        self::assertSame('instant_runoff', $preview->count(Phase::Final)?->result?->method);

        $announced = $this->awards->advance('2027');
        self::assertSame(Phase::Announced, $announced->phase);
        self::assertSame(['a'], $this->keys($this->awards->results('2027', 'novel')->winners));
        $this->assertRefused(Refusal::WrongPhase, fn (): Edition => $this->awards->advance('2027'));
    }

    public function testAnEditionWithoutSemifinalGoesFromNominationsToTheFinal(): void
    {
        $this->awards->create('2027', 'Fan Awards 2027', [new Category('novel', 'Best novel', 1, 3, 2, 'borda', false)], false);
        $this->awards->advance('2027');

        foreach (['1' => 'a', '2' => 'a', '3' => 'b', '4' => 'c', '5' => 'b'] as $voter => $choice) {
            $this->vote((string) $voter, [new Nominee($choice, strtoupper($choice))]);
        }

        $final = $this->awards->advance('2027');

        self::assertSame(Phase::Final, $final->phase);
        self::assertSame(['a', 'b'], $this->keys($this->awards->candidates('2027', 'novel')));
        self::assertSame(['a', 'b'], $this->awards->finalOptions('2027', 'novel'));
    }

    public function testNoAwardWinsWhenItHoldsTheFirstPlaceAlone(): void
    {
        $this->finalOf(new Category('novel', 'Best novel', 2, 2, 2), ['a', 'b']);
        $this->vote('1', ['no-award']);
        $this->vote('2', ['no-award', 'a']);
        $this->vote('3', ['a']);
        $this->awards->advance('2027');

        $results = $this->awards->preview('2027', 'novel');

        self::assertSame([], $results->winners);
        self::assertTrue($results->noAward);
    }

    public function testNomineesTiedWithNoAwardShareTheAward(): void
    {
        $this->finalOf(new Category('novel', 'Best novel', 2, 2, 2, 'borda'), ['a', 'b']);
        $this->vote('1', ['a', 'no-award', 'b']);
        $this->vote('2', ['no-award', 'a', 'b']);
        $this->awards->advance('2027');

        $results = $this->awards->preview('2027', 'novel');

        self::assertSame(['a'], $this->keys($results->winners));
        self::assertFalse($results->noAward);
    }

    public function testTiedNomineesShareTheAward(): void
    {
        $this->finalOf(new Category('novel', 'Best novel', 2, 2, 2, 'schulze', false), ['a', 'b']);
        $this->vote('1', ['a', 'b']);
        $this->vote('2', ['b', 'a']);
        $this->awards->advance('2027');

        self::assertSame(['a', 'b'], $this->keys($this->awards->preview('2027', 'novel')->winners));
    }

    public function testAFinalWithoutBallotsAwardsNobody(): void
    {
        $this->finalOf(new Category('novel', 'Best novel', 2, 2, 2), ['a', 'b']);
        $this->awards->advance('2027');

        $results = $this->awards->preview('2027', 'novel');

        self::assertSame([], $results->winners);
        self::assertFalse($results->noAward);
        self::assertSame(0, $results->count(Phase::Final)?->result?->ballots);
    }

    public function testASingleNomineeGoesThroughWithoutAVote(): void
    {
        $this->awards->create('2027', 'Fan Awards 2027', [new Category('novel', 'Best novel', 1, 2, 2, 'instant_runoff', false), new Category('game', 'Best game'), new Category('comic', 'Best comic')], false);
        $this->awards->propose('2027', 'novel', new Nominee('a', 'Arrival'));
        $this->awards->propose('2027', 'game', new Nominee('g', 'Gearworks'));
        $this->awards->advance('2027');
        $this->awards->advance('2027');

        self::assertSame(['a'], $this->awards->finalOptions('2027', 'novel'));
        self::assertSame(['g', 'no-award'], $this->awards->finalOptions('2027', 'game'));
        self::assertSame([], $this->awards->finalOptions('2027', 'comic'));
        $this->assertInvalid('This category offers fewer than two nominees: there is nothing to vote on.', fn () => $this->vote('1', ['a']));
        $this->vote('1', ['no-award'], 'game');
        $this->awards->advance('2027');

        $novel = $this->awards->preview('2027', 'novel');
        self::assertSame(['a'], $this->keys($novel->winners));
        self::assertNull($novel->count(Phase::Nomination)?->result);
        self::assertNull($novel->count(Phase::Final)?->result);
        self::assertSame(['a'], $this->keys($novel->count(Phase::Nomination)->advanced ?? []));
        self::assertTrue($this->awards->preview('2027', 'game')->noAward);
        self::assertSame([], $this->awards->preview('2027', 'comic')->winners);
        self::assertFalse($this->awards->preview('2027', 'comic')->noAward);
    }

    public function testBallotsThatBreakThePhaseRulesAreRefused(): void
    {
        $this->awards->create('2027', 'Fan Awards 2027', [new Category('novel', 'Best novel', 2, 2, 2)]);
        $this->awards->propose('2027', 'novel', new Nominee('a', 'Arrival'));
        $this->awards->propose('2027', 'novel', new Nominee('b', 'Beacon'));

        $this->assertRefused(Refusal::WrongPhase, fn () => $this->vote('1', ['a']));
        $this->awards->advance('2027');

        $this->assertInvalid('A ballot of this poll holds 1 to 2 choices, not 3.', fn () => $this->vote('1', ['a', 'b', new Nominee('c', 'Cinder')]));
        self::assertSame(['a', 'b'], $this->keys($this->awards->candidates('2027', 'novel')));
        $this->assertInvalid('"z" is not an option of this poll.', fn () => $this->vote('1', ['z']));
        $this->assertInvalid('The option "a" is chosen twice.', fn () => $this->vote('1', ['a', new Nominee('a', 'Arrival')]));
        $this->assertInvalid('This poll accepts no blank ballot.', fn () => $this->vote('1', []));
        $this->assertRefused(Refusal::VoterNotEligible, fn () => $this->vote('9', ['a']));
        $this->assertRefused(Refusal::Ineligible, fn () => $this->vote('1', [new Nominee('old-tale', 'Old Tale')]), 'This nominee is not eligible in this category. "Old Tale" was published before the award year of Fan Awards 2027.');
        $this->assertRefused(Refusal::Ineligible, fn () => $this->awards->propose('2027', 'novel', new Nominee('old-map', 'Old Map')));
        self::assertSame(['a', 'b'], $this->keys($this->awards->candidates('2027', 'novel')));

        $this->vote('1', ['a']);
        $this->vote('2', ['b']);
        $this->awards->advance('2027');

        $this->assertRefused(Refusal::WrongPhase, fn () => $this->awards->propose('2027', 'novel', new Nominee('c', 'Cinder')));
        $this->assertInvalid('A ballot of this poll holds 1 choice(s), not 2.', fn () => $this->vote('1', ['a', 'b']));
        $this->vote('1', ['a']);
        $this->vote('2', ['b']);
        $this->awards->advance('2027');

        $this->assertInvalid('A final ballot ranks the finalists by their keys; no nominee is written in.', fn () => $this->vote('1', [new Nominee('c', 'Cinder')]));
        $this->vote('1', ['b', new Nominee('a', 'Arrival')]);
        self::assertSame(['b', 'a'], $this->awards->ballot('2027', 'novel', $this->member('1')));
        $this->awards->advance('2027');

        $this->assertRefused(Refusal::WrongPhase, fn () => $this->vote('1', ['a']));
    }

    public function testTheFirstNomineesAreWrittenInOneByOne(): void
    {
        $this->awards->create('2027', 'Fan Awards 2027', [new Category('novel', 'Best novel')]);
        $this->awards->advance('2027');

        $message = 'While a category offers one nominee, a ballot holds that nominee alone, or one written in.';

        $this->assertInvalid($message, fn () => $this->vote('1', []));
        $this->vote('1', [new Nominee('a', 'Arrival')]);
        $this->assertInvalid($message, fn () => $this->vote('2', []));
        $this->assertInvalid($message, fn () => $this->vote('2', ['a', 'a']));
        $this->assertInvalid($message, fn () => $this->vote('2', ['z']));
        $this->vote('2', ['a']);
        $this->vote('3', ['a', new Nominee('b', 'Beacon')]);

        self::assertSame(['a'], $this->awards->ballot('2027', 'novel', $this->member('2')));
        self::assertSame(['a', 'b'], $this->awards->ballot('2027', 'novel', $this->member('3')));
        self::assertSame(['a', 'b'], $this->keys($this->awards->candidates('2027', 'novel')));
    }

    public function testACategoryOffersAtMostAHundredNominees(): void
    {
        $this->awards->create('2027', 'Fan Awards 2027', [new Category('novel', 'Best novel')]);

        for ($i = 1; $i <= 99; ++$i) {
            $this->awards->propose('2027', 'novel', new Nominee('n'.$i, 'Novel '.$i));
        }

        $this->awards->advance('2027');
        $this->vote('1', [new Nominee('n100', 'Novel 100')]);

        $this->assertRefused(Refusal::TooManyCandidates, fn () => $this->awards->propose('2027', 'novel', new Nominee('n101', 'Novel 101')));
        $this->assertRefused(Refusal::TooManyCandidates, fn () => $this->vote('2', [new Nominee('n101', 'Novel 101')]));
        self::assertCount(100, $this->awards->candidates('2027', 'novel'));
    }

    public function testAWriteInOfACandidateDoesNotStopTheBallotFromBeingRead(): void
    {
        $this->awards->create('2027', 'Fan Awards 2027', [new Category('novel', 'Best novel')]);
        $this->awards->propose('2027', 'novel', new Nominee('a', 'Arrival'));
        $this->awards->advance('2027');

        $this->vote('1', [new Nominee('a', 'Arrival again'), new Nominee('b', 'Beacon')]);

        self::assertSame(['a', 'b'], $this->awards->ballot('2027', 'novel', $this->member('1')));
        self::assertSame(['a', 'b'], $this->keys($this->awards->candidates('2027', 'novel')));
    }

    public function testTheWriteInsOfOneBallotAreCountedWithTheCandidatesBeforeThem(): void
    {
        $this->awards->create('2027', 'Fan Awards 2027', [new Category('novel', 'Best novel')]);

        for ($i = 1; $i <= 99; ++$i) {
            $this->awards->propose('2027', 'novel', new Nominee('n'.$i, 'Novel '.$i));
        }

        $this->awards->advance('2027');

        $this->assertRefused(Refusal::TooManyCandidates, fn () => $this->vote('1', [new Nominee('n100', 'Novel 100'), new Nominee('n101', 'Novel 101')]));
        self::assertCount(99, $this->awards->candidates('2027', 'novel'));
    }

    public function testTheShortlistKeepsTheNomineesUpToItsSizeWhoReceivedAVote(): void
    {
        foreach ([3, 2] as $size) {
            // 3: the nominee without a vote is within the size; 2: the second place is on its edge.
            $this->store = new InMemoryAwardStore();
            $rules = new Rules(['member:1', 'member:2', 'member:3', 'member:4', 'member:5']);
            $this->awards = new FanAwards($this->store, $this->clock, null, $rules, $rules);
            $this->awards->create('2027', 'Fan Awards 2027', [new Category('novel', 'Best novel', 2, 3, $size, 'borda', false)], false);

            foreach (['a', 'b', 'c'] as $key) {
                $this->awards->propose('2027', 'novel', new Nominee($key, strtoupper($key)));
            }

            $this->awards->advance('2027');
            $this->vote('1', ['a', 'b']);
            $this->vote('2', ['a']);
            $this->awards->advance('2027');

            self::assertSame(['a', 'b'], $this->keys($this->awards->candidates('2027', 'novel')), 'size '.$size);
        }
    }

    public function testBallotsOfDifferentSlotsNeverMeet(): void
    {
        $store = new InMemoryAwardStore();
        $slots = [['a', 'bc', Phase::Final], ['ab', 'c', Phase::Final], ['a', 'bc', Phase::Semifinal], ['x', 'bc', Phase::Final], ['a', 'y', Phase::Final], ['a', 'bc', Phase::Nomination], ['a', 'semi', Phase::Final], ['a', '', Phase::Semifinal], ['', 'a', Phase::Final], ['a', 'a', Phase::Draft], ['', 'a:draft', Phase::Final]];

        foreach ($slots as $index => [$edition, $category, $phase]) {
            $store->saveBallot($edition, $category, $phase, 'member:'.$index, ['k'.$index]);
        }

        $store->forget('member:5');

        foreach ($slots as $index => [$edition, $category, $phase]) {
            self::assertSame(5 === $index ? [['k5']] : [['k'.$index]], iterator_to_array($store->ballots($edition, $category, $phase), false));
        }

        $store->saveBallot('a', 'bc', Phase::Final, 'member:9', ['z']);
        $store->forget('member:9');

        self::assertSame([['k0'], ['z']], iterator_to_array($store->ballots('a', 'bc', Phase::Final), false));
    }

    public function testAnEditionIsCreatedOnceWithRankedMethodsOnly(): void
    {
        $this->awards->create('2027', 'Fan Awards 2027', [new Category('novel', 'Best novel')]);

        $this->assertRefused(Refusal::EditionExists, fn (): Edition => $this->awards->create('2027', 'Again', [new Category('novel', 'Best novel')]));
        $this->assertInvalid('The final of "game" is counted by a ranked method of the application, not "choices".', fn (): Edition => $this->awards->create('2028', 'Fan Awards 2028', [new Category('novel', 'Best novel'), new Category('game', 'Best game', method: 'choices')]));
        $this->assertInvalid('not "plurality".', fn (): Edition => $this->awards->create('2028', 'Fan Awards 2028', [new Category('novel', 'Best novel', method: 'plurality')]));

        $bordaOnly = new FanAwards($this->store, $this->clock, new CountingMethods([new Borda()]));
        $this->assertInvalid('not "instant_runoff".', static fn (): Edition => $bordaOnly->create('2029', 'Fan Awards 2029', [new Category('novel', 'Best novel')]));
        self::assertSame('borda', $bordaOnly->create('2029', 'Fan Awards 2029', [new Category('novel', 'Best novel', method: 'borda')])->category('novel')->method);
        $this->expectException(UnknownAward::class);
        $this->awards->edition('2028');
    }

    public function testAnUnknownEditionOrCategoryIsReported(): void
    {
        $this->awards->create('2027', 'Fan Awards 2027', [new Category('novel', 'Best novel')]);

        foreach ([
            fn (): array => $this->awards->candidates('2027', 'comic'),
            fn (): ?array => $this->awards->ballot('2027', 'comic', $this->member('1')),
            fn (): array => $this->awards->finalOptions('2027', 'comic'),
            fn () => $this->awards->propose('2027', 'comic', new Nominee('a', 'A')),
            fn () => $this->vote('1', ['a'], 'comic'),
        ] as $act) {
            try {
                $act();
                self::fail('The category was found.');
            } catch (UnknownAward $unknown) {
                self::assertSame('The edition "2027" holds no category "comic".', $unknown->getMessage());
            }
        }

        $this->expectException(UnknownAward::class);
        $this->expectExceptionMessageIsOrContains('No edition is known as "2026".');
        $this->awards->results('2026', 'novel');
    }

    public function testTheResultsOfAnEditionNotCountedAreSealed(): void
    {
        $this->awards->create('2027', 'Fan Awards 2027', [new Category('novel', 'Best novel')]);

        $this->assertRefused(Refusal::NotCounted, fn (): Results => $this->awards->preview('2027', 'novel'));
        $this->assertRefused(Refusal::NotAnnounced, fn (): Results => $this->awards->results('2027', 'novel'));

        $this->expectException(UnknownAward::class);
        $this->awards->preview('2027', 'comic');
    }

    public function testACountedEditionWithoutCountsIsNotCounted(): void
    {
        $edition = $this->awards->create('2027', 'Fan Awards 2027', [new Category('novel', 'Best novel')]);
        $this->store->saveEdition($edition->in(Phase::Counted, $this->clock->now()));

        $this->assertRefused(Refusal::NotCounted, fn (): Results => $this->awards->preview('2027', 'novel'));
    }

    public function testAnErasedVoterIsForgottenAndTheirBallotStillCounts(): void
    {
        $this->finalOf(new Category('novel', 'Best novel', 2, 2, 2, 'instant_runoff', false), ['a', 'b']);
        $this->vote('1', ['a']);
        $this->vote('2', ['a']);
        $this->vote('3', ['b']);

        $this->awards->erase($this->member('1'));
        $this->awards->erase($this->member('4'));

        self::assertNull($this->awards->ballot('2027', 'novel', $this->member('1')));
        self::assertSame(['a'], $this->awards->ballot('2027', 'novel', $this->member('2')));
        $this->awards->advance('2027');

        $results = $this->awards->preview('2027', 'novel');
        self::assertSame(['a'], $this->keys($results->winners));
        self::assertSame(3, $results->count(Phase::Final)?->result?->ballots);
    }

    /**
     * Brings an edition without semifinal to its final on the nominees given, each nominated once.
     *
     * @param list<string> $nominees
     */
    private function finalOf(Category $category, array $nominees): void
    {
        $this->awards->create('2027', 'Fan Awards 2027', [$category], false);
        $this->awards->advance('2027');

        foreach ($nominees as $index => $key) {
            $this->vote((string) ($index + 1), [new Nominee($key, strtoupper($key))]);
        }

        $this->awards->advance('2027');
    }

    /**
     * @param list<string|Nominee> $choices
     */
    private function vote(string $voter, array $choices, string $category = 'novel'): void
    {
        $this->awards->vote('2027', $category, $this->member($voter), $choices);
    }

    private function assertRefused(Refusal $refusal, callable $act, ?string $message = null): void
    {
        try {
            $act();
            self::fail(\sprintf('The act was not refused for "%s".', $refusal->value));
        } catch (AwardRefused $awardRefused) {
            self::assertSame($refusal, $awardRefused->refusal);

            if (null !== $message) {
                self::assertSame($message, $awardRefused->getMessage());
            }
        }
    }

    private function assertInvalid(string $message, callable $act): void
    {
        try {
            $act();
            self::fail('The value was accepted.');
        } catch (InvalidAward $invalidAward) {
            self::assertStringContainsString($message, $invalidAward->getMessage());
        }
    }

    /**
     * @param list<Nominee> $nominees
     *
     * @return list<string>
     */
    private function keys(array $nominees): array
    {
        return array_map(static fn (Nominee $nominee): string => $nominee->key, $nominees);
    }

    /**
     * @return list<string>
     */
    private function standings(?PhaseCount $count): array
    {
        return array_map(static fn (Standing $standing): string => \sprintf('%s %d %d', $standing->option, $standing->score, $standing->place), $count->result->standings ?? []);
    }

    private function member(string $id): ResourceReference
    {
        return ResourceReference::of('member', $id);
    }
}
