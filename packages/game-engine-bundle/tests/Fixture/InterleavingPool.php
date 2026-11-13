<?php

declare(strict_types=1);

namespace Tilttek\GameEngineBundle\Tests\Fixture;

use Closure;
use Override;
use Psr\Cache\CacheItemInterface;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

/**
 * An array pool that runs another process's write before its next save — between the read and the write of the
 * process saving — and records the revision of every snapshot it saves, in order.
 */
final class InterleavingPool extends ArrayAdapter
{
    public ?Closure $beforeSave = null;

    /** @var list<int> */
    public array $saved = [];

    #[Override]
    public function save(CacheItemInterface $item): bool
    {
        $before = $this->beforeSave;
        $this->beforeSave = null;

        if ($before instanceof Closure) {
            $before();
        }

        $value = $item->get();
        $data = \is_string($value) ? json_decode($value, true) : null;
        $this->saved[] = \is_array($data) && \is_int($data['revision'] ?? null) ? $data['revision'] : -1;

        return parent::save($item);
    }
}
