<?php

declare(strict_types=1);

namespace Tilttek\QuestsBundle\Infrastructure\Console;

use Phil\Resource\InvalidResource;
use Phil\Resource\ResourceReference;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Tilttek\Quests\Progress\StepProgress;
use Tilttek\Quests\Quests;
use Tilttek\Quests\QuestStatus;

/**
 * Where every quest stands for a member, step by step: `tilttek:quests:show member:44`.
 */
#[AsCommand('tilttek:quests:show', 'Shows where every quest stands for a member.')]
final readonly class ShowCommand
{
    public function __construct(
        private Quests $quests,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Argument('The member, as kind:identifier.')]
        string $member,
    ): int {
        try {
            $reference = ResourceReference::fromString($member);
        } catch (InvalidResource $invalidResource) {
            $io->error($invalidResource->getMessage());

            return Command::FAILURE;
        }

        $io->table(['Quest', 'State', 'Steps', 'Team'], array_map(
            static fn (QuestStatus $status): array => [
                $status->quest->code,
                $status->state->value,
                implode(', ', array_map(static fn (StepProgress $step): string => \sprintf('%s %d/%d', $step->step->code, $step->current(), $step->step->target), $status->progress->steps)),
                $status->team?->toString() ?? '—',
            ],
            $this->quests->board($reference),
        ));

        return Command::SUCCESS;
    }
}
