<?php

declare(strict_types=1);

namespace Tilttek\Referrals\Tests\Unit;

use DateTimeImmutable;
use Phil\Resource\ResourceReference;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Random\Engine\Xoshiro256StarStar;
use Random\Randomizer;
use Tilttek\Referrals\Contract\NoSamePersonSignal;
use Tilttek\Referrals\Erasure;
use Tilttek\Referrals\Exception\InvalidReferral;
use Tilttek\Referrals\FirstAction;
use Tilttek\Referrals\Referral;
use Tilttek\Referrals\ReferralCode;
use Tilttek\Referrals\ReferralId;
use Tilttek\Referrals\ReferralStatus;

#[CoversClass(ReferralCode::class)]
#[CoversClass(ReferralId::class)]
#[CoversClass(FirstAction::class)]
#[CoversClass(Referral::class)]
#[CoversClass(Erasure::class)]
#[CoversClass(NoSamePersonSignal::class)]
#[CoversClass(InvalidReferral::class)]
final class ValuesTest extends TestCase
{
    public function testACodeIsDrawnFromTheAlphabet(): void
    {
        $randomizer = new Randomizer(new Xoshiro256StarStar(1));
        $codes = [];

        for ($i = 0; $i < 200; ++$i) {
            $code = ReferralCode::generate($randomizer);
            self::assertMatchesRegularExpression('/^[0-9A-HJKMNP-TV-Z]{10}$/D', $code->value);
            $codes[$code->value] = true;
        }

        self::assertCount(200, $codes);
    }

    public function testACodeIsReadAsAPersonCopiesIt(): void
    {
        self::assertSame('1100ABCD', ReferralCode::fromString('il-oo-abcd')->value);
        self::assertSame('ABCDEFGH', ReferralCode::fromString('ABCDEFGH')->value);
        self::assertSame(str_repeat('Z', 32), ReferralCode::fromString(str_repeat('z', 32))->value);
        self::assertTrue(ReferralCode::fromString('abcd-efgh')->equals(ReferralCode::fromString('ABCDEFGH')));
        self::assertFalse(ReferralCode::fromString('ABCDEFGH')->equals(ReferralCode::fromString('ABCDEFGJ')));
    }

    #[DataProvider('notCodes')]
    public function testATextThatIsNoCodeIsRefused(string $text): void
    {
        $this->expectExceptionObject(InvalidReferral::because('A referral code is 8 to 32 letters and digits of base 32.'));
        ReferralCode::fromString($text);
    }

    /**
     * @return iterable<array{string}>
     */
    public static function notCodes(): iterable
    {
        yield 'too short' => ['ABCDEFG'];
        yield 'too long' => [str_repeat('A', 33)];
        yield 'U is no digit' => ['ABCDEFGU'];
        yield 'a space' => ['ABCD EFGH'];
        yield 'a newline after' => ["ABCDEFGH\n"];
        yield 'empty' => [''];
    }

    public function testAnIdentifierIsRandomHexadecimal(): void
    {
        $randomizer = new Randomizer(new Xoshiro256StarStar(1));
        $id = ReferralId::generate($randomizer);

        self::assertMatchesRegularExpression('/^[0-9a-f]{32}$/D', $id->value);
        self::assertFalse($id->equals(ReferralId::generate($randomizer)));
        self::assertTrue($id->equals(ReferralId::fromString($id->value)));
    }

    #[DataProvider('notIdentifiers')]
    public function testATextThatIsNoIdentifierIsRefused(string $text): void
    {
        $this->expectExceptionObject(InvalidReferral::because('A referral identifier is 32 lower-case hexadecimal digits.'));
        ReferralId::fromString($text);
    }

    /**
     * @return iterable<array{string}>
     */
    public static function notIdentifiers(): iterable
    {
        yield 'short' => [str_repeat('a', 31)];
        yield 'long' => [str_repeat('a', 33)];
        yield 'upper case' => [str_repeat('A', 32)];
        yield 'a newline after' => [str_repeat('a', 32)."\n"];
        yield 'before' => ['x'.str_repeat('a', 32)];
    }

    public function testAFirstActionIsKeptInUtc(): void
    {
        $action = new FirstAction('sheet.published', new DateTimeImmutable('2026-11-20 08:00:00-05:00'));

        self::assertSame('sheet.published', $action->kind);
        self::assertSame('2026-11-20 13:00:00 UTC', $action->at->format('Y-m-d H:i:s e'));
        self::assertSame(str_repeat('a', 64), new FirstAction(str_repeat('a', 64), new DateTimeImmutable())->kind);
    }

    #[DataProvider('notKinds')]
    public function testAKindThatIsNoneIsRefused(string $kind): void
    {
        $this->expectExceptionObject(InvalidReferral::because(\sprintf('An action kind is 1 to 64 lower-case letters, digits, "_", "." or "-", starting with a letter; "%s" is not.', $kind)));
        new FirstAction($kind, new DateTimeImmutable());
    }

