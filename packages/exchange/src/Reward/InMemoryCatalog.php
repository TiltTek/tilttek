<?php

declare(strict_types=1);

namespace Tilttek\Exchange\Reward;

use Override;
use Tilttek\Exchange\Exception\UnknownReward;

/**
 * A catalogue in memory, built from a configuration or for tests.
 */
final class InMemoryCatalog implements WritableCatalog
{
    /**
     * @var array<string, Reward>
     */
    private array $rewards = [];

    public function __construct(Reward ...$rewards)
    {
        foreach ($rewards as $reward) {
            $this->save($reward);
        }
    }

    /**
     * The catalogue from a configuration: the rewards by code, each as `Reward::fromArray()` reads it.
     *
     * @param array<string, array{price: int, currency?: ?string, stock?: ?int, available_from?: ?string, available_until?: ?string, per_member?: ?int, requires?: list<string>}> $rewards
     */
    public static function fromArray(array $rewards): self
    {
        return new self(...array_map(Reward::fromArray(...), array_keys($rewards), $rewards));
    }

    #[Override]
    public function get(string $code): Reward
    {
        return $this->rewards[$code] ?? throw UnknownReward::named($code);
    }

    #[Override]
    public function all(): array
    {
        $rewards = $this->rewards;
        ksort($rewards, \SORT_STRING);

        return array_values($rewards);
    }

    #[Override]
    public function save(Reward $reward): void
    {
        $this->rewards[$reward->code] = $reward;
    }

    #[Override]
    public function remove(string $code): void
    {
        unset($this->rewards[$code]);
    }
}
