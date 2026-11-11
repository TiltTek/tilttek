<?php

declare(strict_types=1);

namespace Tilttek\CollectionsBundle\Infrastructure\Console;

use DateTimeImmutable;
use Psr\Clock\ClockInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Tilttek\Collections\Daily\DailyCalendar;
use Tilttek\Collections\Daily\DailyPick;
use Tilttek\Collections\Exception\CollectionsException;

/**
 * Generates the calendar of the item of the day and shows it, so that the site can review it and correct a day:
 * `tilttek:collections:daily --from=2026-12-01 --until=2026-12-31`; thirty days from today when no day is given. A day
 * already generated keeps its item unless `--regenerate`; a day the site chose always does.
 */
#[AsCommand('tilttek:collections:daily', 'Generates and shows the calendar of the item of the day.')]
final readonly class DailyCommand
{
    public function __construct(
        private DailyCalendar $calendar,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Option('The first day, written Y-m-d; today when omitted.')]
        ?string $from = null,
        #[Option('The last day, written Y-m-d; thirty days after the first when omitted.')]
        ?string $until = null,
        #[Option('Computes again the days already generated.')]
        bool $regenerate = false,
    ): int {
        $from ??= $this->calendar->dayOf($this->clock->now());
        $until ??= new DateTimeImmutable($from)->modify('+29 days')->format('Y-m-d');

        try {
            $picks = $this->calendar->generate($from, $until, $regenerate);
        } catch (CollectionsException $collectionsException) {
            $io->error($collectionsException->getMessage());

            return Command::FAILURE;
        }

        $io->table(['Day', 'Item', 'Why'], array_map(static fn (DailyPick $pick): array => [$pick->day, $pick->item, $pick->origin->value], $picks));

        return Command::SUCCESS;
    }
}
