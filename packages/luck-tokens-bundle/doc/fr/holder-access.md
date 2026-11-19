<!-- phil-dev source-sha256=e0e06868f1c8cfc7f102cf560fbe3b0daac26fa80f951bfafac1a674c019bf44 -->
# Qui contrôle qui

`LuckTokens` laisse un détenteur dépenser et passer ses jetons, et les meneurs de jeu d'une table ceux de n'importe qui.
Si un autre acteur le peut — un joueur, les jetons du personnage qu'il joue —, la question est posée à un
`HolderAccess`, que choisit `holder_access`.

## `self`

`Tilttek\LuckTokens\Store\SelfAccess` : chaque détenteur contrôle ses propres jetons, et ceux de personne d'autre. C'est
la valeur par défaut.

## `security`

```yaml
tilttek_luck_tokens:
    holder_access: security
```

`SecurityHolderAccess` demande aux voters de sécurité `TILTTEK_LUCK_TOKENS_CONTROL` sur un `HolderControl`, qui porte
l'acteur et le détenteur. Le bundle enregistre `HolderControlVoter`, qui accorde à chaque détenteur ses propres jetons et
s'abstient autrement ; l'application ajoute un voter pour le reste :

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

Les voters décident selon l'acteur que nomme l'opération, non selon l'utilisateur de la requête : une opération lancée
depuis une commande ou un *handler* de message est décidée de la même façon. Parce que le voter du bundle s'abstient
plutôt que de refuser, un voter de l'application qui accorde est entendu sous toute stratégie du gestionnaire de décision
d'accès ; sous `unanimous`, un voter qui refuse l'emporte, comme il se doit.

`holder_access: security` exige `symfony/security-bundle`, dont il utilise `security.authorization_checker`.

## `service`

```yaml
tilttek_luck_tokens:
    holder_access: service
    holder_access_service: App\Tokens\PartyAccess
```

N'importe quel service qui implémente `Tilttek\LuckTokens\Contract\HolderAccess`, avec `controls(ResourceReference $actor,
ResourceReference $holder): bool`. Il ne garde rien entre deux appels, de sorte qu'une instance sert chaque requête d'un
*worker*.
