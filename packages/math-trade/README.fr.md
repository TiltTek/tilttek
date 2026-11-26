<!-- phil-dev source-sha256=2531dc2280356f75c08449e0d4f519c67d817bfccf7dd630bf26a6876d4abfba -->
# tilttek/math-trade

**Un échange mathématique de jeux : chacun liste ce qu'il offre et ce qu'il accepterait en retour, et le plus grand
nombre d'échanges possible est trouvé, sous forme de boucles où chacun donne un jeu et en reçoit un autre.**

Dans un échange mathématique, personne n'échange avec un seul partenaire. Chaque participant offre des jeux et, pour
chacun, liste les offres qu'il accepterait en échange, de la plus souhaitée à la moins souhaitée. La bibliothèque
trouve l'ensemble d'échanges qui déplace le plus de jeux et, parmi ceux-là, celui qui respecte le mieux les
priorités. Les échanges forment des boucles : Alice envoie son jeu à Carol, Carol le sien à Bob, Bob le sien à Alice.
Chaque participant lit alors sa part ainsi : « vous envoyez ceci à X, vous recevez cela de Y ».

Un souhait peut être un groupe — « n'importe lequel de ces trois » — à une seule priorité. Un participant qui offre
plusieurs jeux et qui souhaite un jeu dont plusieurs exemplaires sont offerts le reçoit une seule fois au plus. Le
solveur est déterministe : un germe publié départage les égalités, si bien que n'importe qui peut résoudre les mêmes
listes avec le même germe et trouver les mêmes échanges. Les listes s'écrivent et se lisent en JSON, sur un format
documenté, et chaque liste est vérifiée contre des bornes avant d'être résolue.

La bibliothèque ne garde que des identifiants : aucun nom et aucune adresse postale. L'application montre les
adresses une fois l'échange confirmé. C'est une bibliothèque PHP simple, sans dépendance.

## État

| Partie | État |
|---|---|
| `Participant`, `Offer`, `Want`, `WantGroup` : la liste d'échange immuable | Implémentée et testée |
| `TradeList`, `Limits` : la liste vérifiée d'un bloc, et ses bornes | Implémentées et testées |
| `TradeSolver` : le plus d'échanges, les meilleures priorités, un jeu reçu une fois, égalités départagées par le germe | Implémenté et testé |
| `TradeResult`, `TradeCycle`, `Trade`, `Swap` : les boucles, et ce que chacun envoie et reçoit | Implémentés et testés |
| `TradeListJson` : les listes écrites et lues en JSON | Implémenté, testé et soumis au fuzzing |

## Documentation

| Document | Contenu |
|---|---|
| [Installation](doc/fr/installation.md) | Les prérequis et l'installation. |
| [Mener un échange mathématique](doc/fr/math-trade.md) | Listes, souhaits, groupes, exemplaires, germe et résultat, de bout en bout. |
| [Le format JSON](doc/fr/json-format.md) | Le document dans lequel les listes s'écrivent et se lisent. |
| [Points d'extension](doc/fr/extension-points.md) | Les limites, les identifiants, et ce qui reste à l'application. |
| [Exceptions](doc/fr/exceptions.md) | `MathTradeException` et ses exceptions, avec leurs messages. |
| [Tests](doc/fr/testing.md) | Les tests de la bibliothèque, son banc d'essai et sa cible de fuzzing, et la complexité du solveur. |
| [Référence](doc/fr/reference.md) | Chaque type public, avec son rôle. |

## Démarrage rapide

```sh
composer require tilttek/math-trade
```

```php
use Tilttek\MathTrade\Offer;
use Tilttek\MathTrade\Participant;
use Tilttek\MathTrade\TradeList;
use Tilttek\MathTrade\TradeSolver;
use Tilttek\MathTrade\Want;
use Tilttek\MathTrade\WantGroup;

$list = new TradeList([
    new Participant('alice', [new Offer('azul-1', [new Want('catan-1'), new WantGroup(['wingspan-1', 'carcassonne-1'])])]),
    new Participant('bob', [new Offer('catan-1', [new Want('wingspan-1')])]),
    new Participant('carol', [new Offer('wingspan-1', [new Want('azul-1')])]),
    new Participant('dave', [new Offer('carcassonne-1')]),
]);

$result = new TradeSolver()->solve($list, 'spring-trade-2026');

foreach ($result->forParticipant('alice') as $swap) {
    echo "Send {$swap->sent} to {$swap->sendTo}, receive {$swap->received} from {$swap->receiveFrom}\n";
}
// Send azul-1 to carol, receive catan-1 from bob
```

## Prérequis

PHP 8.5 ou une version plus récente.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt de PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et
le [code de conduite](CODE_OF_CONDUCT.md), en anglais. Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Tilttek project. Voir [LICENSE](LICENSE).
