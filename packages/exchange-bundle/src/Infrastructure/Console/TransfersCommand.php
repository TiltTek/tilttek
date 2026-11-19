<?php

declare(strict_types=1);

namespace Tilttek\ExchangeBundle\Infrastructure\Console;

use Phil\Resource\InvalidResource;
use Phil\Resource\ResourceReference;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Tilttek\Exchange\Transfer\Transfer;
use Tilttek\Exchange\Transfer\Transfers;

/**
 * Shows the journal of transfers between members: the latest of everyone, or every transfer a member gave or
 * received.
 */
#[AsCommand('tilttek:exchange:transfers', 'Shows the journal of the transfers of points between members')]
final readonly class TransfersCommand
{
    public function __construct(
        private Transfers $transfers,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Argument('The member, as a reference such as "member:44"; every member when left out')]
        ?string $member = null,
        #[Option('How many of the latest transfers to show when no member is named, 50 unless asked, at most 1000')]
        ?int $limit = null,
    ): int {
        try {
            $transfers = match (true) {
                null !== $member => $this->transfers->ofMember(ResourceReference::fromString($member)),
                null !== $limit => $this->transfers->journal($limit),
                default => $this->transfers->journal(),
            };
        } catch (InvalidResource $invalidResource) {
            $io->error($invalidResource->getMessage());

            return Command::FAILURE;
        }

        if ([] === $transfers) {
            $io->writeln('No transfer.');

            return Command::SUCCESS;
        }

        $io->table(
            ['Moment', 'Key', 'From', 'To', 'Amount', 'Currency'],
            array_map(static fn (Transfer $transfer): array => [$transfer->at->format('Y-m-d H:i:s'), $transfer->key->value, $transfer->from->toString(), $transfer->to->toString(), $transfer->amount, $transfer->currency], $transfers),
        );

        return Command::SUCCESS;
    }
}
