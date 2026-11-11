<?php

declare(strict_types=1);

namespace Tilttek\ActivityBundle\Infrastructure\Console;

use DateMalformedStringException;
use DateTimeImmutable;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Tilttek\Activity\Exception\UnknownProjection;
use Tilttek\Activity\Projection\Projections;
use Tilttek\Activity\Projection\Projector;

/**
 * Replays the journal into a projection after a rule changed: the projection forgets what it derived from the period
 * and reads the activity of that period again — the whole journal without `--since`.
 */
#[AsCommand('tilttek:activity:rebuild', 'Replays the activity into a projection after a rule changed')]
final readonly class RebuildCommand
{
    public function __construct(
        private Projector $projector,
        private Projections $projections,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Argument('The projection to rebuild')]
        string $projection,
        #[Option('Replay only the activity from this date on, such as 2026-11-01')]
        ?string $since = null,
    ): int {
        try {
            $rebuilt = $this->projections->get($projection);
            $from = null === $since ? null : new DateTimeImmutable($since);
        } catch (UnknownProjection|DateMalformedStringException $invalid) {
            $io->error($invalid->getMessage());

            return Command::FAILURE;
        }

        $io->success(\sprintf('%s: %d entries applied.', $rebuilt->name(), $this->projector->rebuild($rebuilt, $from)));

        return Command::SUCCESS;
    }
}
