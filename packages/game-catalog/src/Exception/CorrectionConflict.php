<?php

declare(strict_types=1);

namespace Tilttek\GameCatalog\Exception;

use RuntimeException;
use Tilttek\GameCatalog\Field;

/**
 * A field the correction changes was changed on the entry since the member proposed it: accepting it would overwrite
 * that change unseen. The administration reads both and accepts with `force`, or refuses the correction.
 */
final class CorrectionConflict extends RuntimeException implements CatalogException
{
    /**
     * @param non-empty-list<Field> $fields
     */
    public function __construct(string $message, public readonly array $fields)
    {
        parent::__construct($message);
    }

    /**
     * @param non-empty-list<Field> $fields
     */
    public static function on(string $correction, array $fields): self
    {
        return new self(\sprintf(
            'The correction %s changes %s, changed on the entry since it was proposed.',
            $correction,
            implode(', ', array_map(static fn (Field $field): string => $field->value, $fields)),
        ), $fields);
    }
}
