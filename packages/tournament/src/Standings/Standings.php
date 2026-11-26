<?php

declare(strict_types=1);

namespace Tilttek\Tournament\Standings;

/**
 * The standings of a stage or a pool, the first first.
 */
final readonly class Standings
{
    /**
     * @param list<Standing> $standings by rank
     */
    public function __construct(
        public array $standings,
    ) {
    }

    public function of(string $participant): ?Standing
    {
        foreach ($this->standings as $standing) {
            if ($participant === $standing->participant) {
                return $standing;
            }
        }

        return null;
    }

    /**
     * The participants by rank.
     *
     * @return list<string>
     */
    public function participants(): array
    {
        return array_map(static fn (Standing $standing): string => $standing->participant, $this->standings);
    }

    /**
     * The first participants by rank, those who withdrew left out: the qualifiers of a stage.
     *
     * @return list<string>
     */
    public function qualifiers(int $count): array
    {
        $active = array_filter($this->standings, static fn (Standing $standing): bool => !$standing->withdrawn);

        return \array_slice(array_map(static fn (Standing $standing): string => $standing->participant, $active), 0, max(0, $count));
    }

    /**
     * What moved from the standings given to these: one change for each participant whose rank or points differ, in
     * the order of these standings, then those no longer in them.
     *
     * @return list<StandingChange>
     */
    public function since(self $before): array
    {
        $changes = [];

        foreach ($this->standings as $after) {
            $was = $before->of($after->participant);

            if ($was?->rank !== $after->rank || $was->points !== $after->points) {
                $changes[] = new StandingChange($after->participant, $was?->rank, $after->rank, $was?->points, $after->points);
            }
        }

        foreach ($before->standings as $was) {
            if (!$this->of($was->participant) instanceof Standing) {
                $changes[] = new StandingChange($was->participant, $was->rank, null, $was->points, null);
            }
        }

        return $changes;
    }
}
