<?php

declare(strict_types=1);

namespace Tilttek\LuckTokensBundle\Infrastructure\Security;

use Override;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\CacheableVoterInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;

/**
 * Grants each holder their own tokens, and abstains otherwise: the voters of the application grant the rest — a
 * player the tokens of their character —, whatever the strategy of the access decision manager.
 */
final readonly class HolderControlVoter implements CacheableVoterInterface
{
    public const string CONTROL = 'TILTTEK_LUCK_TOKENS_CONTROL';

    #[Override]
    public function vote(TokenInterface $token, mixed $subject, array $attributes, ?Vote $vote = null): int
    {
        if (!$subject instanceof HolderControl || !\in_array(self::CONTROL, $attributes, true) || !$subject->actor->equals($subject->holder)) {
            return self::ACCESS_ABSTAIN;
        }

        $vote?->addReason('Each holder controls their own tokens.');

        return self::ACCESS_GRANTED;
    }

    #[Override]
    public function supportsAttribute(string $attribute): bool
    {
        return self::CONTROL === $attribute;
    }

    #[Override]
    public function supportsType(string $subjectType): bool
    {
        return HolderControl::class === $subjectType;
    }
}
