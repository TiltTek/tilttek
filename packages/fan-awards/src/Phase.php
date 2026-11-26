<?php

declare(strict_types=1);

namespace Tilttek\FanAwards;

/**
 * Where an edition of the awards stands: prepared, open to nominations, in its semifinal, in its final, counted, then
 * announced at the ceremony. Ballots are cast in the three voting phases only.
 */
enum Phase: string
{
    case Draft = 'draft';

    case Nomination = 'nomination';

    case Semifinal = 'semifinal';

    case Final = 'final';

    case Counted = 'counted';

    case Announced = 'announced';

    public function isVoting(): bool
    {
        return match ($this) {
            self::Nomination, self::Semifinal, self::Final => true,
            self::Draft, self::Counted, self::Announced => false,
        };
    }

    /**
     * Whether a voter may add a nominee of their own to their ballot: in the nominations and the semifinal, never in
     * the final.
     */
    public function takesWriteIns(): bool
    {
        return self::Nomination === $this || self::Semifinal === $this;
    }

    /**
     * The phase that follows, the semifinal skipped when the edition has none; null after the announcement.
     */
    public function next(bool $semifinal): ?self
    {
        return match ($this) {
            self::Draft => self::Nomination,
            self::Nomination => $semifinal ? self::Semifinal : self::Final,
            self::Semifinal => self::Final,
            self::Final => self::Counted,
            self::Counted => self::Announced,
            self::Announced => null,
        };
    }
}
