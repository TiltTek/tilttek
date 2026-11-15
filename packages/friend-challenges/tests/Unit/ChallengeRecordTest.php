<?php

declare(strict_types=1);

namespace Tilttek\FriendChallenges\Tests\Unit;

use Closure;
use DateTimeImmutable;
use Phil\Resource\ResourceReference;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tilttek\FriendChallenges\Challenge;
use Tilttek\FriendChallenges\ChallengeDraft;
use Tilttek\FriendChallenges\ChallengeRecord;
use Tilttek\FriendChallenges\ChallengeRules;
use Tilttek\FriendChallenges\Check;
use Tilttek\FriendChallenges\Exception\InvalidChallenge;
use Tilttek\FriendChallenges\Instant;
use Tilttek\FriendChallenges\Prize;
use Tilttek\FriendChallenges\Proof;
use Tilttek\FriendChallenges\Reader;
use Tilttek\FriendChallenges\Tests\Unit\Fixture\World;
use Tilttek\FriendChallenges\Validation;
use Tilttek\FriendChallenges\Visibility;

#[CoversClass(ChallengeRecord::class)]
#[CoversClass(Reader::class)]
#[CoversClass(Challenge::class)]
#[CoversClass(Instant::class)]
#[CoversClass(Check::class)]
#[CoversClass(Proof::class)]
final class ChallengeRecordTest extends TestCase
{
    /**
     * @return iterable<string, array{Closure(): Challenge}>
     */
    public static function challenges(): iterable
    {
        yield 'open' => [self::challenge(...)];
        yield 'declined' => [static fn (): Challenge => self::challenge()->decline(World::dee(), self::at())];
        yield 'with proofs' => [self::played(...)];
        yield 'won' => [static fn (): Challenge => self::played()->approve('p1', World::ana(), false, self::at())];
        yield 'won by the second proof' => [static fn (): Challenge => self::played()->approve('p2', World::ana(), false, self::at())];
        yield 'with fifty invitees' => [static fn (): Challenge => Challenge::create(new ChallengeDraft('c1', 'Paint', new Prize(100), self::at()->modify('+7 days'), array_map(static fn (int $n): ResourceReference => ResourceReference::of('member', 'm'.$n), range(1, 50))), World::ana(), self::at(), new ChallengeRules())];
        yield 'rejected' => [static fn (): Challenge => self::played()->reject('p1', World::ana(), false, self::at())];
        yield 'frozen' => [static fn (): Challenge => self::played()->freeze()];
        yield 'removed' => [static fn (): Challenge => self::played()->remove(self::at())];
        yield 'cancelled' => [static fn (): Challenge => self::challenge()->cancel(World::ana(), self::at())];
        yield 'expired' => [static fn (): Challenge => self::challenge()->expire(self::at()->modify('+10 days'))];
        yield 'voted' => [static fn (): Challenge => self::voted()->approve('p1', World::ana(), false, self::at())->reject('p2', World::ana(), false, self::at())];
        yield 'won by vote' => [static fn (): Challenge => self::voted()->approve('p1', World::ana(), false, self::at())->approve('p1', World::cy(), false, self::at())];
        yield 'rejected by vote' => [static fn (): Challenge => self::voted()->reject('p1', World::ana(), false, self::at())->reject('p1', World::cy(), false, self::at())];
        yield 'open to all' => [static fn (): Challenge => Challenge::create(new ChallengeDraft('c1', 'Paint', new Prize(5, 'gems'), self::at()->modify('+1 day'), visibility: Visibility::Open), World::ana(), self::at(), new ChallengeRules())->join(World::eve(), self::at())];
    }

    /**
     * @param Closure(): Challenge $challenge
     */
    #[DataProvider('challenges')]
    public function testARecordReadsBackAsItWasWritten(Closure $challenge): void
    {
        $record = $challenge()->toArray();
        $json = json_decode(json_encode($record, \JSON_THROW_ON_ERROR), true, 512, \JSON_THROW_ON_ERROR);
        self::assertIsArray($json);

        self::assertSame($record, Challenge::fromArray($json)->toArray());
    }

