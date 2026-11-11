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
use Tilttek\Badges\Exception\BadgesException;

/**
 * Withdraws the awards of a badge made by hand: `tilttek:badges:revoke member:44 organiser`.
 */
#[AsCommand('tilttek:badges:revoke', 'Withdraws the awards of a badge made by hand.')]
final readonly class RevokeCommand
{
    public function __construct(
        private Badges $badges,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Argument('The member, as kind:identifier.')]
        string $member,
        #[Argument('The code of the badge.')]
        string $badge,
    ): int {
        try {
            $revoked = $this->badges->revoke(ResourceReference::fromString($member), $badge);
        } catch (InvalidResource|BadgesException $exception) {
            $io->error($exception->getMessage());

            return Command::FAILURE;
        }

        $io->success(\sprintf('%d award(s) of "%s" withdrawn from %s.', $revoked, $badge, $member));

        return Command::SUCCESS;
    }
}
