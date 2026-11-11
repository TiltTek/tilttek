<?php

declare(strict_types=1);

namespace Tilttek\Badges\Tests\Unit;

use DateTimeImmutable;
use Override;
use Phil\Resource\ResourceReference;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tilttek\Badges\Award;
use Tilttek\Badges\Badge;
use Tilttek\Badges\Badges;
use Tilttek\Badges\Catalog;
use Tilttek\Badges\Contribution;
use Tilttek\Badges\Evaluator;
use Tilttek\Badges\Exception\AccessDenied;
use Tilttek\Badges\Exception\InvalidBadge;
use Tilttek\Badges\Exception\NotEarned;
use Tilttek\Badges\Exception\UnknownBadge;
use Tilttek\Badges\Profile;
use Tilttek\Badges\Showcase;
use Tilttek\Badges\Standing;
use Tilttek\Badges\Store\InMemoryAwardStore;
use Tilttek\Badges\Store\InMemoryGrantStore;
use Tilttek\Badges\Store\InMemoryProfileStore;
use Tilttek\Badges\Store\InMemoryProgressStore;
use Tilttek\Badges\Tests\Unit\Fixture\Channel;
use Tilttek\Badges\Tests\Unit\Fixture\Clock;

#[CoversClass(Badges::class)]
final class BadgesTest extends TestCase
{
    private InMemoryProgressStore $progress;

    private InMemoryAwardStore $awards;

    private InMemoryProfileStore $profiles;

    private InMemoryGrantStore $grants;

    private Badges $badges;

    private ResourceReference $member;

    #[Override]
    protected function setUp(): void
    {
        $this->progress = new InMemoryProgressStore();
        $this->awards = new InMemoryAwardStore();
        $this->profiles = new InMemoryProfileStore();
        $this->grants = new InMemoryGrantStore();
        $this->badges = $this->badges();
        $this->member = ResourceReference::of('member', '1');
    }

    public function testAMembersAwardsAndProgress(): void
    {
        $this->progress->add(new Contribution('commenter', 'r', $this->member, 'c1', 1, $this->at('2026-11-01 10:00:00')));
        $this->awards->add(new Award('first', $this->member, 1, $this->at('2026-11-01 10:00:00'), 'c1'));

        self::assertSame(['commenter', 'first', 'secret', 'hand', 'games'], array_map(static fn (Badge $badge): string => $badge->code, $this->badges->catalog()->all()));
        self::assertSame(['first'], array_map(static fn (Award $award): string => $award->badge, $this->badges->awards($this->member)));
        self::assertTrue($this->badges->has($this->member, 'first'));
        self::assertFalse($this->badges->has($this->member, 'commenter'));
        $progress = $this->badges->progress($this->member, 'commenter');
        self::assertSame([1, 3], [$progress->current, $progress->target]);

        self::assertSame([['commenter', 1, 0], ['first', 0, 1], ['hand', 0, 0], ['games', 0, 0]], array_map(
            static fn (Standing $standing): array => [$standing->badge->code, $standing->progress->current, $standing->awards],
            $this->badges->overview($this->member),
        ));

        $this->awards->add(new Award('secret', $this->member, 1, $this->at('2026-11-02 10:00:00'), null, true));
        self::assertSame(['commenter', 'first', 'secret', 'hand', 'games'], array_map(static fn (Standing $standing): string => $standing->badge->code, $this->badges->overview($this->member)));
    }

    public function testAnUnknownBadgeIsRefused(): void
    {
        $this->expectExceptionObject(UnknownBadge::named('platinum'));
        $this->badges->progress($this->member, 'platinum');
    }

    public function testAnUnknownBadgeIsNotHeld(): void
    {
        $this->expectExceptionObject(UnknownBadge::named('platinum'));
        $this->badges->has($this->member, 'platinum');
    }

    public function testABadgeIsAwardedAndRevokedByHand(): void
    {
        $award = $this->badges->award($this->member, 'hand', 'Organised the convention');
        self::assertSame(['hand', 1, '2026-11-07T12:00:00+00:00', 'Organised the convention', true], [$award->badge, $award->number, $award->awardedAt->format(\DATE_ATOM), $award->cause, $award->manual]);
        self::assertSame($award, $this->badges->award($this->member, 'hand'));
        self::assertCount(1, $this->awards->of($this->member));

        $this->badges->award($this->member, 'games');
        $second = $this->badges->award($this->member, 'games');
        self::assertSame(2, $second->number);
        self::assertNull($second->cause);
        $this->awards->add(new Award('games', $this->member, 3, $this->at('2026-11-08 10:00:00'), 'g1'));

        self::assertSame(2, $this->badges->revoke($this->member, 'games'));
        self::assertSame([['hand', 1], ['games', 3]], array_map(static fn (Award $award): array => [$award->badge, $award->number], $this->badges->awards($this->member)));
        self::assertSame(0, $this->badges->revoke($this->member, 'games'));
        self::assertSame(4, $this->badges->award($this->member, 'games')->number);
    }

