<?php

declare(strict_types=1);

namespace Tilttek\GameCatalog\Tests\Unit\Fixture;

use Override;
use Tilttek\GameCatalog\Contract\CatalogObserver;
use Tilttek\GameCatalog\CorrectionStatus;

/**
 * An observer that writes down what it is told, one line per call.
 */
final class Observed implements CatalogObserver
{
    /** @var list<string> */
    public array $calls = [];

    #[Override]
    public function erratumPublished(string $entry, string $erratum): void
    {
        $this->calls[] = 'erratum '.$entry.' '.$erratum;
    }

    #[Override]
    public function correctionProposed(string $entry, string $correction): void
    {
        $this->calls[] = 'proposed '.$entry.' '.$correction;
    }

    #[Override]
    public function correctionDecided(string $correction, string $member, CorrectionStatus $status): void
    {
        $this->calls[] = 'decided '.$correction.' '.$member.' '.$status->value;
    }
}
