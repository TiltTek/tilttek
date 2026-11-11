<?php

declare(strict_types=1);

namespace Tilttek\CollectionsBundle\Infrastructure\Console;

use Phil\Resource\InvalidResource;
use Phil\Resource\ResourceReference;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Tilttek\Collections\Collections;
use Tilttek\Collections\Exception\CollectionsException;
use Tilttek\Collections\Source;

/**
 * Gives a member a copy of an item under a key, for the site's own reasons — a contest won, a copy restored:
 * `tilttek:collections:grant member:44 pirate --key=contest:2026-11`. The same key given again gives nothing more.
 */
#[AsCommand('tilttek:collections:grant', 'Gives a member a copy of an item.')]
final readonly class GrantCommand
{
    public function __construct(
        private Collections $collections,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Argument('The member, as kind:identifier.')]
        string $member,
        #[Argument('The code of the item.')]
        string $item,
        #[Option('The key that makes the copy unique.')]
        string $key = '',
        #[Option('Why it is given: granted, reward, gift, purchase…')]
        string $source = 'granted',
        #[Option('Binds the copy to its member.')]
        bool $bound = false,
    ): int {
        $why = Source::tryFrom($source);

        if (!$why instanceof Source) {
            $io->error(\sprintf('"%s" is not a source: %s.', $source, implode(', ', array_map(static fn (Source $one): string => $one->value, Source::cases()))));

            return Command::FAILURE;
        }

        try {
            $copy = $this->collections->grant(ResourceReference::fromString($member), $item, $why, $key, tradable: !$bound);
        } catch (CollectionsException|InvalidResource $exception) {
            $io->error($exception->getMessage());

            return Command::FAILURE;
        }

        $io->success(\sprintf('%s owns the copy %s of %s.', $copy->owner->toString(), $copy->id, $copy->item));

        return Command::SUCCESS;
    }
}
