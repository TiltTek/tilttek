<?php

declare(strict_types=1);

namespace Tilttek\Generator\Import\Tracery;

/**
 * `#[name:rule]symbol.modifier#`: its actions, then its symbol — none for a tag of actions only — and its modifiers.
 *
 * @internal
 */
final readonly class Tag
{
    /**
     * @param list<Action> $actions
     * @param list<string> $modifiers
     */
    public function __construct(public array $actions, public string $symbol, public array $modifiers)
    {
    }

    /**
     * Whether the rule is plain text, without tags or actions.
     *
     * @param list<Text|Tag|Action> $rule
     */
    public static function isPlain(array $rule): bool
    {
        return array_all($rule, static fn (Text|self|Action $segment): bool => $segment instanceof Text);
    }
}
