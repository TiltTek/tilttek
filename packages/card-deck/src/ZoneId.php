<?php

declare(strict_types=1);

namespace Tilttek\CardDeck;

use Tilttek\CardDeck\Exception\InvalidDefinition;

/**
 * A zone of the table, written `draw`, `discard`, `table`, `hand:<player>` or `pile:<name>`.
 */
final readonly class ZoneId
{
    private function __construct(
        public ZoneKind $kind,
        public ?string $name = null,
    ) {
    }

    public static function drawPile(): self
    {
        return new self(ZoneKind::DrawPile);
    }

    public static function discard(): self
    {
        return new self(ZoneKind::Discard);
    }

    public static function table(): self
    {
        return new self(ZoneKind::Table);
    }

    public static function hand(PlayerId $player): self
    {
        return new self(ZoneKind::Hand, $player->value);
    }

    /**
     * @throws InvalidDefinition when the name does not follow the pattern of the names
     */
    public static function pile(string $name): self
    {
        return new self(ZoneKind::Pile, Name::check($name, 'A pile name'));
    }

    /**
     * @throws InvalidDefinition when the text is not a zone `toString()` could have written
     */
    public static function fromString(string $zone): self
    {
        $parts = explode(':', $zone, 2);
        $kind = $parts[0];
        $name = $parts[1] ?? null;

        return match (ZoneKind::tryFrom($kind)) {
            ZoneKind::Hand => self::hand(new PlayerId($name ?? '')),
            ZoneKind::Pile => self::pile($name ?? ''),
            ZoneKind::DrawPile, ZoneKind::Discard, ZoneKind::Table => null === $name ? new self(ZoneKind::from($kind)) : throw InvalidDefinition::because(\sprintf('The zone "%s" takes no name.', $kind)),
            null => throw InvalidDefinition::because('A zone is draw, discard, table, hand:<player> or pile:<name>.'),
        };
    }

    public function toString(): string
    {
        return null === $this->name ? $this->kind->value : $this->kind->value.':'.$this->name;
    }

    public function equals(self $other): bool
    {
        return $this->toString() === $other->toString();
    }

    /**
     * Whether this zone is the hand of that player.
     */
    public function isHandOf(?PlayerId $player): bool
    {
        return ZoneKind::Hand === $this->kind && $player?->value === $this->name;
    }
}
