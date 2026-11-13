<?php

declare(strict_types=1);

namespace Tilttek\SeasonalEvents;

use Phil\Resource\ResourceReference;

/**
 * One thing a season or an event brings: its kind and the reference of the quest, item or decoration, `quest:snow-hunt`.
 */
final readonly class Content
{
    public function __construct(
        public ContentKind $kind,
        public ResourceReference $reference,
    ) {
    }

    public static function quest(string $reference): self
    {
        return new self(ContentKind::Quest, ResourceReference::fromString($reference));
    }

    public static function item(string $reference): self
    {
        return new self(ContentKind::Item, ResourceReference::fromString($reference));
    }

    public static function decoration(string $reference): self
    {
        return new self(ContentKind::Decoration, ResourceReference::fromString($reference));
    }

    /**
     * @return array{kind: string, reference: string}
     */
    public function toArray(): array
    {
        return ['kind' => $this->kind->value, 'reference' => $this->reference->toString()];
    }
}
