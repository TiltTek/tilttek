<?php

declare(strict_types=1);

namespace Tilttek\Badges\Tests\Unit\Fixture;

use Override;
use Tilttek\Badges\Award;
use Tilttek\Badges\AwardObserver;
use Tilttek\Badges\Badge;

/**
 * An observer that writes down what it is told, as `awarded badge member #number`.
 */
final class Observer implements AwardObserver
{
    /**
     * @var list<string>
     */
    public array $told = [];

    /**
     * What it was told so far, read again at each call rather than from what an earlier assertion established.
     *
     * @return list<string>
     */
    public function told(): array
    {
        return $this->told;
    }

    public function forget(): void
    {
        $this->told = [];
    }

    #[Override]
    public function awarded(Award $award, Badge $badge): void
    {
        $this->told[] = \sprintf('awarded %s %s #%d', $badge->code, $award->member->toString(), $award->number);
    }

    #[Override]
    public function revoked(Award $award, Badge $badge): void
    {
        $this->told[] = \sprintf('revoked %s %s #%d', $badge->code, $award->member->toString(), $award->number);
    }
}
