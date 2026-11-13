<?php

declare(strict_types=1);

namespace Tilttek\SecretRolls\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RoundingMode;
use Tilttek\Dice\DiceRoller;
use Tilttek\Dice\Limits;
use Tilttek\SecretRolls\Encoding;
use Tilttek\SecretRolls\RollCommitment;
use Tilttek\SecretRolls\RollOpening;
use Tilttek\SecretRolls\RollVerifier;
use Tilttek\SecretRolls\Tests\Unit\Fixture\Table;

#[CoversClass(RollVerifier::class)]
final class RollVerifierTest extends TestCase
{
    public function testAnHonestOpeningChecksOut(): void
    {
        $opening = new Table()->rolls->roll('cave', '3d6/2', rounding: RoundingMode::PositiveInfinity)->opening;

        self::assertTrue(new RollVerifier()->verify($opening, $opening->commitment()));
        self::assertTrue(new RollVerifier()->verifyPublished($opening->toArray(), $opening->commitment()->hex));
    }

    public function testAnotherCommitmentFails(): void
    {
        $opening = new Table()->rolls->roll('cave', '1d6')->opening;

        self::assertFalse(new RollVerifier()->verify($opening, RollCommitment::fromHex(str_repeat('0', 64))));
    }

    public function testAResultChangedFailsEvenWithItsOwnCommitment(): void
    {
        $data = new Table()->rolls->roll('cave', '1d20')->opening->toArray();
        $data['result'][0]['total'] = 21;
        $forged = RollOpening::fromArray($data);

        self::assertFalse(new RollVerifier()->verify($forged, $forged->commitment()));
    }

    public function testAnotherSeedFails(): void
    {
        $data = new Table()->rolls->roll('cave', '10d20')->opening->toArray();
        $data['seed'] = str_repeat('1', 64);
        $forged = RollOpening::fromArray($data);

        self::assertFalse(new RollVerifier()->verify($forged, $forged->commitment()));
    }

    public function testAnotherRollAsClientSeedFails(): void
    {
        $data = new Table()->rolls->roll('cave', '10d20')->opening->toArray();
        $data['roll'] = Encoding::rollId('cave', 2);
        $forged = RollOpening::fromArray($data);

        self::assertFalse(new RollVerifier()->verify($forged, $forged->commitment()));
    }

    public function testARoundingPhpDoesNotKnowFails(): void
    {
        $data = new Table()->rolls->roll('cave', '1d6')->opening->toArray();
        $data['rounding'] = 'Sideways';
        $forged = RollOpening::fromArray($data);

        self::assertFalse(new RollVerifier()->verify($forged, $forged->commitment()));
    }

    public function testAnotherRoundingFails(): void
    {
        $odd = null;

        foreach (range(1, 40) as $seed) {
            $data = new Table($seed)->rolls->roll('cave', '1d6/2')->opening->toArray();

            $left = $this->dig($data['result'], 0, 'detail', 'left', 'value');

            if (\is_int($left) && 1 === $left % 2) {
                $odd = $data;
                break;
            }
        }

        self::assertIsArray($odd);
        $odd['rounding'] = 'PositiveInfinity';
        $forged = RollOpening::fromArray($odd);

        self::assertFalse(new RollVerifier()->verify($forged, $forged->commitment()));
    }

    public function testAnExpressionNotInTheCanonicalFormFails(): void
    {
        $data = new Table()->rolls->roll('cave', '1d6')->opening->toArray();
        $data['expression'] = '1D6';
        $forged = RollOpening::fromArray($data);

        self::assertFalse(new RollVerifier()->verify($forged, $forged->commitment()));
    }

    public function testAnExpressionThatDoesNotReadFails(): void
    {
        $data = new Table()->rolls->roll('cave', '1d6')->opening->toArray();
        $data['expression'] = '1d';
        $forged = RollOpening::fromArray($data);

        self::assertFalse(new RollVerifier()->verify($forged, $forged->commitment()));
    }

    public function testAnExpressionPastTheLimitsFails(): void
    {
        $opening = new Table()->rolls->roll('cave', '20d6')->opening;

        self::assertFalse(new RollVerifier(new DiceRoller(new Limits(dice: 10)))->verify($opening, $opening->commitment()));
    }

    public function testPublishedDataThatDoesNotReadFails(): void
    {
        $opening = new Table()->rolls->roll('cave', '1d6')->opening;

        self::assertFalse(new RollVerifier()->verifyPublished(['roll' => 'x'], $opening->commitment()->hex));
        self::assertFalse(new RollVerifier()->verifyPublished($opening->toArray(), 'abc'));
    }

    public function testARoundingIsFoundByItsName(): void
    {
        self::assertSame(RoundingMode::HalfEven, RollVerifier::rounding('HalfEven'));
        self::assertNull(RollVerifier::rounding('halfeven'));
    }

    /**
     * The value at the end of the keys, or null when an array along the way lacks one.
     */
    private function dig(mixed $value, int|string ...$keys): mixed
    {
        foreach ($keys as $key) {
            $value = \is_array($value) ? $value[$key] ?? null : null;
        }

        return $value;
    }
}
