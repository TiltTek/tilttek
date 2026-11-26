<?php

declare(strict_types=1);

namespace Tilttek\SessionPrep\Store;

use Override;
use Tilttek\SessionPrep\Contract\PreparationStore;
use Tilttek\SessionPrep\Exception\InvalidPreparation;
use Tilttek\SessionPrep\Exception\PreparationConflict;
use Tilttek\SessionPrep\Preparation;
use Tilttek\SessionPrep\PreparationQuery;

/**
 * The preparations in memory, for tests: they last as long as the object.
 */
final class InMemoryPreparationStore implements PreparationStore
{
    /** @var array<string, Preparation> by identifier */
    private array $preparations = [];

    #[Override]
    public function preparation(string $id): ?Preparation
    {
        return $this->preparations[$id] ?? null;
    }

    #[Override]
    public function save(Preparation $preparation): Preparation
    {
        if ($preparation->version !== ($this->preparations[$preparation->id]->version ?? 0)) {
            throw PreparationConflict::changed($preparation->id);
        }

        return $this->preparations[$preparation->id] = $preparation->withVersion($preparation->version + 1);
    }

    #[Override]
    public function delete(Preparation $preparation): void
    {
        if ($preparation->version !== ($this->preparations[$preparation->id]->version ?? null)) {
            throw PreparationConflict::changed($preparation->id);
        }

        unset($this->preparations[$preparation->id]);
    }

    #[Override]
    public function preparations(PreparationQuery $query, int $limit): iterable
    {
        if ($limit < 1) {
            throw InvalidPreparation::because('A store reads at least one preparation at once.');
        }

        $preparations = array_filter($this->preparations, $query->matches(...));
        ksort($preparations);

        return \array_slice(array_values($preparations), 0, $limit);
    }
}
