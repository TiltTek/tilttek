<?php

declare(strict_types=1);

namespace Tilttek\Generator\Definition;

/**
 * A field filled by a template: its text.
 */
final readonly class TemplateField implements Field
{
    public function __construct(public string $source)
    {
    }
}
