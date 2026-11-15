<?php

declare(strict_types=1);

namespace Tilttek\FriendChallenges\Tests\Unit\Fixture;

use Phil\Resource\ResourceReference;
use Symfony\Component\Clock\DatePoint;
use Symfony\Component\Clock\MockClock;
use Tilttek\FriendChallenges\ChallengeDraft;
use Tilttek\FriendChallenges\ChallengeRules;
use Tilttek\FriendChallenges\Contract\ChallengeStore;
use Tilttek\FriendChallenges\Contract\PrizeEscrow;
use Tilttek\FriendChallenges\Escrow\InMemoryPrizeEscrow;
use Tilttek\FriendChallenges\FriendChallenges;
use Tilttek\FriendChallenges\Prize;
use Tilttek\FriendChallenges\Store\InMemoryChallengeStore;
use Tilttek\FriendChallenges\Store\InMemoryFriendship;
use Tilttek\FriendChallenges\Store\InMemoryModeration;
use Tilttek\FriendChallenges\Validation;
use Tilttek\FriendChallenges\Visibility;

/**
 * Ana sets challenges to her friends Bea, Cy and Dee; Mo moderates; everything in memory, at a time in Montréal. Ana
 * holds 1 000 points.
 */
final class World
{
    public const string NOW = '2026-11-13 20:00:00 America/Toronto';

    public MockClock $clock;

    public InMemoryPrizeEscrow $points;

    public InMemoryFriendship $friends;

    public InMemoryModeration $moderation;

    public Recorder $events;

    public FriendChallenges $challenges;

    public function __construct(
        public ChallengeStore $store = new InMemoryChallengeStore(),
        ?PrizeEscrow $escrow = null,
        ChallengeRules $rules = new ChallengeRules(),
    ) {
        $this->clock = new MockClock(self::NOW);
        $this->points = new InMemoryPrizeEscrow();
        $this->points->fund(self::ana(), 1000);

        $this->friends = new InMemoryFriendship();

        foreach ([self::bea(), self::cy(), self::dee()] as $friend) {
            $this->friends->befriend(self::ana(), $friend);
        }

        $this->moderation = new InMemoryModeration(self::mo());
        $this->events = new Recorder();
        $this->challenges = new FriendChallenges($this->store, $escrow ?? $this->points, $this->friends, $this->moderation, $this->events, $rules, $this->clock);
    }

    /**
     * @param list<ResourceReference>|null $invitees
     */
    public function draft(string $id = 'c1', Validation $validation = Validation::Creator, int $votes = 1, ?array $invitees = null, Visibility $visibility = Visibility::Friends, int $prize = 100): ChallengeDraft
    {
        return new ChallengeDraft(
            $id,
            'Paint ten minis in a week',
            new Prize($prize),
            $this->in('+7 days'),
            $invitees ?? (Visibility::Friends === $visibility ? [self::bea(), self::cy(), self::dee()] : []),
            $visibility,
            $validation,
            'Show a photo of each.',
            $votes,
        );
    }

    public function in(string $modifier): DatePoint
    {
        return $this->clock->now()->modify($modifier);
    }

    public static function ana(): ResourceReference
    {
        return ResourceReference::of('member', 'ana');
    }

    public static function bea(): ResourceReference
    {
        return ResourceReference::of('member', 'bea');
    }

    public static function cy(): ResourceReference
    {
        return ResourceReference::of('member', 'cy');
    }

    public static function dee(): ResourceReference
    {
        return ResourceReference::of('member', 'dee');
    }

    public static function mo(): ResourceReference
    {
        return ResourceReference::of('member', 'mo');
    }

    public static function eve(): ResourceReference
    {
        return ResourceReference::of('member', 'eve');
    }
}