    /**
     * @return iterable<array{string}>
     */
    public static function notKinds(): iterable
    {
        yield 'empty' => [''];
        yield 'a digit first' => ['1game'];
        yield 'upper case' => ['Game'];
        yield 'too long' => [str_repeat('a', 65)];
        yield 'a newline after' => ["game\n"];
    }

    public function testAReferralMovesFromPending(): void
    {
        $referral = new Referral(ReferralId::fromString(str_repeat('a', 32)), $this->member(1), $this->member(2), new DateTimeImmutable('2026-11-20 07:00:00-05:00'));

        self::assertSame(ReferralStatus::Pending, $referral->status);
        self::assertNull($referral->settledAt);
        self::assertSame([0, 0], [$referral->referrerPoints, $referral->newcomerPoints]);
        self::assertSame('2026-11-20 12:00:00 UTC', $referral->referredAt->format('Y-m-d H:i:s e'));

        $rewarded = $referral->rewarded(new DateTimeImmutable('2026-11-21 07:00:00-05:00'), 100, 50);
        self::assertSame(ReferralStatus::Rewarded, $rewarded->status);
        self::assertSame('2026-11-21 12:00:00 UTC', $rewarded->settledAt?->format('Y-m-d H:i:s e'));
        self::assertSame([100, 50], [$rewarded->referrerPoints, $rewarded->newcomerPoints]);
        self::assertTrue($rewarded->id->equals($referral->id));
        self::assertSame('2026-11-20 12:00:00', $rewarded->referredAt->format('Y-m-d H:i:s'));

        $refused = $referral->refused(new DateTimeImmutable('2026-11-21 12:00:00 UTC'));
        self::assertSame(ReferralStatus::Refused, $refused->status);
        self::assertSame([0, 0], [$refused->referrerPoints, $refused->newcomerPoints]);

        $detached = $rewarded->withReferrer(Erasure::erasedMember())->withNewcomer($this->member(3));
        self::assertSame(['erased:member', 'member:3'], [$detached->referrer->toString(), $detached->newcomer->toString()]);
        self::assertSame([ReferralStatus::Rewarded, 100, 50], [$detached->status, $detached->referrerPoints, $detached->newcomerPoints]);
        self::assertSame('2026-11-21 12:00:00 UTC', $detached->settledAt?->format('Y-m-d H:i:s e'));
        self::assertSame('2026-11-20 12:00:00', $detached->referredAt->format('Y-m-d H:i:s'));
        self::assertTrue($detached->id->equals($referral->id));
    }

    public function testAReferralKeepsItsStatusConsistent(): void
    {
        $id = ReferralId::fromString(str_repeat('a', 32));
        $now = new DateTimeImmutable();
        $cases = [
            'A referral is settled at a moment exactly when it is no longer pending.' => [
                [ReferralStatus::Pending, $now, 0, 0],
                [ReferralStatus::Rewarded, null, 1, 1],
                [ReferralStatus::Refused, null, 0, 0],
            ],
            'Only a rewarded referral gives points.' => [
                [ReferralStatus::Refused, $now, 0, 1],
                [ReferralStatus::Refused, $now, 1, 0],
                [ReferralStatus::Refused, $now, 1, 1],
            ],
            'A referral gives no negative points.' => [
                [ReferralStatus::Rewarded, $now, -1, 1],
                [ReferralStatus::Rewarded, $now, 1, -1],
            ],
        ];

        foreach ($cases as $message => $invalid) {
            foreach ($invalid as [$status, $settledAt, $referrerPoints, $newcomerPoints]) {
                try {
                    new Referral($id, $this->member(1), $this->member(2), $now, $status, $settledAt, $referrerPoints, $newcomerPoints);
                    self::fail($message);
                } catch (InvalidReferral $refused) {
                    self::assertSame($message, $refused->getMessage());
                }
            }
        }

        self::assertSame(0, new Referral($id, $this->member(1), $this->member(2), $now, ReferralStatus::Rewarded, $now, 0, 50)->referrerPoints);
    }

    public function testTheErasedMember(): void
    {
        self::assertSame('erased:member', Erasure::erasedMember()->toString());
        self::assertTrue(Erasure::isErased(ResourceReference::fromString('erased:member')));
        self::assertFalse(Erasure::isErased(ResourceReference::fromString('erased:other')));
        self::assertSame([0, 0, 0], [new Erasure()->codes, new Erasure()->deleted, new Erasure()->detached]);
    }

    public function testWithoutSignalTwoReferencesAreTwoPeople(): void
    {
        self::assertFalse(new NoSamePersonSignal()->same($this->member(1), $this->member(1)));
    }

    private function member(int $id): ResourceReference
    {
        return ResourceReference::of('member', (string) $id);
    }
}
