<?php

declare(strict_types=1);

namespace Tilttek\DiceBundle\Infrastructure\Validator;

use Override;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;
use Tilttek\Dice\Exception\InvalidExpression;
use Tilttek\Dice\Exception\LimitExceeded;
use Tilttek\DiceBundle\Application\DiceRolls;

/**
 * Checks a `DiceExpression` by parsing the value; `null` and the empty string pass, as with every constraint but
 * `NotBlank`.
 */
final class DiceExpressionValidator extends ConstraintValidator
{
    public function __construct(
        private readonly DiceRolls $dice,
    ) {
    }

    #[Override]
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof DiceExpression) {
            throw new UnexpectedTypeException($constraint, DiceExpression::class);
        }

        if (null === $value || '' === $value) {
            return;
        }

        if (!\is_string($value)) {
            throw new UnexpectedValueException($value, 'string');
        }

        try {
            $this->dice->parse($value, $constraint->locale);
        } catch (InvalidExpression|LimitExceeded $exception) {
            $this->context->buildViolation($constraint->message)
                ->setParameter('{{ reason }}', $exception->getMessage())
                ->setCode(DiceExpression::INVALID)
                ->addViolation();
        }
    }
}
