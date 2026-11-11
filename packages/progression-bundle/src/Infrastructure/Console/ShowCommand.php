<?php

declare(strict_types=1);

namespace Tilttek\ProgressionBundle\Infrastructure\Console;

use Phil\Resource\InvalidResource;
use Phil\Resource\ResourceReference;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Tilttek\Progression\Progression;
use Tilttek\Progression\Standing;
use Tilttek\Progression\Streak\StreakStatus;

/**
 * Where a member — or a character — stands on every track, and with every streak: `tilttek:progression:show member:44`.
 */
#[AsCommand('tilttek:progression:show', 'Shows where a subject stands on every track and with every streak.')]
final readonly class ShowCommand
{
    public function __construct(
        private Progression $progression,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Argument('The subject, as kind:identifier.')]
        string $subject,
    ): int {
        try {
            $reference = ResourceReference::fromString($subject);
        } catch (InvalidResource $invalidResource) {
            $io->error($invalidResource->getMessage());

            return Command::FAILURE;
        }

        $io->table(['Track', 'Season', 'Level', 'Experience', 'Next level at', 'Lifetime'], array_map(
            static fn (Standing $standing): array => [$standing->track->name, $standing->season->code ?? '—', $standing->level->number, $standing->level->experience, $standing->level->next ?? '—', $standing->lifetime],
            $this->progression->standings($reference),
        ));
        $io->table(['Streak', 'Current', 'Best', 'Grace left'], array_map(
            static fn (StreakStatus $status): array => [$status->streak, $status->current, $status->best, $status->graceLeft],
            $this->progression->streaks($reference),
        ));

        return Command::SUCCESS;
    }
}
