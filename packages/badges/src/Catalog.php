<?php

declare(strict_types=1);

namespace Tilttek\Badges;

use DateTimeZone;
use Throwable;
use Tilttek\Badges\Criterion\CriterionReader;
use Tilttek\Badges\Criterion\Leaf;
use Tilttek\Badges\Criterion\Measure;
use Tilttek\Badges\Exception\InvalidBadge;
use Tilttek\Badges\Exception\UnknownBadge;

/**
 * The badges of an application, in the order it lists them: each code once, each rank of a series once, and the
 * paths of their criteria at most 64 bytes long.
 */
final readonly class Catalog
{
    public const int MAX_PATH_LENGTH = 64;

    /**
     * @var array<string, Badge>
     */
    private array $badges;

    /**
     * @var array<string, array<string, Leaf>>
     */
    private array $leaves;

    /**
     * @var array<string, array<int, Badge>>
     */
    private array $series;

    /**
     * @param list<Badge> $badges
     */
    public function __construct(array $badges = [])
    {
        $byCode = [];
        $leaves = [];
        $tiers = [];

        foreach ($badges as $badge) {
            if (isset($byCode[$badge->code])) {
                throw InvalidBadge::because(\sprintf('The badge "%s" is in the catalog twice.', $badge->code));
            }

            if ($badge->tier instanceof Tier) {
                $holder = $tiers[$badge->tier->series][$badge->tier->rank] ?? null;

                if ($holder instanceof Badge) {
                    throw InvalidBadge::because(\sprintf('The badges "%s" and "%s" hold the same rank %d of the series "%s".', $holder->code, $badge->code, $badge->tier->rank, $badge->tier->series));
                }

                $tiers[$badge->tier->series][$badge->tier->rank] = $badge;
            }

            $leaves[$badge->code] = $badge->criterion->leaves(Badge::ROOT);

            foreach (array_keys($leaves[$badge->code]) as $path) {
                if (\strlen($path) > self::MAX_PATH_LENGTH) {
                    throw InvalidBadge::because(\sprintf('The criterion of the badge "%s" nests too deep: the path "%s" is longer than %d bytes.', $badge->code, $path, self::MAX_PATH_LENGTH));
                }
            }

            $byCode[$badge->code] = $badge;
        }

        $this->badges = $byCode;
        $this->leaves = $leaves;
        $this->series = $tiers;
    }

    /**
     * The catalog from plain values, as a configuration holds them: by code, a name, a description, an image, whether
     * the badge is repeatable, hidden or revocable, its tier, and its criterion as `CriterionReader` reads it.
     *
     * @param array<array-key, mixed> $badges
     */
    public static function fromArray(array $badges, string $timezone = 'UTC'): self
    {
        try {
            $reader = new CriterionReader(new DateTimeZone($timezone));
        } catch (Throwable) {
            throw InvalidBadge::because(\sprintf('"%s" is not a timezone.', $timezone));
        }

        $list = [];

        foreach ($badges as $code => $values) {
            if (!\is_string($code) || !\is_array($values)) {
                throw InvalidBadge::because('The badges of a catalog are values by code.');
            }

            $criterion = $values['criterion'] ?? null;
            $tier = $values['tier'] ?? null;

            if (!\is_array($criterion)) {
                throw InvalidBadge::because(\sprintf('The badge "%s" has a criterion.', $code));
            }

            $list[] = new Badge(
                $code,
                self::text($values, 'name', $code) ?? $code,
                $reader->read($criterion),
                self::text($values, 'description', $code) ?? '',
                self::text($values, 'image', $code),
                true === ($values['repeatable'] ?? false),
                true === ($values['hidden'] ?? false),
                true === ($values['revocable'] ?? false),
                \is_array($tier) ? new Tier(self::text($tier, 'series', $code) ?? '', \is_int($tier['rank'] ?? null) ? $tier['rank'] : 0) : null,
            );
        }

        return new self($list);
    }

    /**
     * @return list<Badge>
     */
    public function all(): array
    {
        return array_values($this->badges);
    }

    public function has(string $code): bool
    {
        return isset($this->badges[$code]);
    }

    /**
     * @throws UnknownBadge
     */
    public function get(string $code): Badge
    {
        return $this->badges[$code] ?? throw UnknownBadge::named($code);
    }

    /**
     * The leaves of a badge's criterion, by path.
     *
     * @return array<string, Leaf>
     */
    public function leaves(Badge $badge): array
    {
        return $this->leaves[$badge->code] ?? [];
    }

    /**
     * The badges of a series, by rank.
     *
     * @return list<Badge>
     */
    public function series(string $series): array
    {
        $badges = $this->series[$series] ?? [];
        ksort($badges);

        return array_values($badges);
    }

    /**
     * Every verb pattern a leaf reads, each once.
     *
     * @return list<string>
     */
    public function patterns(): array
    {
        $patterns = [];

        foreach ($this->leaves as $leaves) {
            foreach ($leaves as $leaf) {
                array_push($patterns, ...$leaf->patterns());
            }
        }

        return array_values(array_unique($patterns));
    }

    /**
     * Whether a leaf reads a measure the application provides.
     */
    public function measures(): bool
    {
        return array_any($this->leaves, static fn (array $leaves): bool => array_any($leaves, static fn (Leaf $leaf): bool => $leaf instanceof Measure));
    }

    /**
     * @param array<array-key, mixed> $values
     */
    private static function text(array $values, string $key, string $code): ?string
    {
        $value = $values[$key] ?? null;

        if (null !== $value && !\is_string($value)) {
            throw InvalidBadge::because(\sprintf('The "%s" of the badge "%s" is a string.', $key, $code));
        }

        return $value;
    }
}
