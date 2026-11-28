<?php

declare(strict_types=1);

namespace Tilttek\SetsBundle\Infrastructure\Console;

use Phil\Resource\InvalidResource;
use Phil\Resource\ResourceReference;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Throwable;
use Tilttek\Sets\Exception\DeliveryFailed;
use Tilttek\Sets\Exception\InvalidSet;
use Tilttek\Sets\Identifiers;
use Tilttek\Sets\Sets;
use Tilttek\SetsBundle\Infrastructure\Messenger\DeliverPendingCompletions;

/**
 * Tells again the completions whose event was not delivered: those of the holders waiting the longest, at most
 * `--limit` holders, or every one of the holder `--holder` names. It succeeds when every completion told was
 * delivered, and fails, saying how many were, when a handler threw.
 */
#[AsCommand(self::NAME, 'Tells again the completions of the sets whose event was not delivered')]
final readonly class DeliverCommand
{
    public const string NAME = 'tilttek:sets:deliver';

    public function __construct(
        private Sets $sets,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Option('Only the completions of this holder, such as member:1042')]
        ?string $holder = null,
        #[Option('The most holders told, the one waiting the longest first')]
        int $limit = DeliverPendingCompletions::LIMIT,
    ): int {
        return null === $holder ? $this->pending($io, $limit) : $this->holder($io, $holder);
    }

    private function pending(SymfonyStyle $io, int $limit): int
    {
        try {
            $delivered = $this->sets->deliverPending($limit);
        } catch (InvalidSet $invalid) {
            $io->error($invalid->getMessage());

            return Command::FAILURE;
        } catch (DeliveryFailed $failed) {
            $io->error([$failed->getMessage(), $this->describe($failed->getPrevious() ?? $failed)]);

            return Command::FAILURE;
        }

        $io->success(\sprintf('%d completions delivered.', $delivered));

        return Command::SUCCESS;
    }

    private function holder(SymfonyStyle $io, string $holder): int
    {
        try {
            $reference = Identifiers::holder(ResourceReference::fromString($holder));
        } catch (InvalidResource|InvalidSet $invalid) {
            $io->error($invalid->getMessage());

            return Command::FAILURE;
        }

        try {
            $delivered = $this->sets->deliver($reference);
        } catch (Throwable $throwable) {
            $io->error(['A handler threw: the completions it was told stay undelivered.', $this->describe($throwable)]);

            return Command::FAILURE;
        }

        $io->success(\sprintf('%d completions delivered to %s.', $delivered, $reference->toString()));

        return Command::SUCCESS;
    }

    private function describe(Throwable $thrown): string
    {
        return $thrown::class.': '.$thrown->getMessage();
    }
}
