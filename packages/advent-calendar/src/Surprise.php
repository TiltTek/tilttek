<?php

declare(strict_types=1);

namespace Tilttek\AdventCalendar;

use Phil\Resource\ResourceReference;
use Tilttek\AdventCalendar\Exception\InvalidCalendar;

/**
 * The surprise of a box: a reference to a reward — points, a badge, content, a code —, never the reward itself. The
 * application grants it when it is told a box was opened; a code stays in the application's pool until then.
 */
final readonly class Surprise
{
    public const int MAX_QUANTITY = 1_000_000;

    private function __construct(
        public SurpriseKind $kind,
        public string $reference,
        public int $quantity,
    ) {
    }

    /**
     * @param string $reference 1 to 255 printable bytes: a ledger, a badge, a resource reference or a pool
     * @param int    $quantity  1 to 1 000 000
     */
    public static function of(SurpriseKind $kind, string $reference, int $quantity = 1): self
    {
        if (1 !== preg_match('/^[\x21-\x7e][\x20-\x7e]{0,254}$/D', $reference)) {
            throw InvalidCalendar::because('The reference of a surprise is 1 to 255 printable ASCII characters, starting with a non-space.');
        }

        if ($quantity < 1 || $quantity > self::MAX_QUANTITY) {
            throw InvalidCalendar::because(\sprintf('The quantity of a surprise is 1 to %d; %d is not one.', self::MAX_QUANTITY, $quantity));
        }

        return new self($kind, $reference, $quantity);
    }

    public static function points(int $amount, string $ledger = 'points'): self
    {
        return self::of(SurpriseKind::Points, $ledger, $amount);
    }

    public static function badge(string $badge): self
    {
        return self::of(SurpriseKind::Badge, $badge);
    }

    public static function content(ResourceReference $content): self
    {
        return self::of(SurpriseKind::Content, $content->toString());
    }

    public static function code(string $pool): self
    {
        return self::of(SurpriseKind::Code, $pool);
    }

    /**
     * @return array{kind: string, reference: string, quantity: int}
     */
    public function toArray(): array
    {
        return ['kind' => $this->kind->value, 'reference' => $this->reference, 'quantity' => $this->quantity];
    }
}
