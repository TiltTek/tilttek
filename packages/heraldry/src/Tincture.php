<?php

declare(strict_types=1);

namespace Tilttek\Heraldry;

/**
 * The tinctures of a coat of arms: two metals, five colours and two furs.
 */
enum Tincture: string
{
    case Or = 'or';
    case Argent = 'argent';
    case Gules = 'gules';
    case Azure = 'azure';
    case Vert = 'vert';
    case Sable = 'sable';
    case Purpure = 'purpure';
    case Ermine = 'ermine';
    case Vair = 'vair';

    public function kind(): TinctureKind
    {
        return match ($this) {
            self::Or, self::Argent => TinctureKind::Metal,
            self::Ermine, self::Vair => TinctureKind::Fur,
            default => TinctureKind::Colour,
        };
    }

    /**
     * Whether this tincture may lie on the other under the rule of tincture: never metal on metal, colour on colour, or
     * a tincture on itself; a fur goes on anything else.
     */
    public function contrastsWith(self $ground): bool
    {
        return $this !== $ground && (TinctureKind::Fur === $this->kind() || $this->kind() !== $ground->kind());
    }

    /**
     * @return list<self>
     */
    public static function ofKind(TinctureKind $kind): array
    {
        return array_values(array_filter(self::cases(), static fn (self $tincture): bool => $kind === $tincture->kind()));
    }
}
