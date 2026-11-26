<?php

declare(strict_types=1);

namespace Tilttek\Referrals;

use DateInterval;
use Exception;
use Tilttek\Points\Account;
use Tilttek\Points\Currency;
use Tilttek\Points\Exception\InvalidPoints;
use Tilttek\Referrals\Exception\InvalidReferral;

/**
 * The terms of the referrals: the points each side receives, how many referrals a referrer is rewarded for in all, the
 * currency and the issuance account of `tilttek/points`, and the two guards on dates — how long the referrer must have
 * been a member, and how soon after joining a newcomer may be referred —, off until the application sets them.
 */
final readonly class Terms
{
    public const int MAX_POINTS = 1_000_000;

    public const int MAX_REWARDS = 10_000;

    public ?DateInterval $referrerSeniority;

    public ?DateInterval $newcomerWindow;

    /**
     * @param int     $referrerPoints    the points the referrer receives for each referral rewarded, within the cap
     * @param int     $newcomerPoints    the points the newcomer receives once their referral is rewarded
     * @param int     $maxRewards        how many referrals a referrer is rewarded for, ever
     * @param ?string $currency          the currency of `tilttek/points`, its default when null
     * @param string  $source            the issuance account the points come from
     * @param ?string $referrerSeniority how long the referrer must have been a member, as an ISO 8601 duration like `P7D`
     * @param ?string $newcomerWindow    how soon after joining a newcomer may be referred, as an ISO 8601 duration
     */
    public function __construct(
        public int $referrerPoints = 100,
        public int $newcomerPoints = 50,
        public int $maxRewards = 10,
        public ?string $currency = null,
        public string $source = 'referrals',
        ?string $referrerSeniority = null,
        ?string $newcomerWindow = null,
    ) {
        foreach (['referrer' => $referrerPoints, 'newcomer' => $newcomerPoints] as $side => $points) {
            if ($points < 1 || $points > self::MAX_POINTS) {
                throw InvalidReferral::because(\sprintf('The %s receives 1 to %d points; %d is out of bounds.', $side, self::MAX_POINTS, $points));
            }
        }

        if ($maxRewards < 1 || $maxRewards > self::MAX_REWARDS) {
            throw InvalidReferral::because(\sprintf('A referrer is rewarded for 1 to %d referrals; %d is out of bounds.', self::MAX_REWARDS, $maxRewards));
        }

        try {
            if (null !== $currency) {
                Currency::checkCode($currency);
            }

            Account::issuance($source);
        } catch (InvalidPoints $invalidPoints) {
            throw InvalidReferral::because($invalidPoints->getMessage());
        }

        $this->referrerSeniority = $this->duration($referrerSeniority);
        $this->newcomerWindow = $this->duration($newcomerWindow);
    }

    private function duration(?string $spec): ?DateInterval
    {
        try {
            return null === $spec ? null : new DateInterval($spec);
        } catch (Exception) {
            throw InvalidReferral::because(\sprintf('"%s" is not an ISO 8601 duration like "P7D".', $spec));
        }
    }
}
