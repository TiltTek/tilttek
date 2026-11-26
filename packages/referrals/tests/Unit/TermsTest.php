<?php

declare(strict_types=1);

namespace Tilttek\Referrals\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tilttek\Referrals\Exception\InvalidReferral;
use Tilttek\Referrals\Terms;

#[CoversClass(Terms::class)]
final class TermsTest extends TestCase
{
    public function testTheDefaults(): void
    {
        $terms = new Terms();

        self::assertSame([100, 50, 10, null, 'referrals'], [$terms->referrerPoints, $terms->newcomerPoints, $terms->maxRewards, $terms->currency, $terms->source]);
        self::assertNull($terms->referrerSeniority);
        self::assertNull($terms->newcomerWindow);
    }

    public function testTheBounds(): void
    {
        $terms = new Terms(1, 1_000_000, 10_000, 'stars', 'campaign:2026', 'P7D', 'PT48H');

        self::assertSame([1, 1_000_000, 10_000, 'stars', 'campaign:2026'], [$terms->referrerPoints, $terms->newcomerPoints, $terms->maxRewards, $terms->currency, $terms->source]);
        self::assertSame(7, $terms->referrerSeniority?->d);
        self::assertSame(48, $terms->newcomerWindow?->h);
        self::assertSame(1, new Terms(1_000_000, 1, 1)->maxRewards);
    }

    public function testValuesOutOfBounds(): void
    {
        $cases = [
            'The referrer receives 1 to 1000000 points; 0 is out of bounds.' => static fn (): Terms => new Terms(0),
            'The referrer receives 1 to 1000000 points; 1000001 is out of bounds.' => static fn (): Terms => new Terms(1_000_001),
            'The newcomer receives 1 to 1000000 points; 0 is out of bounds.' => static fn (): Terms => new Terms(newcomerPoints: 0),
            'The newcomer receives 1 to 1000000 points; 1000001 is out of bounds.' => static fn (): Terms => new Terms(newcomerPoints: 1_000_001),
            'A referrer is rewarded for 1 to 10000 referrals; 0 is out of bounds.' => static fn (): Terms => new Terms(maxRewards: 0),
            'A referrer is rewarded for 1 to 10000 referrals; 10001 is out of bounds.' => static fn (): Terms => new Terms(maxRewards: 10_001),
            '"Stars" is not a currency code: 1 to 32 lower-case letters, digits and "_", starting with a letter.' => static fn (): Terms => new Terms(currency: 'Stars'),
            '"Referrals" is not an account name: 1 to 255 lower-case letters, digits, "_", ".", ":" or "-", starting with a letter or a digit.' => static fn (): Terms => new Terms(source: 'Referrals'),
            '"7 days" is not an ISO 8601 duration like "P7D".' => static fn (): Terms => new Terms(referrerSeniority: '7 days'),
            '"P" is not an ISO 8601 duration like "P7D".' => static fn (): Terms => new Terms(newcomerWindow: 'P'),
        ];

        foreach ($cases as $message => $terms) {
            try {
                $terms();
                self::fail($message);
            } catch (InvalidReferral $invalid) {
                self::assertSame($message, $invalid->getMessage());
            }
        }
    }
}
