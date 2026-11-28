<?php

declare(strict_types=1);

namespace Tilttek\Downtime;

use Tilttek\Downtime\Exception\InvalidDowntime;
use Tilttek\Downtime\Internal\Identifier;
use Tilttek\Downtime\Internal\Text;

/**
 * One result an activity may end with: its label, the text the players read, and its effects on the resources the
 * system names.
 */
final readonly class Outcome
{
    /** The longest label, in characters. */
    public const int MAX_LABEL = 120;

    /** The longest text, in characters. */
    public const int MAX_TEXT = 2_000;

    /** The most effects of one outcome. */
    public const int MAX_EFFECTS = 8;

    public string $label;

    public string $text;

    /** @var list<Effect> */
    public array $effects;

    /**
     * @param string       $key     the key of the outcome within its activity
     * @param string       $label   one line of 1 to `MAX_LABEL` characters
     * @param list<Effect> $effects up to `MAX_EFFECTS`, each resource once
     * @param string       $text    lines of up to `MAX_TEXT` characters, possibly empty
     *
     * @throws InvalidDowntime when a value is out of its shape or its bounds, or a resource comes twice
     */
    public function __construct(public string $key, string $label, array $effects = [], string $text = '')
    {
        Identifier::key($key, 'an outcome');
        $this->label = Text::line($label, self::MAX_LABEL, 'label of an outcome');
        $this->text = Text::block($text, self::MAX_TEXT, 'text of an outcome');

        if (\count($effects) > self::MAX_EFFECTS) {
            throw InvalidDowntime::because(\sprintf('An outcome has at most %d effects.', self::MAX_EFFECTS));
        }

        $twice = Identifier::twice(array_map(static fn (Effect $effect): string => $effect->resource, $effects));

        if (null !== $twice) {
            throw InvalidDowntime::because(\sprintf('The resource %s comes twice among the effects of the outcome %s.', $twice, $key));
        }

        $this->effects = $effects;
    }
}
