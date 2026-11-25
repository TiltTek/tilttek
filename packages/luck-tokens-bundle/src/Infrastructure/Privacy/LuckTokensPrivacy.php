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
use Tilttek\LuckTokensBundle\Infrastructure\Doctrine\DbalTokenRules;
use Tilttek\LuckTokensBundle\Infrastructure\Doctrine\LuckTokensTables;

/**
 * The tokens' part of an erasure request of `phil/privacy-bundle`. The data subject is a member, referenced under the
 * kind the configuration names (`member` by default). `doc/erasing-a-member.md` gives the reason of each choice:
 *
 * - in every ledger, `eraseHolder()` writes a pseudonym in their place, as holder and as actor — under the member's
 *   lock when the locks are enabled — and clears the reasons of the entries they hold;
 * - with the rules kept in the database, the member is taken off the game masters of every table.
 *
 * The report is the same for an erasure sent again.
 */
final readonly class LuckTokensPrivacy implements PersonalDataEraser
{
    public const string MODULE = 'tilttek_luck_tokens';

    public function __construct(
        private LuckTokens $tokens,
        private string $memberKind,
        private ?LockedLuckTokens $locked = null,
        private ?DbalTokenRules $rules = null,
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

        $effects = [
            LuckTokensTables::ENTRIES.'.holder' => ErasureEffect::Pseudonymised,
            LuckTokensTables::ENTRIES.'.actor' => ErasureEffect::Pseudonymised,
            LuckTokensTables::ENTRIES.'.reason' => ErasureEffect::Deleted,
        ];

        if ($this->rules instanceof DbalTokenRules) {
            $this->rules->removeGameMaster($member);
            $effects[LuckTokensTables::RULES.'.game_masters'] = ErasureEffect::Deleted;
        }

        return ErasureReport::of(self::MODULE, $effects);
    }
}