    /**
     * @return iterable<string, array{Closure(array<array-key, mixed>): array<array-key, mixed>, string}>
     */
    public static function brokenRecords(): iterable
    {
        yield 'a field missing' => [static fn (array $r): array => array_diff_key($r, ['version' => true]), 'holds the fields'];
        yield 'a field more' => [static fn (array $r): array => [...$r, 'extra' => 1], 'holds the fields'];
        yield 'a text not a text' => [static fn (array $r): array => [...$r, 'title' => 4], 'is a text'];
        yield 'a number not a number' => [static fn (array $r): array => [...$r, 'version' => '2'], 'is a whole number'];
        yield 'a list not a list' => [static fn (array $r): array => [...$r, 'invitees' => ['a' => 'member:bea']], 'is a list'];
        yield 'a list of numbers' => [static fn (array $r): array => [...$r, 'invitees' => [4]], 'lists texts'];
        yield 'a member twice' => [static fn (array $r): array => [...$r, 'invitees' => ['member:bea', 'member:bea']], 'names member:bea twice'];
        yield 'too many invitees' => [static fn (array $r): array => [...$r, 'invitees' => array_map(static fn (int $n): string => 'member:m'.$n, range(1, 51))], 'lists 50 items at most'];
        yield 'not a member' => [static fn (array $r): array => [...$r, 'creator' => 'ana'], 'not a resource reference'];
        yield 'an unknown state' => [static fn (array $r): array => [...$r, 'state' => 'lost'], 'not one of its values'];
        yield 'a time in another form' => [static fn (array $r): array => [...$r, 'ends_at' => '2026-11-21 01:00:00'], 'not a time written in UTC'];
        yield 'a time out of range' => [static fn (array $r): array => [...$r, 'ends_at' => '2026-02-30T01:00:00.000000Z'], 'not a time written in UTC'];
        yield 'a title untrimmed' => [static fn (array $r): array => [...$r, 'title' => ' Paint'], 'kept trimmed'];
        yield 'terms untrimmed' => [static fn (array $r): array => [...$r, 'description' => 'Go '], 'kept trimmed'];
        yield 'an end before the creation' => [static fn (array $r): array => [...$r, 'ends_at' => $r['created_at']], 'ends after it is created'];
        yield 'a review ending before the end' => [static fn (array $r): array => [...$r, 'review_ends_at' => '2026-11-20T01:00:00.000000Z'], 'ends after it is created'];
        yield 'version zero' => [static fn (array $r): array => [...$r, 'version' => 0], 'version 1 or later'];
        yield 'the creator invited' => [static fn (array $r): array => [...$r, 'invitees' => ['member:ana', 'member:bea']], 'does not take part'];
        yield 'the creator taking part' => [static fn (array $r): array => [...$r, 'participants' => ['member:ana']], 'does not take part'];
        yield 'a stranger declining' => [static fn (array $r): array => [...$r, 'declined' => ['member:eve']], 'was invited'];
        yield 'a participant declining' => [static fn (array $r): array => [...$r, 'declined' => ['member:bea']], 'was invited'];
        yield 'a stranger taking part' => [static fn (array $r): array => [...$r, 'participants' => ['member:bea', 'member:eve']], 'were invited'];
        yield 'a proof not a record' => [static fn (array $r): array => [...$r, 'proofs' => ['p1']], 'are records'];
        yield 'a proof field missing' => [static fn (array $r): array => self::proof($r, 0, static fn (array $p): array => array_diff_key($p, ['state' => true])), 'holds the fields'];
        yield 'a proof twice' => [static fn (array $r): array => self::proof($r, 1, static fn (array $p): array => [...$p, 'id' => 'p1']), 'listed twice'];
        yield 'a proof by a stranger' => [static fn (array $r): array => self::proof($r, 0, static fn (array $p): array => [...$p, 'participant' => 'member:dee']), 'one of at most'];
        yield 'a proof before the creation' => [static fn (array $r): array => self::proof($r, 0, static fn (array $p): array => [...$p, 'submitted_at' => '2026-11-13T00:00:00.000000Z']), 'while the challenge was open'];
        yield 'a proof at the end' => [static fn (array $r): array => self::proof($r, 0, static fn (array $p): array => [...$p, 'submitted_at' => $r['ends_at']]), 'while the challenge was open'];
        yield 'votes without a vote' => [static fn (array $r): array => self::proof($r, 0, static fn (array $p): array => [...$p, 'approvals' => ['member:ana']]), 'not put to a vote'];
        yield 'rejections without a vote' => [static fn (array $r): array => self::proof($r, 0, static fn (array $p): array => [...$p, 'rejections' => ['member:ana']]), 'not put to a vote'];
        yield 'an accepted proof of an open challenge' => [static fn (array $r): array => self::proof($r, 0, static fn (array $p): array => [...$p, 'state' => 'accepted']), 'one accepted proof, and any other none'];
        yield 'a won challenge without its proof' => [static fn (array $r): array => [...$r, 'state' => 'won', 'winner' => 'member:bea', 'winning_proof' => 'p1', 'settled_at' => $r['created_at']], 'one accepted proof, and any other none'];
        yield 'a winner of an open challenge' => [static fn (array $r): array => [...$r, 'winner' => 'member:bea'], 'participant of its accepted proof'];
        yield 'a winning proof of an open challenge' => [static fn (array $r): array => [...$r, 'winning_proof' => 'p1'], 'participant of its accepted proof'];
        yield 'a settlement of an open challenge' => [static fn (array $r): array => [...$r, 'settled_at' => $r['created_at']], 'settlement time'];
        yield 'a cancelled challenge without settlement' => [static fn (array $r): array => [...$r, 'state' => 'cancelled'], 'settlement time'];
        yield 'a cancelled challenge with proofs' => [static fn (array $r): array => [...$r, 'state' => 'cancelled', 'settled_at' => $r['created_at']], 'not cancelled by its creator'];
        yield 'a settlement before the creation' => [static fn (array $r): array => [...$r, 'state' => 'removed', 'settled_at' => '2026-11-13T00:00:00.000000Z'], 'settled after it is created'];
        yield 'an invalid prize' => [static fn (array $r): array => [...$r, 'prize' => 0], 'A prize is'];
    }

