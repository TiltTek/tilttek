<?php

declare(strict_types=1);

namespace Tilttek\DiceBundle\Infrastructure\Validator;

use Attribute;
use Override;
use Symfony\Component\Validator\Constraint;

/**
 * A dice expression that parses, within the limits, in the notation of a language — or the canonical one when no
 * locale is given. The violation says why, with the position of the error.
 */
#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_METHOD)]
final class DiceExpression extends Constraint
{
    public const string INVALID = 'tilttek_dice.invalid';

    public string $message = 'This is not a dice expression: {{ reason }}';

    /**
     * @param list<string>|null $groups
     */
    public function __construct(
        public ?string $locale = null,
        ?string $message = null,
        ?array $groups = null,
        mixed $payload = null,
    ) {
        parent::__construct(null, $groups, $payload);
        $this->message = $message ?? $this->message;
    }

    #[Override]
    public function validatedBy(): string
    {
        return DiceExpressionValidator::class;
    }
}
