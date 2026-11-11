<?php

declare(strict_types=1);

namespace Tilttek\Badges;

use Phil\Resource\ResourceReference;
use Psr\Clock\ClockInterface;
use Tilttek\Badges\Exception\AccessDenied;
use Tilttek\Badges\Exception\InvalidBadge;
use Tilttek\Badges\Exception\NotEarned;
use Tilttek\Badges\Exception\UnknownBadge;
use Tilttek\Badges\Store\AwardStore;
use Tilttek\Badges\Store\GrantStore;
use Tilttek\Badges\Store\ProfileStore;
use Tilttek\Badges\Store\ProgressStore;

/**
 * The members' badges: what each holds and how far they are from the others, the badges awarded and withdrawn by hand,
 * what each member chooses to show — the badges, the showcase, the title —, what they share, which applications they
 * allow to read their badges, and their erasure.
 *
 * A badge a member earned is private until they show it; a hidden badge they have not earned is left out of their
 * overview, and its criterion with it.
 */
final readonly class Badges
{
    public const int DEFAULT_SHOWCASE_SIZE = 6;

    public function __construct(
        private Evaluator $evaluator,
        private ProgressStore $progress,
        private AwardStore $awards,
        private ProfileStore $profiles,
        private GrantStore $grants,
        private ClockInterface $clock,
        private int $showcaseSize = self::DEFAULT_SHOWCASE_SIZE,
    ) {
        if ($showcaseSize < 1) {
            throw InvalidBadge::because(\sprintf('A showcase holds at least one badge; %d is not a size.', $showcaseSize));
        }
    }

    public function catalog(): Catalog
    {
        return $this->evaluator->catalog();
    }

    /**
     * The member's awards, by the moment they were earned.
     *
     * @return list<Award>
     */
    public function awards(ResourceReference $member): array
    {
        return $this->awards->of($member);
    }

    /**
     * @throws UnknownBadge
     */
    public function has(ResourceReference $member, string $code): bool
    {
        return [] !== $this->awards->of($member, $this->catalog()->get($code)->code);
    }

    /**
     * @throws UnknownBadge
     */
    public function progress(ResourceReference $member, string $code): Progress
    {
        return $this->evaluator->progress($this->catalog()->get($code), $member);
    }

    /**
     * Where the member stands with every badge of the catalog, in its order; a hidden badge they have not earned is
     * left out.
     *
     * @return list<Standing>
     */
    public function overview(ResourceReference $member): array
    {
        $standings = [];

        foreach ($this->catalog()->all() as $badge) {
            $standing = new Standing($badge, $this->evaluator->progress($badge, $member), \count($this->awards->of($member, $badge->code)));

            if (!$badge->hidden || $standing->isEarned()) {
                $standings[] = $standing;
            }
        }

        return $standings;
    }

    /**
     * Awards the badge by hand — whatever its criterion —, for the reason given; a badge that is not repeatable and is
     * already held is not awarded again, and the award held is returned.
     *
     * @throws UnknownBadge
     */
    public function award(ResourceReference $member, string $code, ?string $reason = null): Award
    {
        $badge = $this->catalog()->get($code);
        $held = $this->awards->of($member, $code);

        if (!$badge->repeatable && [] !== $held) {
            return $held[0];
        }

        $award = new Award($code, $member, max([0, ...array_map(static fn (Award $award): int => $award->number, $held)]) + 1, $this->clock->now(), $reason, true);
        $this->awards->add($award);

        return $award;
    }

    /**
     * Withdraws the awards of the badge made by hand; those earned by the activity stay, since the activity would
     * earn them again.
     *
     * @return int how many awards were withdrawn
     *
     * @throws UnknownBadge
     */
    public function revoke(ResourceReference $member, string $code): int
    {
        $revoked = 0;

        foreach ($this->awards->of($member, $this->catalog()->get($code)->code) as $award) {
            if ($award->manual) {
                $this->awards->remove($award);
                ++$revoked;
            }
        }

        return $revoked;
    }

    public function profile(ResourceReference $member): Profile
    {
        return $this->profiles->get($member);
    }

    /**
     * Makes an earned badge public.
     *
     * @throws UnknownBadge
     * @throws NotEarned
     */
    public function show(ResourceReference $member, string $code): void
    {
        $this->profiles->save($this->profiles->get($member)->show($this->earned($member, $code)));
    }

    /**
     * Makes a badge private again, out of the showcase and off as a title.
     */
    public function hide(ResourceReference $member, string $code): void
    {
        $this->profiles->save($this->profiles->get($member)->hide($code));
    }

    /**
     * Replaces the showcase with these earned badges, in this order, each made public.
     *
     * @param list<string> $codes
     *
     * @throws UnknownBadge
     * @throws NotEarned
     */
    public function feature(ResourceReference $member, array $codes): void
    {
        if (\count($codes) > $this->showcaseSize) {
            throw InvalidBadge::because(\sprintf('A showcase holds at most %d badges; %d were chosen.', $this->showcaseSize, \count($codes)));
        }

        $this->profiles->save($this->profiles->get($member)->feature(array_map(fn (string $code): string => $this->earned($member, $code), $codes)));
    }

    /**
     * Wears an earned badge as the title beside the member's name, made public; none when null.
     *
     * @throws UnknownBadge
     * @throws NotEarned
     */
    public function entitle(ResourceReference $member, ?string $code): void
    {
        $this->profiles->save($this->profiles->get($member)->entitle(null === $code ? null : $this->earned($member, $code)));
    }

    /**
     * What anyone may see of the member's badges: the badges they show and still hold, their showcase and their title.
     */
    public function showcase(ResourceReference $member): Showcase
    {
        $profile = $this->profiles->get($member);
        $held = array_map(static fn (Award $award): string => $award->badge, $this->awards->of($member));
        $title = $this->public(null === $profile->title ? [] : [$profile->title], $held);

        return new Showcase($title[0] ?? null, $this->public($profile->featured, $held), $this->public($profile->shown, $held));
    }

    /**
     * Shares an earned badge through the channel, at the member's request; returns what the channel made of it.
     *
     * @throws UnknownBadge
     * @throws NotEarned
     */
    public function share(ResourceReference $member, string $code, ShareChannel $channel): string
    {
        $held = $this->awards->of($member, $this->earned($member, $code));

        return $channel->share($held[\count($held) - 1], $this->catalog()->get($code));
    }

    /**
     * Allows another application to read the member's badges, until the member withdraws it.
     */
    public function grant(ResourceReference $member, string $client): void
    {
        if (1 !== preg_match('/^[a-z][a-z0-9_.-]{0,63}$/D', $client)) {
            throw InvalidBadge::because(\sprintf('"%s" is not an application name: 1 to 64 lower-case letters, digits, "_", "." or "-", starting with a letter.', $client));
        }

        $this->grants->grant($member, $client, $this->clock->now());
    }

    /**
     * Withdraws the consent given to an application; false when there was none.
     */
    public function withdraw(ResourceReference $member, string $client): bool
    {
        return $this->grants->withdraw($member, $client);
    }

    /**
     * The applications the member allows to read their badges.
     *
     * @return list<string>
     */
    public function clients(ResourceReference $member): array
    {
        return $this->grants->clients($member);
    }

    /**
     * The member's awards, read by another application with their consent.
     *
     * @return list<Award>
     *
     * @throws AccessDenied
     */
    public function readFor(ResourceReference $member, string $client): array
    {
        if (!$this->grants->allows($member, $client)) {
            throw AccessDenied::to($client, $member->toString());
        }

        return $this->awards->of($member);
    }

    /**
     * Erases the member: their awards, the contributions of their activity, their profile and their consents.
     */
    public function erase(ResourceReference $member): Erasure
    {
        return new Erasure(
            $this->awards->eraseMember($member),
            $this->progress->eraseMember($member),
            $this->grants->eraseMember($member),
            $this->profiles->erase($member),
        );
    }

    /**
     * The badges of these codes that the member holds and the catalog still has.
     *
     * @param list<string> $codes
     * @param list<string> $held
     *
     * @return list<Badge>
     */
    private function public(array $codes, array $held): array
    {
        $badges = [];

        foreach ($codes as $code) {
            if (\in_array($code, $held, true) && $this->catalog()->has($code)) {
                $badges[] = $this->catalog()->get($code);
            }
        }

        return $badges;
    }

    /**
     * @throws UnknownBadge
     * @throws NotEarned
     */
    private function earned(ResourceReference $member, string $code): string
    {
        if (!$this->has($member, $code)) {
            throw NotEarned::badge($code, $member->toString());
        }

        return $code;
    }
}