    /**
     * @param Closure(array<array-key, mixed>): array<array-key, mixed> $break
     */
    #[DataProvider('brokenRecords')]
    public function testABrokenRecordIsRefused(Closure $break, string $message): void
    {
        $this->expectException(InvalidChallenge::class);
        $this->expectExceptionMessageIsOrContains($message);

        Challenge::fromArray($break(self::played()->toArray()));
    }

    /**
     * @return iterable<string, array{Closure(array<array-key, mixed>): array<array-key, mixed>, string}>
     */
    public static function brokenWins(): iterable
    {
        yield 'a winner who is not the participant' => [static fn (array $r): array => [...$r, 'winner' => 'member:cy'], 'participant of its accepted proof'];
        yield 'another winning proof' => [static fn (array $r): array => [...$r, 'winning_proof' => 'p2'], 'participant of its accepted proof'];
        yield 'no winner' => [static fn (array $r): array => [...$r, 'winner' => null], 'participant of its accepted proof'];
        yield 'no winning proof' => [static fn (array $r): array => [...$r, 'winning_proof' => null], 'participant of its accepted proof'];
        yield 'two accepted proofs' => [static fn (array $r): array => self::proof($r, 1, static fn (array $p): array => [...$p, 'state' => 'accepted']), 'one accepted proof at most'];
        yield 'no settlement' => [static fn (array $r): array => [...$r, 'settled_at' => null], 'settlement time'];
        yield 'an open challenge with an accepted proof' => [static fn (array $r): array => [...$r, 'state' => 'open', 'winner' => null, 'winning_proof' => null, 'settled_at' => null], 'one accepted proof, and any other none'];
    }

    /**
     * @param Closure(array<array-key, mixed>): array<array-key, mixed> $break
     */
    #[DataProvider('brokenWins')]
    public function testABrokenWinIsRefused(Closure $break, string $message): void
    {
        $this->expectException(InvalidChallenge::class);
        $this->expectExceptionMessageIsOrContains($message);

        Challenge::fromArray($break(self::played()->approve('p1', World::ana(), false, self::at())->toArray()));
    }

    /**
     * @return iterable<string, array{Closure(array<array-key, mixed>): array<array-key, mixed>, string}>
     */
    public static function brokenVotes(): iterable
    {
        yield 'a voter twice' => [static fn (array $r): array => self::proof($r, 0, static fn (array $p): array => [...$p, 'approvals' => ['member:ana'], 'rejections' => ['member:ana']]), 'each voter once'];
        yield 'more approvals than needed' => [static fn (array $r): array => self::proof($r, 0, static fn (array $p): array => [...$p, 'approvals' => ['member:ana', 'member:cy', 'member:dee']]), 'no more votes than needed'];
        yield 'more rejections than needed' => [static fn (array $r): array => self::proof($r, 0, static fn (array $p): array => [...$p, 'rejections' => ['member:ana', 'member:cy', 'member:dee']]), 'no more votes than needed'];
        yield 'a vote by the participant' => [static fn (array $r): array => self::proof($r, 0, static fn (array $p): array => [...$p, 'approvals' => ['member:bea']]), 'by the creator or another participant'];
        yield 'a vote by a stranger' => [static fn (array $r): array => self::proof($r, 0, static fn (array $p): array => [...$p, 'rejections' => ['member:eve']]), 'by the creator or another participant'];
        yield 'accepted without the votes' => [static fn (array $r): array => self::proof($r, 0, static fn (array $p): array => [...$p, 'state' => 'accepted']), 'accepted when, and only when'];
        yield 'the votes but not accepted' => [static fn (array $r): array => self::proof($r, 0, static fn (array $p): array => [...$p, 'approvals' => ['member:ana', 'member:cy']]), 'accepted when, and only when'];
        yield 'rejected without the votes' => [static fn (array $r): array => self::proof($r, 0, static fn (array $p): array => [...$p, 'state' => 'rejected']), 'rejected when, and only when'];
        yield 'the votes but not rejected' => [static fn (array $r): array => self::proof($r, 0, static fn (array $p): array => [...$p, 'rejections' => ['member:ana', 'member:cy']]), 'rejected when, and only when'];
        yield 'too many votes in a list' => [static fn (array $r): array => self::proof($r, 0, static fn (array $p): array => [...$p, 'approvals' => array_map(static fn (int $n): string => 'member:m'.$n, range(1, 26))]), 'lists 25 items at most'];
    }

