<?php

declare(strict_types=1);

namespace Tilttek\DiceBundle\Tests\Unit\Infrastructure;

use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use stdClass;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\ConstraintValidatorInterface;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;
use Tilttek\Dice\DiceRoller;
use Tilttek\Dice\Limits;
use Tilttek\DiceBundle\Application\DiceRolls;
use Tilttek\DiceBundle\Infrastructure\Memory\InMemoryRecordedRollRepository;
use Tilttek\DiceBundle\Infrastructure\Validator\DiceExpression;
use Tilttek\DiceBundle\Infrastructure\Validator\DiceExpressionValidator;
use Tilttek\DiceBundle\Tests\Unit\Fixture\ScriptedEngine;

/**
 * @extends ConstraintValidatorTestCase<DiceExpressionValidator>
 */
#[CoversClass(DiceExpression::class)]
#[CoversClass(DiceExpressionValidator::class)]
final class DiceExpressionValidatorTest extends ConstraintValidatorTestCase
{
    public function testAnExpressionThatParsesPasses(): void
    {
        $this->validator->validateInContext('2d6kh1', new DiceExpression(), $this->context);
        $this->validator->validateInContext('2W6', new DiceExpression(locale: 'de'), $this->context);
        $this->validator->validateInContext(null, new DiceExpression(), $this->context);
        $this->validator->validateInContext('', new DiceExpression(), $this->context);

        $this->assertNoViolation();
    }

    public function testAnExpressionThatDoesNotParseSaysWhy(): void
    {
        $this->validator->validateInContext('2W6', new DiceExpression(), $this->context);

        $this->buildViolation('This is not a dice expression: {{ reason }}')
            ->setParameter('{{ reason }}', 'Unexpected "W" at position 1 of "2W6".')
            ->setCode(DiceExpression::INVALID)
            ->assertRaised();
    }

    public function testAnExpressionPastALimitSaysWhichAndTheMessageIsTheApplications(): void
    {
        $this->validator->validateInContext('3d6', new DiceExpression(message: 'No: {{ reason }}'), $this->context);

        $this->buildViolation('No: {{ reason }}')
            ->setParameter('{{ reason }}', 'The dice of a roll are limited to 2; this asks for 3.')
            ->setCode(DiceExpression::INVALID)
            ->assertRaised();
    }

    public function testOnlyTextIsChecked(): void
    {
        $this->expectException(UnexpectedValueException::class);
        $this->validator->validateInContext(new stdClass(), new DiceExpression(), $this->context);
    }

    public function testOnlyItsConstraintIsChecked(): void
    {
        $this->expectException(UnexpectedTypeException::class);
        $this->validator->validateInContext('2d6', new NotBlank(), $this->context);
    }

    public function testTheConstraint(): void
    {
        $constraint = new DiceExpression('fr', groups: ['dice'], payload: 'p');

        self::assertSame(['fr', 'This is not a dice expression: {{ reason }}', ['dice'], 'p', DiceExpressionValidator::class], [$constraint->locale, $constraint->message, $constraint->groups, $constraint->payload, $constraint->validatedBy()]);
        self::assertNull(new DiceExpression()->locale);
    }

    #[Override]
    protected function createValidator(): ConstraintValidatorInterface
    {
        return new DiceExpressionValidator(new DiceRolls(new DiceRoller(new Limits(dice: 2)), ScriptedEngine::randomizer(), new InMemoryRecordedRollRepository(), new MockClock()));
    }
}
