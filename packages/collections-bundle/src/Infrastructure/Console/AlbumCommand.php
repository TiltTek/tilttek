<?php

declare(strict_types=1);

namespace Tilttek\CollectionsBundle\Infrastructure\Console;

use Phil\Resource\InvalidResource;
use Phil\Resource\ResourceReference;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Tilttek\Collections\Collections;
use Tilttek\Collections\Holding;
use Tilttek\Collections\SetProgress;

/**
 * A member's album: the progress of each set, then the items they own with their doubles —
 * `tilttek:collections:album member:44`.
 */
#[AsCommand('tilttek:collections:album', "Shows a member's album.")]
final readonly class AlbumCommand
{
    public function __construct(
        private Collections $collections,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Argument('The member, as kind:identifier.')]
        string $member,
    ): int {
        try {
            $album = $this->collections->album(ResourceReference::fromString($member));
        } catch (InvalidResource $invalidResource) {
            $io->error($invalidResource->getMessage());

            return Command::FAILURE;
        }

        $io->table(['Set', 'Owned', 'Completed once'], array_map(
            static fn (SetProgress $progress): array => [$progress->set->code, \sprintf('%d / %d', \count($progress->owned), $progress->total()), $progress->completedOnce ? 'yes' : 'no'],
            $album->sets,
        ));
        $io->table(['Item', 'Copies', 'Doubles'], array_map(
            static fn (Holding $holding): array => [$holding->item->code, $holding->count, $holding->doubles()],
            $album->owned(),
        ));

        return Command::SUCCESS;
    }
}
