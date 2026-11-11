<?php

declare(strict_types=1);

namespace Tilttek\Dice\Dialect;

use Tilttek\Dice\Exception\UntranslatableExpression;

/**
 * A pool written in a dialect: one command per expression, since a table rolls each with its own total, the
 * constructs it could not write, and the notices of what the table rolls differently — a division the roll rounds
 * another way than down. An incomplete translation gives no text: a construct is reported rather than written wrong.
 */
final readonly class Translation
{
    /**
     * @param list<string>      $expressions the expressions in the dialect, without the command
     * @param list<Unsupported> $unsupported
     * @param list<string>      $notices     what the table rolls differently, in English
     */
    public function __construct(
        public string $dialect,
        public string $command,
        public array $expressions,
        public array $unsupported,
        public array $notices = [],
    ) {
    }

    public function isComplete(): bool
    {
        return [] === $this->unsupported;
    }

    /**
     * Each expression after the dialect's roll command, ready to paste in the table's chat.
     *
     * @return list<string>
     *
     * @throws UntranslatableExpression when a construct could not be written
     */
    public function commands(): array
    {
        if (!$this->isComplete()) {
            throw UntranslatableExpression::in($this->dialect, array_map(static fn (Unsupported $unsupported): string => $unsupported->construct, $this->unsupported));
        }

        return array_map(fn (string $expression): string => $this->command.$expression, $this->expressions);
    }

    /**
     * The commands, one per line.
     *
     * @throws UntranslatableExpression when a construct could not be written
     */
    public function text(): string
    {
        return implode("\n", $this->commands());
    }
}
