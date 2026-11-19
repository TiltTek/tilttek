<?php

declare(strict_types=1);

namespace Tilttek\LuckTokensBundle\Infrastructure\Privacy;

use Override;
use Phil\PrivacyBundle\Application\Contract\PersonalDataEraser;
use Phil\PrivacyBundle\Domain\Retention\ErasureEffect;
use Phil\PrivacyBundle\Domain\Subject\DataSubject;
use Phil\PrivacyBundle\Domain\Subject\ErasureReport;
use Phil\PrivacyBundle\Domain\Subject\ModuleName;
use Phil\Resource\ResourceReference;
use Tilttek\LuckTokens\LuckTokens;
use Tilttek\LuckTokensBundle\Application\LockedLuckTokens;
use Tilttek\LuckTokensBundle\Infrastructure\Doctrine\LuckTokensTables;

/**
 * The tokens' part of an erasure request of `phil/privacy-bundle`. The data subject is a member, referenced under the
 * kind the configuration names (`member` by default); `eraseHolder()` writes a pseudonym in their place, as holder and
 * as actor, in every ledger — under the member's lock when the locks are enabled. The report names the holder and the
 * actor of the entries as pseudonymised, an erasure sent again included.
 */
final readonly class LuckTokensPrivacy implements PersonalDataEraser
{
    public const string MODULE = 'tilttek_luck_tokens';

    public function __construct(
        private LuckTokens $tokens,
        private string $memberKind,
        private ?LockedLuckTokens $locked = null,
    ) {
    }

    #[Override]
    public function module(): ModuleName
    {
        return ModuleName::fromString(self::MODULE);
    }

    #[Override]
    public function erase(DataSubject $subject): ErasureReport
    {
        $member = ResourceReference::of($this->memberKind, $subject->value);

        if ($this->locked instanceof LockedLuckTokens) {
            $this->locked->eraseHolder($member);
        } else {
            $this->tokens->eraseHolder($member);
        }

        return ErasureReport::of(self::MODULE, [
            LuckTokensTables::ENTRIES.'.holder' => ErasureEffect::Pseudonymised,
            LuckTokensTables::ENTRIES.'.actor' => ErasureEffect::Pseudonymised,
        ]);
    }
}
