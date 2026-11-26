<?php

declare(strict_types=1);

namespace Tilttek\ScoreSheets\Exception;

use DomainException;

/**
 * A change the status of the sheet refuses: a score entered on a closed sheet, a sheet published, closed or reopened
 * twice.
 */
final class IllegalTransition extends DomainException implements ScoreSheetsException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
