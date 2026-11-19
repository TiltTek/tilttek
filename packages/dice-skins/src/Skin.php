<?php

declare(strict_types=1);

namespace Tilttek\DiceSkins;

use Tilttek\DiceSkins\Exception\InvalidSkin;

/**
 * A skin a site offers: its key, its label, the look of its dice, and the look of some types of dice — a d20 of
 * another colour than the rest of the set — by number of sides. Every look keeps its numbers readable: its ink stands
 * out from its faces at 4.5:1 at least, the contrast WCAG 2.2 asks of text at level AA.
 */
final readonly class Skin
{
    public const int MAX_SIDES = 1000;

    public const int MAX_TYPES = 32;

    public const int MAX_LABEL_LENGTH = 80;

    /**
     * @var array<int, SkinLook> the looks of some types of dice, by number of sides
     */
    public array $types;

    /**
     * @param array<array-key, mixed> $types the looks of some types of dice, by number of sides: `SkinLook` values
     *                                       under whole numbers
     *
     * @throws InvalidSkin when the key, the label, a number of sides or the contrast of a look is not as described
     */
    public function __construct(
        public string $key,
        public string $label,
        public SkinLook $look,
        array $types = [],
    ) {
        if (1 !== preg_match('/^[a-z0-9](?:[a-z0-9-]{0,62}[a-z0-9])?$/D', $key)) {
            throw InvalidSkin::because(\sprintf('The key of a skin is 1 to 64 characters among a-z, 0-9 and inner "-"; "%s" is not.', mb_substr($key, 0, 32)));
        }

        if ('' === trim($label) || !mb_check_encoding($label, 'UTF-8') || mb_strlen($label) > self::MAX_LABEL_LENGTH || 1 === preg_match('/\p{Cc}/u', $label)) {
            throw InvalidSkin::because(\sprintf('The label of the skin "%s" is 1 to %d characters in UTF-8, without control characters.', $key, self::MAX_LABEL_LENGTH));
        }

        if (\count($types) > self::MAX_TYPES) {
            throw InvalidSkin::because(\sprintf('The skin "%s" gives %d types of dice their own look; it gives %d at most.', $key, \count($types), self::MAX_TYPES));
        }

        $this->readable($key, 'its dice', $look);
        $looks = [];

        foreach ($types as $sides => $typeLook) {
            if (!\is_int($sides) || !$typeLook instanceof SkinLook) {
                throw InvalidSkin::because(\sprintf('The types of the skin "%s" are looks by number of sides.', $key));
            }

            self::sides($sides);
            $this->readable($key, \sprintf('its d%d', $sides), $typeLook);
            $looks[$sides] = $typeLook;
        }

        $this->types = $looks;
    }

    /**
     * The look of a die of this number of sides: its own, or that of the skin.
     */
    public function lookFor(int $sides): SkinLook
    {
        return $this->types[$sides] ?? $this->look;
    }

    /**
     * The skin as `SkinDefinitions` reads it back.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $skin = ['key' => $this->key, 'label' => $this->label, ...$this->look->toArray()];

        if ([] !== $this->types) {
            $types = [];

            foreach ($this->types as $sides => $look) {
                $types[(string) $sides] = $look->toArray();
            }

            $skin['types'] = $types;
        }

        return $skin;
    }

    /**
     * @throws InvalidSkin when a die cannot have this number of sides
     */
    public static function sides(int $sides): int
    {
        if ($sides < 2 || $sides > self::MAX_SIDES) {
            throw InvalidSkin::because(\sprintf('A die has 2 to %d sides; not %d.', self::MAX_SIDES, $sides));
        }

        return $sides;
    }

    private function readable(string $key, string $what, SkinLook $look): void
    {
        if (!$look->reaches(Color::AA)) {
            throw InvalidSkin::because(\sprintf('The numbers of %s of the skin "%s", %s on %s, stand out at %.2f:1; a skin keeps them at %.1f:1 at least.', $what, $key, $look->ink->hex, $look->face->hex, $look->contrast(), Color::AA));
        }
    }
}
