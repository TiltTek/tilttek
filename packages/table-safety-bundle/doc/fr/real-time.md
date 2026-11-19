<!-- phil-dev source-sha256=d71de68888162f28bedefa04a0f25531416bc6e8d4c9f63b669f608400485af8 -->
# Temps réel

Les signaux parviennent aux autres écrans d'une table par un hub Mercure et le contrôleur Stimulus des signaux. Mercure
leur convient : un signal va dans un seul sens, du serveur vers les écrans qui lisent son sujet ; le hub garde les
connexions, de sorte que les workers de l'application n'en tiennent aucune ; et les sujets sont privés, lus seulement
avec le témoin d'autorisation que donne l'écran. Le contrôleur lit le hub avec `EventSource` du navigateur, sans
bibliothèque cliente.

Avec `mercure.hub` configuré, `MercureSignalObserver` diffuse chaque signal au hub dès qu'il est levé et dès qu'il est
marqué comme vu. Sans hub, les écrans montrent les signaux au chargement, et les formulaires fonctionnent quand même.

## Le hub

`mercure.hub` nomme un service `HubInterface`, comme `mercure.hub.default` de MercureBundle. Les deux sortes de hub
conviennent :

- un hub joint par son URL, comme le serveur Mercure ou le hub d'un service d'hébergement, où le bundle publie par HTTP
  avec un jeton ;
- le hub intégré au serveur FrankenPHP, que MercureBundle choisit quand l'`url` du hub est vide, et où le bundle publie
  dans le processus du serveur.

Le hub intégré ne publie que depuis une requête servie par FrankenPHP : sa fonction de publication n'existe pas en ligne
de commande. Le bundle lève et confirme les signaux depuis des requêtes seulement, le hub intégré lui suffit donc. Une
application qui lève des signaux depuis une commande ou un consommateur de Messenger leur nomme un hub joint par son
URL ; MercureBundle déclare plusieurs hubs.

## Les sujets

Chaque table a deux sujets privés :

| Sujet | Signaux | Lu par |
|---|---|---|
| `urn:tilttek:table-safety:<table>` | ceux que voit toute la table | chaque participant et le meneur de jeu |
| `urn:tilttek:table-safety:<table>/game-master` | les discrets : la porte ouverte, les réponses à un point d'étape, et la carte X et Script Change à une table qui l'a choisi | le meneur de jeu |

La référence de la table est encodée de sorte qu'elle reste un seul segment. Un écran s'abonne à ses sujets par leurs
noms exacts, avec `match` — ou `topic` pour un hub du protocole hérité —, jamais par un motif.

## Ce qui est diffusé

Le JSON de `Signal::toArray()` : l'identifiant du signal, la référence de la table, le genre, l'auditoire et les
moments — la référence de l'auteur seulement pour un signal nominatif. Rien d'autre n'atteint le hub. Une carte X est anonyme sauf si la table a choisi les signaux
nominatifs.

## Les abonnements

Quand un écran se charge, `SignalSubscriptions` donne au témoin d'autorisation du lecteur les sujets qu'il lit — celui
de la table, et celui du meneur de jeu pour le meneur de jeu —, et l'écran passe l'URL du hub au contrôleur Stimulus.

## Quand le hub échoue

Un hub injoignable — toute `\RuntimeException`, celle d'un hub par URL comme celle du hub intégré — est journalisé
comme un avertissement, avec l'identifiant du signal et la référence de la table, et ne fait pas échouer le signal : il
est enregistré, et l'écran qui l'a levé le montre. Quand la connexion revient, le contrôleur lit dans le fil de la table
les signaux qu'il a manqués.
