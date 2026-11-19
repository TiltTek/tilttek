# Who controls whom

`LuckTokens` lets a holder spend and pass their tokens, and the game masters of the table those of anyone. Whether
another actor may — a player the tokens of the character they play — is asked of a `HolderAccess`, which
`holder_access` chooses.

## `self`

`Tilttek\LuckTokens\Store\SelfAccess`: each holder controls their own tokens, and nobody else's. It is the default.

## `security`

```yaml
tilttek_luck_tokens:
    holder_access: security
```

`SecurityHolderAccess` asks the security voters for `TILTTEK_LUCK_TOKENS_CONTROL` on a `HolderControl`, which carries
the actor and the holder. The bundle registers `HolderControlVoter`, which grants each holder their own tokens and
abstains otherwise; the application adds a voter for the rest:

```php
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Tilttek\LuckTokensBundle\Infrastructure\Security\HolderControl;
use Tilttek\LuckTokensBundle\Infrastructure\Security\HolderControlVoter;

/**
 * @extends Voter<string, HolderControl>
 */
final class CharacterVoter extends Voter
{
    public function __construct(private readonly Characters $characters) {}

    protected function supports(string $attribute, mixed $subject): bool
    {
        return HolderControlVoter::CONTROL === $attribute && $subject instanceof HolderControl && 'character' === $subject->holder->key;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        return $this->characters->playedBy($subject->holder->id, $subject->actor);
    }
}
```

The voters decide on the actor the operation names, not on the user of the request: an operation run from a command
or a message handler is decided the same way. Because the bundle's voter abstains rather than denies, an application
voter that grants is heard under every strategy of the access decision manager; under `unanimous`, a voter that denies
wins, as it should.

`holder_access: security` needs `symfony/security-bundle`, whose `security.authorization_checker` it uses.

## `service`

```yaml
tilttek_luck_tokens:
    holder_access: service
    holder_access_service: App\Tokens\PartyAccess
```

Any service implementing `Tilttek\LuckTokens\Contract\HolderAccess`, with `controls(ResourceReference $actor,
ResourceReference $holder): bool`. It keeps nothing between two calls, so that one instance serves every request of a
worker.