    public function testAnUnknownBadgeIsNotAwarded(): void
    {
        $this->expectExceptionObject(UnknownBadge::named('platinum'));
        $this->badges->award($this->member, 'platinum');
    }

    public function testAnUnknownBadgeIsNotRevoked(): void
    {
        $this->expectExceptionObject(UnknownBadge::named('platinum'));
        $this->badges->revoke($this->member, 'platinum');
    }

    public function testAMemberChoosesWhatTheyShow(): void
    {
        foreach (['first', 'hand', 'games', 'secret'] as $code) {
            $this->badges->award($this->member, $code);
        }

        self::assertTrue($this->badges->profile($this->member)->isEmpty());
        self::assertEquals(new Showcase(), $this->badges->showcase($this->member));

        $this->badges->show($this->member, 'first');
        $this->badges->feature($this->member, ['games', 'hand']);
        $this->badges->entitle($this->member, 'secret');

        $profile = $this->badges->profile($this->member);
        self::assertSame([['first', 'games', 'hand', 'secret'], ['games', 'hand'], 'secret'], [$profile->shown, $profile->featured, $profile->title]);

        $showcase = $this->badges->showcase($this->member);
        self::assertSame('secret', $showcase->title?->code);
        self::assertSame(['games', 'hand'], array_map(static fn (Badge $badge): string => $badge->code, $showcase->featured));
        self::assertSame(['first', 'games', 'hand', 'secret'], array_map(static fn (Badge $badge): string => $badge->code, $showcase->shown));

        // A badge no longer held, or no longer in the catalog, is left out.
        $this->badges->revoke($this->member, 'hand');
        $this->profiles->save($this->profiles->get($this->member)->show('retired'));
        $this->awards->add(new Award('retired', $this->member, 1, $this->at('2026-11-01 10:00:00')));
        self::assertSame(['first', 'games', 'secret'], array_map(static fn (Badge $badge): string => $badge->code, $this->badges->showcase($this->member)->shown));
        self::assertSame(['games'], array_map(static fn (Badge $badge): string => $badge->code, $this->badges->showcase($this->member)->featured));

        $this->badges->hide($this->member, 'secret');
        self::assertFalse($this->badges->profile($this->member)->shows('secret'));
        $this->badges->entitle($this->member, 'games');
        self::assertSame('games', $this->badges->showcase($this->member)->title?->code);
        $this->badges->entitle($this->member, null);
        self::assertNull($this->badges->profile($this->member)->title);
        self::assertNull($this->badges->showcase($this->member)->title);
        $this->badges->revoke($this->member, 'secret');
        $this->profiles->save(new Profile($this->member, ['secret'], [], 'secret'));
        self::assertNull($this->badges->showcase($this->member)->title);
    }

    public function testOnlyAnEarnedBadgeIsShown(): void
    {
        $this->expectExceptionObject(NotEarned::badge('first', 'member:1'));
        $this->badges->show($this->member, 'first');
    }

    public function testOnlyAnEarnedBadgeIsFeatured(): void
    {
        $this->badges->award($this->member, 'hand');
        $this->expectExceptionObject(NotEarned::badge('first', 'member:1'));
        $this->badges->feature($this->member, ['hand', 'first']);
    }

    public function testOnlyAnEarnedBadgeIsWorn(): void
    {
        $this->expectExceptionObject(NotEarned::badge('first', 'member:1'));
        $this->badges->entitle($this->member, 'first');
    }

    public function testAShowcaseHasALimit(): void
    {
        $badges = $this->badges(2);
        $badges->award($this->member, 'hand');
        $badges->award($this->member, 'games');
        $badges->feature($this->member, ['hand', 'games']);
        self::assertSame(['hand', 'games'], $badges->profile($this->member)->featured);

        $this->expectExceptionObject(InvalidBadge::because('A showcase holds at most 2 badges; 3 were chosen.'));
        $badges->feature($this->member, ['hand', 'games', 'first']);
    }

