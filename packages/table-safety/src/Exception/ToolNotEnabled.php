<?php

declare(strict_types=1);

namespace Tilttek\TableSafety\Exception;

use DomainException;
use Tilttek\TableSafety\SafetyTool;

/**
 * A tool the table has not chosen: its answers are not taken and its signals are not raised.
 */
final class ToolNotEnabled extends DomainException implements TableSafetyException
{
    public static function at(SafetyTool $tool): self
    {
        return new self(\sprintf('The table does not use the tool "%s".', $tool->value));
    }
}
