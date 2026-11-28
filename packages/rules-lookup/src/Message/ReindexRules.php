<?php

declare(strict_types=1);

namespace Tilttek\RulesLookup\Message;

use Tilttek\RulesLookup\Exception\InvalidRule;
use Tilttek\RulesLookup\Folding;
use Tilttek\RulesLookup\RuleId;

/**
 * The message an application sends when the rules of a system change — a data package imported or updated —, so that
 * a store kept elsewhere indexes them again. It carries identifiers only: the handler reads the rules itself.
 */
final readonly class ReindexRules
{
    /**
     * @param string  $system  the key of the system
     * @param ?string $version its version; every version when null
     *
     * @throws InvalidRule
     */
    public function __construct(public string $system, public ?string $version = null)
    {
        if (!Folding::isKey($system) || (null !== $version && !RuleId::isVersion($version))) {
            throw InvalidRule::because(\sprintf('"%s" is not the key of a system, or "%s" not a version.', $system, $version ?? ''));
        }
    }
}