    /**
     * @param Closure(array<array-key, mixed>): array<array-key, mixed> $break
     */
    #[DataProvider('brokenVotes')]
    public function testBrokenVotesAreRefused(Closure $break, string $message): void
    {
        $this->expectException(InvalidChallenge::class);
        $this->expectExceptionMessageIsOrContains($message);

        Challenge::fromArray($break(self::voted()->toArray()));
    }

    public function testAProofOfAParticipantOverFiveIsRefused(): void
    {
        $record = self::played()->toArray();
        $proofs = [];

        foreach (range(1, 6) as $n) {
            $proofs[] = [...$record['proofs'][0], 'id' => 'b'.$n];
        }

        $this->expectException(InvalidChallenge::class);
        $this->expectExceptionMessageIsOrContains('one of at most 5');

        Challenge::fromArray([...$record, 'proofs' => $proofs]);
    }

    public function testFiveProofsOfAParticipantAreRead(): void
    {
        $record = self::played()->toArray();
        $proofs = [];

        foreach (range(1, 5) as $n) {
            $proofs[] = [...$record['proofs'][0], 'id' => 'b'.$n];
        }

        self::assertCount(5, Challenge::fromArray([...$record, 'proofs' => $proofs])->proofs);
    }

    public function testTheFirstAndLastMomentsOfAProofAndAReviewEndingWithTheChallengeAreRead(): void
    {
        $record = self::played()->toArray();
        $edge = self::proof($record, 0, static fn (array $p): array => [...$p, 'submitted_at' => $record['created_at']]);
        $edge = self::proof($edge, 1, static fn (array $p): array => [...$p, 'submitted_at' => '2026-11-21T00:59:59.999999Z']);
        $edge = [...$edge, 'review_ends_at' => $record['ends_at']];

        self::assertSame($edge, Challenge::fromArray($edge)->toArray());
    }

    public function testASettlementAtTheCreationIsRead(): void
    {
        $record = [...self::played()->toArray(), 'state' => 'removed', 'settled_at' => self::played()->toArray()['created_at']];

        self::assertSame($record, Challenge::fromArray($record)->toArray());
    }

    /**
     * @param array<array-key, mixed>                                   $record
     * @param Closure(array<array-key, mixed>): array<array-key, mixed> $change
     *
     * @return array<array-key, mixed>
     */
    private static function proof(array $record, int $index, Closure $change): array
    {
        $proofs = $record['proofs'];
        self::assertIsArray($proofs);
        $proof = $proofs[$index];
        self::assertIsArray($proof);
        /** @var array<array-key, mixed> $proof */
        $proofs[$index] = $change($proof);

        return [...$record, 'proofs' => $proofs];
    }

    private static function challenge(): Challenge
    {
        return Challenge::create(new ChallengeDraft('c1', 'Paint', new Prize(100), self::at()->modify('+7 days'), [World::bea(), World::cy(), World::dee()], description: "Ten minis.\nOne photo each."), World::ana(), self::at(), new ChallengeRules());
    }

    private static function played(): Challenge
    {
        return self::challenge()->join(World::bea(), self::at())->join(World::cy(), self::at())
            ->submit('p1', World::bea(), 'upload:1', self::at())
            ->submit('p2', World::cy(), 'upload:2', self::at()->modify('+1 hour'));
    }

    private static function voted(): Challenge
    {
        return Challenge::create(new ChallengeDraft('c1', 'Paint', new Prize(100), self::at()->modify('+7 days'), [World::bea(), World::cy(), World::dee()], validation: Validation::Vote, votesNeeded: 2), World::ana(), self::at(), new ChallengeRules())
            ->join(World::bea(), self::at())->join(World::cy(), self::at())->join(World::dee(), self::at())
            ->submit('p1', World::bea(), 'upload:1', self::at())
            ->submit('p2', World::cy(), 'upload:2', self::at());
    }

    private static function at(): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-11-14T01:00:00Z');
    }
}
