<?php

declare(strict_types=1);

namespace Tilttek\EscapeRoomBundle\Infrastructure\Console;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Tilttek\EscapeRoom\Exception\InvalidRoom;
use Tilttek\EscapeRoomBundle\Application\GameDesk;

/**
 * `tilttek:escape-room:expire`: ends the games whose countdown reached zero, as the schedule does.
 */
#[AsCommand(name: 'tilttek:escape-room:expire', description: 'Ends the games whose countdown reached zero')]
final readonly class ExpireCommand
{
    public function __construct(
        private GameDesk $desk,
    ) {
    }

    public function __invoke(SymfonyStyle $io, #[Option(description: 'How many games at most, 1 to 1000')] int $limit = 100): int
    {
        try {
            $ended = $this->desk->expireDue($limit);
        } catch (InvalidRoom $invalidRoom) {
            $io->error($invalidRoom->getMessage());

            return Command::INVALID;
        }

        $io->success(\sprintf('%d game(s) ended.', \count($ended)));

        return Command::SUCCESS;
    }
}
