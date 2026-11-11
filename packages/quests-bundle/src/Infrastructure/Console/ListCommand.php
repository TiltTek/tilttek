<?php

declare(strict_types=1);

namespace Tilttek\QuestsBundle\Infrastructure\Console;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Tilttek\Quests\Catalog;
use Tilttek\Quests\Quest;
use Tilttek\Quests\Reward;
use Tilttek\Quests\Step;

/**
 * The quests of the catalog, with their kind, steps, chain and rewards: `tilttek:quests:list`.
 */
#[AsCommand('tilttek:quests:list', 'Lists the quests of the catalog.')]
final readonly class ListCommand
{
    public function __construct(
        private Catalog $catalog,
    ) {
    }

    public function __invoke(SymfonyStyle $io): int
    {
        $io->table(['Quest', 'Kind', 'Steps', 'After', 'Rewards'], array_map(
            static fn (Quest $quest): array => [
                $quest->code,
                $quest->kind->value.($quest->needsAcceptance() ? ', accepted' : ''),
                implode($quest->ordered ? ' → ' : ', ', array_map(static fn (Step $step): string => \sprintf('%s %s', $step->code, $step->target), $quest->steps)),
                $quest->after ?? '—',
                implode(', ', array_map(static fn (Reward $reward): string => \sprintf('%s %s ×%d', $reward->kind, $reward->code, $reward->amount), $quest->rewards)),
            ],
            $this->catalog->all(),
        ));

        return Command::SUCCESS;
    }
}
