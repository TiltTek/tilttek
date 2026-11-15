<?php

declare(strict_types=1);

namespace Tilttek\LeaderboardsBundle\Infrastructure\Twig;

use DateTimeImmutable;
use Phil\Resource\ResourceReference;
use Psr\Clock\ClockInterface;
use Tilttek\Leaderboards\Appearance;
use Tilttek\Leaderboards\Circle;
use Tilttek\Leaderboards\Exception\InvalidLeaderboard;
use Tilttek\Leaderboards\Leaderboards;
use Tilttek\Leaderboards\League\Division;
use Tilttek\Leaderboards\League\League;
use Tilttek\Leaderboards\League\Leagues;
use Tilttek\Leaderboards\League\Membership;
use Tilttek\Leaderboards\Period\Period;
use Tilttek\Leaderboards\Standing;
use Tilttek\Leaderboards\Team;
use Tilttek\Leaderboards\TeamRow;
use Tilttek\LeaderboardsBundle\Infrastructure\Event\CelebrateOn;
use Tilttek\LeaderboardsBundle\Infrastructure\Event\Summit;
use Tilttek\LeaderboardsBundle\Infrastructure\Lead\Contract\LeadStore;
use Twig\Attribute\AsTwigFunction;

/**
 * The leaderboards in the application's templates: `tilttek_leaderboards_standing('games-month', 'member:44')` a board
 * as that member sees it — among friends with `among: ['member:7', 'member:9']`, for an object with `object:
 * 'game:catan'` —; `tilttek_leaderboards_frozen('games-month', '2026-10', 'member:44')` a snapshot;
 * `tilttek_leaderboards_division('weekly-xp', 'member:44')` the member's division on a board with a league;
 * `tilttek_leaderboards_league('weekly-xp', 'member:44')` everything their league screen shows;
 * `tilttek_leaderboards_teams('house-cup', {red: ['member:1', 'member:2'], blue: ['member:3']})` the teams ranked;
 * `tilttek_leaderboards_titles('member:44')` the titles the member holds, `tilttek_leaderboards_holds('legend', 'member:44',
 * 'game:catan')` whether they hold one board's; `tilttek_leaderboards_appearance(
 * 'member:44')` the member's choice, for the form that changes it; `tilttek_leaderboards_celebration('games-month',
 * 'member:44')` how the member reached the top — when the last period closed, or in the period under way when the site
 * celebrates on a score change —, or null; `tilttek_leaderboards_kit('Badge')` the name a recipe of the kit is installed
 * under.
 */
