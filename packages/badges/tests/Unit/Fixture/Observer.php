<?php

declare(strict_types=1);

namespace Tilttek\Badges\Tests\Unit\Fixture;

use Override;
use RuntimeException;
use Tilttek\Badges\Award;
use Tilttek\Badges\AwardObserver;
use Tilttek\Badges\Badge;

/**
 * An observer that writes down what it is told, as `awarded badge member #number`; it fails the next `$failures`
 * times it is told, before writing anything down.
 */
final class Observer implements AwardObserver
{
    /**
     * @var list<string>
     */
    public array $told = [];

    public int $failures = 0;

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
        $this->fail();
        $this->told[] = \sprintf('awarded %s %s #%d', $badge->code, $award->member->toString(), $award->number);
    }

    #[Override]
    public function revoked(Award $award, Badge $badge): void
    {
        $this->fail();
        $this->told[] = \sprintf('revoked %s %s #%d', $badge->code, $award->member->toString(), $award->number);
    }

    private function fail(): void
    {
        if (0 < $this->failures) {
            --$this->failures;

            throw new RuntimeException('The observer failed.');
        }
    }
}
