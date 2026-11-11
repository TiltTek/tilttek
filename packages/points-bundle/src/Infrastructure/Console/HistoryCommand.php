<?php

declare(strict_types=1);

namespace Tilttek\PointsBundle\Infrastructure\Console;

use Phil\Resource\InvalidResource;
use Phil\Resource\ResourceReference;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Tilttek\Points\Account;
use Tilttek\Points\Exception\PointsException;
use Tilttek\Points\Points;

/**
 * Shows a member's balance and their latest movements: what support reads when a member asks where their points went.
 */
#[AsCommand('tilttek:points:history', 'Shows the balance and the movements of a member')]
final readonly class HistoryCommand
{
    public function __construct(
        private Points $points,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Argument('The member, as a reference such as "member:44"')]
        string $member,
        #[Option('The currency; the default one when none is named')]
        ?string $currency = null,
        #[Option('Show the movements after this position')]
        int $after = 0,
        #[Option('How many movements to show, at most 1000')]
        int $limit = 50,
    ): int {
        try {
            $reference = ResourceReference::fromString($member);
            $balance = $this->points->balance($reference, $currency);
            $entries = $this->points->history($reference, $currency, $after, $limit);
        } catch (InvalidResource|PointsException $exception) {
            $io->error($exception->getMessage());

            return Command::FAILURE;
        }

        $io->writeln(\sprintf('Balance of %s: %d.', $reference->toString(), $balance));
        $rows = [];

        foreach ($entries as $entry) {
            $movement = $entry->movement;
            $rows[] = [
                $entry->position,
                $movement->occurredAt->format('Y-m-d H:i:s'),
                $movement->kind->value,
                \sprintf('%+d', $movement->amountFor(Account::member($reference))),
                $movement->currency,
                $movement->key->value,
            ];
        }

        $io->table(['Position', 'Moment', 'Kind', 'Points', 'Currency', 'Key'], $rows);

        return Command::SUCCESS;
    }
}
