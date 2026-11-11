<?php

declare(strict_types=1);

namespace Tilttek\BadgesBundle\Infrastructure\Console;

use Phil\Resource\InvalidResource;
use Phil\Resource\ResourceReference;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Tilttek\Badges\Badges;
use Tilttek\Badges\Exception\BadgesException;

/**
 * Awards a badge by hand: `tilttek:badges:award member:44 organiser --reason="Ran the convention"`.
 */
#[AsCommand('tilttek:badges:award', 'Awards a badge to a member by hand.')]
final readonly class AwardCommand
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
        #[Option('Why the badge is awarded.')]
        ?string $reason = null,
    ): int {
        try {
            $award = $this->badges->award(ResourceReference::fromString($member), $badge, $reason);
        } catch (InvalidResource|BadgesException $exception) {
            $io->error($exception->getMessage());

            return Command::FAILURE;
        }

        $io->success(\sprintf('%s holds the badge "%s" (#%d).', $member, $badge, $award->number));

        return Command::SUCCESS;
    }
}
