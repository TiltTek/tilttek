<?php

declare(strict_types=1);

namespace Tilttek\Badges;

use DateTimeImmutable;
use LogicException;
use Override;
use Phil\Resource\ResourceReference;
use Tilttek\Badges\Criterion\Reading;
use Tilttek\Badges\Exception\InvalidBadge;
use Tilttek\Badges\Store\ProgressStore;

/**
 * Reads a member's progress towards a badge from the contributions kept, and the measures of the application.
 */
final readonly class Evaluator
{
    public function __construct(
        private Catalog $catalog,
        private ProgressStore $progress,
        private ?Measures $measures = null,
    ) {
        if ($catalog->measures() && !$measures instanceof Measures) {
            throw InvalidBadge::because('A criterion of the catalog reads a measure, and no measures are provided.');
        }
    }

    public function catalog(): Catalog
    {
        return $this->catalog;
    }

    public function progress(Badge $badge, ResourceReference $member): Progress
    {
        $store = $this->progress;
        $measures = $this->measures;

        return $badge->criterion->progress(Badge::ROOT, new readonly class($badge->code, $member, $store, $measures) implements Reading {
            public function __construct(
                private string $badge,
                private ResourceReference $member,
                private ProgressStore $store,
                private ?Measures $measures,
            ) {
            }

            #[Override]
            public function count(string $path): int
            {
                return $this->store->count($this->badge, $path, $this->member);
            }

            #[Override]
            public function quantity(string $path): int
            {
                return $this->store->quantity($this->badge, $path, $this->member);
            }

            /**
             * @return list<DateTimeImmutable>
             */
            #[Override]
            public function moments(string $path): array
            {
                return $this->store->moments($this->badge, $path, $this->member);
            }

            #[Override]
            public function measure(string $name): int
            {
                if (!$this->measures instanceof Measures) {
                    throw new LogicException(\sprintf('The measure "%s" is read, and no measures are provided.', $name));
                }

                return $this->measures->value($name, $this->member);
            }
        });
    }
}
