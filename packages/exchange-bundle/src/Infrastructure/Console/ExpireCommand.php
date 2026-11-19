<?php

declare(strict_types=1);

namespace Tilttek\ExchangeBundle\Infrastructure\Console;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Tilttek\Exchange\Reservation\Redemptions;
use Tilttek\Exchange\Trade\Trades;

/**
 * Expires the reservations left unconfirmed past their lifetime, giving their points back, and the offers left
 * unanswered past theirs, freeing their items: the task a scheduler runs.
 */
#[AsCommand('tilttek:exchange:expire', 'Expires the reservations and the offers whose lifetime has ended')]
final readonly class ExpireCommand
{
    public function __construct(
        private Redemptions $redemptions,
        private ?Trades $trades = null,
    ) {
    }

    public function __invoke(SymfonyStyle $io): int
    {
        $reservations = $this->redemptions->expireDue();
        $offers = $this->trades?->expireDue() ?? 0;
        $io->writeln(\sprintf('%d reservations and %d offers expired.', $reservations, $offers));

        return Command::SUCCESS;
    }
}
