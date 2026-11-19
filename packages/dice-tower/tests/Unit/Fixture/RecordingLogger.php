<?php

declare(strict_types=1);

namespace Tilttek\DiceTower\Tests\Unit\Fixture;

use Override;
use Psr\Log\AbstractLogger;
use Stringable;

/**
 * A logger that keeps each record, its placeholders filled.
 */
final class RecordingLogger extends AbstractLogger
{
    /** @var list<array{mixed, string, array<array-key, mixed>}> */
    public array $records = [];

    /**
     * @param array<array-key, mixed> $context
     */
    #[Override]
    public function log(mixed $level, string|Stringable $message, array $context = []): void
    {
        $replacements = [];

        foreach ($context as $key => $value) {
            if (\is_scalar($value)) {
                $replacements['{'.$key.'}'] = (string) $value;
            }
        }

        $this->records[] = [$level, strtr((string) $message, $replacements), $context];
    }
}
