<?php

declare(strict_types=1);

namespace Tilttek\PlayLog\Tests\Unit\Fixture;

use Override;
use Tilttek\PlayLog\Contract\PlayerConsent;

/**
 * A consent given by pairs: the member allows the plays of these recorders.
 */
final readonly class Consent implements PlayerConsent
{
    /**
     * @param array<string, list<string>> $allowed the recorders each member allows
     */
    public function __construct(private array $allowed)
    {
    }

    #[Override]
    public function allows(string $member, string $recordedBy): bool
    {
        return \in_array($recordedBy, $this->allowed[$member] ?? [], true);
    }
}