    public function testAShowcaseHoldsABadge(): void
    {
        self::assertSame(6, Badges::DEFAULT_SHOWCASE_SIZE);
        self::assertInstanceOf(Badges::class, $this->badges(1));
        $this->expectExceptionObject(InvalidBadge::because('A showcase holds at least one badge; 0 is not a size.'));
        $this->badges(0);
    }

    public function testTheDefaultShowcaseHoldsSix(): void
    {
        $badges = new Badges(new Evaluator(Catalog::fromArray(array_fill_keys(['a', 'b', 'c', 'd', 'e', 'f', 'g'], ['criterion' => ['manual' => true]])), $this->progress), $this->progress, $this->awards, $this->profiles, $this->grants, new Clock());

        foreach (['a', 'b', 'c', 'd', 'e', 'f', 'g'] as $code) {
            $badges->award($this->member, $code);
        }

        $badges->feature($this->member, ['a', 'b', 'c', 'd', 'e', 'f']);
        $this->expectException(InvalidBadge::class);
        $badges->feature($this->member, ['a', 'b', 'c', 'd', 'e', 'f', 'g']);
    }

    public function testAMemberSharesAnEarnedBadge(): void
    {
        $this->badges->award($this->member, 'games');
        $this->badges->award($this->member, 'games');

        self::assertSame('https://example.org/shared/games/2', $this->badges->share($this->member, 'games', new Channel()));
        $this->expectExceptionObject(NotEarned::badge('hand', 'member:1'));
        $this->badges->share($this->member, 'hand', new Channel());
    }

    public function testAnotherApplicationReadsWithConsent(): void
    {
        $this->badges->award($this->member, 'hand');
        $this->badges->grant($this->member, 'portfolio');
        $this->badges->grant($this->member, 'a'.str_repeat('b', 63));

        self::assertSame(['a'.str_repeat('b', 63), 'portfolio'], $this->badges->clients($this->member));
        self::assertSame(['hand'], array_map(static fn (Award $award): string => $award->badge, $this->badges->readFor($this->member, 'portfolio')));
        self::assertTrue($this->badges->withdraw($this->member, 'portfolio'));
        self::assertFalse($this->badges->withdraw($this->member, 'portfolio'));

        $this->expectExceptionObject(AccessDenied::to('portfolio', 'member:1'));
        $this->badges->readFor($this->member, 'portfolio');
    }

    public function testAClientIsNamed(): void
    {
        $this->expectExceptionObject(InvalidBadge::because('"Portfolio" is not an application name: 1 to 64 lower-case letters, digits, "_", "." or "-", starting with a letter.'));
        $this->badges->grant($this->member, 'Portfolio');
    }

    public function testAClientNameEndsWithTheName(): void
    {
        $this->expectException(InvalidBadge::class);
        $this->badges->grant($this->member, "portfolio\n");
    }

    public function testAClientNameHasALimit(): void
    {
        $this->expectException(InvalidBadge::class);
        $this->badges->grant($this->member, 'a'.str_repeat('b', 64));
    }

    public function testAMemberIsErased(): void
    {
        $this->progress->add(new Contribution('commenter', 'r', $this->member, 'c1', 1, $this->at('2026-11-01 10:00:00')));
        $this->badges->award($this->member, 'hand');
        $this->badges->award($this->member, 'games');
        $this->badges->show($this->member, 'hand');
        $this->badges->grant($this->member, 'portfolio');

        $erasure = $this->badges->erase($this->member);
        self::assertSame([2, 1, 1, true], [$erasure->awards, $erasure->contributions, $erasure->grants, $erasure->profile]);
        self::assertSame([], $this->badges->awards($this->member));
        $again = $this->badges->erase($this->member);
        self::assertSame([0, 0, 0, false], [$again->awards, $again->contributions, $again->grants, $again->profile]);
    }

    private function badges(int $showcase = Badges::DEFAULT_SHOWCASE_SIZE): Badges
    {
        $catalog = Catalog::fromArray([
            'commenter' => ['criterion' => ['count' => ['verbs' => ['news.commented'], 'times' => 3]]],
            'first' => ['criterion' => ['first' => ['verbs' => ['news.commented']]]],
            'secret' => ['hidden' => true, 'criterion' => ['first' => ['verbs' => ['easter.egg']]]],
            'hand' => ['criterion' => ['manual' => true]],
            'games' => ['repeatable' => true, 'criterion' => ['manual' => true]],
        ]);

        return new Badges(new Evaluator($catalog, $this->progress), $this->progress, $this->awards, $this->profiles, $this->grants, new Clock(), $showcase);
    }

    private function at(string $moment): DateTimeImmutable
    {
        return new DateTimeImmutable($moment.' UTC');
    }
}