final readonly class LeaderboardsExtension
{
    public function __construct(
        private Leaderboards $leaderboards,
        private string $domain,
        private ?Leagues $leagues = null,
        private KitComponents $kit = new KitComponents(),
        private ?ClockInterface $clock = null,
        private ?string $celebration = null,
        private CelebrateOn $celebrateOn = CelebrateOn::PeriodClose,
        private ?LeadStore $leads = null,
    ) {
    }

    /**
     * The name under which the recipe of the kit is installed in the application.
     */
    #[AsTwigFunction('tilttek_leaderboards_kit')]
    public function kit(string $recipe): string
    {
        return $this->kit->name($recipe);
    }

    /**
     * The translation domain of the templates' texts.
     */
    #[AsTwigFunction('tilttek_leaderboards_domain')]
    public function domain(): string
    {
        return $this->domain;
    }

    /**
     * @param list<string> $among the members of a circle, as `kind:identifier`; none for every member
     */
    #[AsTwigFunction('tilttek_leaderboards_standing')]
    public function standing(string $board, ?string $viewer = null, ?string $period = null, int $limit = Leaderboards::DEFAULT_LIMIT, array $among = [], ?string $object = null): Standing
    {
        return $this->leaderboards->standing($board, $this->reference($viewer), $period, $limit, [] === $among ? null : Circle::of(...$among), $this->reference($object));
    }

    #[AsTwigFunction('tilttek_leaderboards_frozen')]
    public function frozen(string $board, string $period, ?string $viewer = null, int $limit = Leaderboards::DEFAULT_LIMIT): ?Standing
    {
        return $this->leaderboards->frozen($board, $period, $this->reference($viewer), $limit);
    }

    #[AsTwigFunction('tilttek_leaderboards_division')]
    public function division(string $board, string $viewer, ?string $period = null): ?Division
    {
        return $this->leaderboards->division($board, ResourceReference::fromString($viewer), $period);
    }

    /**
     * Everything the member's league screen on the board shows.
     *
     * @throws InvalidLeaderboard when the board has no league
     */
    #[AsTwigFunction('tilttek_leaderboards_league')]
    public function league(string $board, string $viewer): LeagueView
    {
        $leagues = $this->leagues ?? throw InvalidLeaderboard::because('The leaderboards are not given their leagues.');
        $member = ResourceReference::fromString($viewer);
        $league = $leagues->league($board);
        $definition = $this->leaderboards->boards()->get($board);
        $now = $this->clock?->now() ?? new DateTimeImmutable();
        $lastWeek = $definition->schedule->previous($now);

        return new LeagueView(
            $definition,
            $league->tiers,
            $leagues->tier($board, $member),
            $this->leaderboards->division($board, $member),
            $definition->schedule->at($now)?->end,
            $lastWeek instanceof Period ? $leagues->membership($board, $member, $lastWeek->code) : null,
            $this->leaderboards->appearance($member),
        );
    }

    /**
     * The teams ranked on the board in the period — the one under way by default —, each team's members as
     * `kind:identifier`, by team name.
     *
     * @param array<string, list<string>> $teams
     *
     * @return list<TeamRow>
     */
    #[AsTwigFunction('tilttek_leaderboards_teams')]
    public function teams(string $board, array $teams, ?string $period = null, ?string $object = null): array
    {
        $list = [];

        foreach ($teams as $name => $members) {
            $list[] = new Team($name, Circle::of(...$members));
        }

        return $this->leaderboards->teams($board, $list, $period, $this->reference($object));
    }

    /**
     * @return list<string>
     */
    #[AsTwigFunction('tilttek_leaderboards_titles')]
    public function titles(string $member): array
    {
        return $this->leaderboards->titles(ResourceReference::fromString($member));
    }

    /**
     * Whether the member holds the board's title now, for the object given on a board ranked by object.
     */
    #[AsTwigFunction('tilttek_leaderboards_holds')]
    public function holds(string $board, string $member, ?string $object = null): bool
    {
        return $this->leaderboards->holds($board, ResourceReference::fromString($member), $this->reference($object));
    }

    /**
     * How the member reached the top: promoted into the highest tier when the last week of a league closed; first of
     * the snapshot of the last period over; or — when the site celebrates on a score change — in the lead of the period
     * under way, for as long as they hold it. A period given is read in place of the last one over, or of the one under
     * way. Null when they did not reach the top, or when the celebration is disabled.
     */
    #[AsTwigFunction('tilttek_leaderboards_celebration')]
    public function celebration(string $board, string $viewer, ?string $period = null): ?CelebrationView
    {
        if (null === $this->celebration) {
            return null;
        }

        $definition = $this->leaderboards->boards()->get($board);
        $member = ResourceReference::fromString($viewer);
        $now = $this->clock?->now() ?? new DateTimeImmutable();
        $closed = $period ?? $definition->schedule->previous($now)?->code;
        $league = $definition->league;
        $membership = $league instanceof League && null !== $closed ? $this->leagues?->membership($board, $member, $closed) : null;

        if ($league instanceof League && $membership instanceof Membership && Summit::HighestTier === Summit::ofMembership($membership, $league->tiers)) {
            return new CelebrationView($definition, $closed, Summit::HighestTier, $this->celebration, array_last($league->tiers));
        }

        if (CelebrateOn::ScoreChange === $this->celebrateOn) {
            $current = $period ?? $definition->schedule->at($now)?->code;

            return null !== $current && true === $this->leads?->has($board, $current, $member) && 1 === $this->leaderboards->rank($board, $member, $current)?->rank
                ? new CelebrationView($definition, $current, Summit::Lead, $this->celebration)
                : null;
        }

        return null !== $closed && 1 === $this->leaderboards->frozen($board, $closed, $member, 1)?->own?->rank ? new CelebrationView($definition, $closed, Summit::FirstPlace, $this->celebration) : null;
    }

    #[AsTwigFunction('tilttek_leaderboards_appearance')]
    public function appearance(string $member): Appearance
    {
        return $this->leaderboards->appearance(ResourceReference::fromString($member));
    }

    private function reference(?string $member): ?ResourceReference
    {
        return null === $member ? null : ResourceReference::fromString($member);
    }
}
