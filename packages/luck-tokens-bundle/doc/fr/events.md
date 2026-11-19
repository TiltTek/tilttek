<!-- phil-dev source-sha256=6047b2131abc9b3d5cfe2d6f15f338d8c2f7c945f40d484bb0c06f5313763140 -->
# Événements

`LuckTokens` répartit ses événements au répartiteur d'événements de l'application, une fois leurs entrées écrites,
jamais pour une opération envoyée de nouveau :

| Événement | Quand |
|---|---|
| `Tilttek\LuckTokens\Event\TokensGranted` | Des jetons octroyés |
| `Tilttek\LuckTokens\Event\TokensSpent` | Des jetons dépensés |
| `Tilttek\LuckTokens\Event\TokensTransferred` | Des jetons passés d'un détenteur à un autre |
| `Tilttek\LuckTokens\Event\SessionStarted` | Une session commencée, avec ses rafraîchissements |
| `Tilttek\LuckTokens\Event\HolderErased` | Un membre effacé sous un pseudonyme ; il ne nomme ni le membre ni une table |

```php
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Tilttek\LuckTokens\Event\TokensSpent;

#[AsEventListener]
final readonly class OfferTheReroll
{
    public function __invoke(TokensSpent $spent): void
    {
        // $spent->entry->table, ->holder, ->kind, ->reason, ->operation, ->position
    }
}
```

Un écouteur s'exécute dans la requête qui a écrit les entrées. Le travail qui prend du temps — une notification, une
poussée vers l'écran de la table — passe par Messenger avec la table, l'opération et la position seulement : le
*handler* relit l'entrée dans le registre, de sorte qu'un message ne porte aucun renseignement personnel.
