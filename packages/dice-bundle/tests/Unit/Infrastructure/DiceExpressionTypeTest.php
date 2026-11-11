<?php

declare(strict_types=1);

namespace Tilttek\DiceBundle\Tests\Unit\Infrastructure;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Forms;
use Symfony\Component\OptionsResolver\Exception\InvalidOptionsException;
use Tilttek\DiceBundle\Infrastructure\Form\DiceExpressionType;
use Tilttek\DiceBundle\Infrastructure\Validator\DiceExpression;

#[CoversClass(DiceExpressionType::class)]
final class DiceExpressionTypeTest extends TestCase
{
    public function testTheFieldIsTextCheckedByTheConstraintInItsLanguage(): void
    {
        $factory = Forms::createFormFactoryBuilder()->getFormFactory();
        $canonical = $factory->create(DiceExpressionType::class)->getConfig();
        $german = $factory->create(DiceExpressionType::class, null, ['locale' => 'de'])->getConfig();

        self::assertSame(TextType::class, new DiceExpressionType()->getParent());
        self::assertEquals([new DiceExpression()], $canonical->getOption('constraints'));
        self::assertEquals([new DiceExpression(locale: 'de')], $german->getOption('constraints'));
        self::assertSame(['spellcheck' => 'false', 'autocomplete' => 'off'], $canonical->getOption('attr'));
        self::assertNull($canonical->getOption('locale'));

        $this->expectException(InvalidOptionsException::class);
        $factory->create(DiceExpressionType::class, null, ['locale' => 3]);
    }
}
