<?php

declare(strict_types=1);

namespace Tilttek\LuckTokensBundle\Infrastructure\Security;

use Override;
use Phil\Resource\ResourceReference;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Tilttek\LuckTokens\Contract\HolderAccess;

/**
 * The access of actors to holders, asked of the security voters: `TILTTEK_LUCK_TOKENS_CONTROL` on a `HolderControl`.
 * The bundle's `HolderControlVoter` grants each holder their own tokens; a voter of the application grants the
 * player the tokens of their character. The game masters of the table always may, whatever the voters say.
 */
final readonly class SecurityHolderAccess implements HolderAccess
{
    public function __construct(
        private AuthorizationCheckerInterface $authorization,
    ) {
    }

    #[Override]
    public function controls(ResourceReference $actor, ResourceReference $holder): bool
    {
        return $this->authorization->isGranted(HolderControlVoter::CONTROL, new HolderControl($actor, $holder));
    }
}
