<?php

declare(strict_types=1);

namespace Tilttek\BadgesBundle\Infrastructure\Console;

use Phil\Resource\InvalidResource;
use Phil\Resource\ResourceReference;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Tilttek\Badges\Badges;
use Tilttek\Badges\Standing;

/**
 * Where a member stands with every badge — earned, how many times, the progress towards the others —, hidden badges
 * they have not earned left out: `tilttek:badges:list member:44`.
 */
#[AsCommand('tilttek:badges:list', "Lists a member's badges and their progress towards the others.")]
final readonly class ListCommand
{
    public function __construct(
        private Badges $badges,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Argument('The member, as kind:identifier.')]
        string $member,
    ): int {
        try {
            $standings = $this->badges->overview(ResourceReference::fromString($member));
        } catch (InvalidResource $invalidResource) {
            $io->error($invalidResource->getMessage());

            return Command::FAILURE;
        }

        $io->table(['Badge', 'Awards', 'Progress'], array_map(
            static fn (Standing $standing): array => [$standing->badge->code, $standing->awards, \sprintf('%d / %d', $standing->progress->shown(), $standing->progress->target)],
            $standings,
        ));

        return Command::SUCCESS;
    }
}
