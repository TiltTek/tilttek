<?php

declare(strict_types=1);

namespace Tilttek\TableSafetyBundle\Infrastructure\Console;

use Psr\Clock\ClockInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Tilttek\TableSafety\Contract\SignalStore;

/**
 * Deletes the signals older than the retention the configuration sets, or than the one given: a signal serves during
 * play, and is not kept beyond it.
 */
#[AsCommand(name: 'tilttek:table-safety:purge-signals', description: 'Deletes the signals older than the retention')]
final readonly class PurgeSignalsCommand
{
    /** A retention: a number and a unit, such as "30 days". */
    public const string RETENTION = '/^[1-9]\d{0,4} (?:minute|hour|day|week|month)s?$/D';

    public function __construct(
        private SignalStore $signals,
        private ClockInterface $clock,
        private string $retention,
    ) {
    }

    public function __invoke(SymfonyStyle $io, #[Option('How long the signals are kept, such as "30 days"')] ?string $retention = null): int
    {
        $retention ??= $this->retention;

        if (1 !== preg_match(self::RETENTION, $retention)) {
            $io->error('The retention is a number and a unit, such as "30 days".');

            return Command::INVALID;
        }

        $before = $this->clock->now()->modify('-'.$retention);
        $io->success(\sprintf('%d signals raised before %s deleted.', $this->signals->purge($before), $before->format(\DATE_ATOM)));

        return Command::SUCCESS;
    }
}
