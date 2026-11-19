<!-- phil-dev source-sha256=86464ba2f3a294bbc5a307cd01d5f268f3cb970d28f1d6ea6559d2ceae21e39e -->
# tilttek/raffle

**Tirages au sort vérifiables sans achat de chances : une future ronde de drand donne le germe d'un tirage à prix,
l'engagement-révélation celui d'un tirage sans enjeu, et une preuve que tout joueur vérifie.**

Un tirage au sort désigne des gagnants parmi des participants, et chaque participant détient une seule chance : le
modèle compte les participants, jamais les billets, et rien n'y achète, ne pondère ni ne multiplie une chance. Quand
les inscriptions se ferment, les participants sont listés dans l'ordre des octets et leur empreinte est calculée, puis
l'organisateur annonce cette empreinte avec la source du tirage, avant que quiconque puisse connaître les gagnants.

Pour un tirage à prix, le hasard vient d'une balise publique d'aléa : la première ronde de drand publiée après la
fermeture des inscriptions et une marge, une ronde que personne — l'organisateur compris — ne connaît d'avance ni ne
peut choisir. Pour un tirage sans enjeu, l'organisateur s'engage sur un germe secret à l'ouverture du tirage et le
révèle au moment du tirage ; aucun réseau n'est nécessaire. Dans les deux cas, le germe du tirage est l'empreinte du
tirage, de ses participants et de cet aléa, et les gagnants, puis les substituts qui remplacent un gagnant qui ne
réclame pas son prix, sortent d'un mélange partiel de Fisher-Yates écrit de façon que n'importe quel langage les tire
de nouveau. La preuve contient tout ce qu'un joueur doit avoir pour vérifier le tirage seul.

Le paquet s'adresse à une application qui organise des concours, des prix de présence ou des tirages au hasard dans un
congrès, une soirée de jeu ou une diffusion en direct, et qui les veut équitables et vus comme tels. C'est une
bibliothèque PHP simple : l'adaptateur drand lit les relais par Symfony HttpClient, leurs adresses vérifiées par
`phil/url` ; le dépôt de stockage est celui de l'application ; le tirage envoie un événement que l'application écoute,
pour annoncer les gagnants ou jouer une célébration.

## État

| Partie | État |
|---|---|
| `Raffles` : l'ouverture, les inscriptions, le scellement avec l'annonce, le tirage et la preuve | Implémenté et testé |
| `Raffle`, `Stake`, `SeedSource`, `RaffleStatus` : le tirage tel qu'il est stocké, une chance par participant | Implémenté et testé |
| `Announcement`, `RaffleProof` : ce qui est publié à la fermeture des inscriptions et après le tirage | Implémenté et testé |
| `RaffleVerifier` : une preuve vérifiée du côté du joueur, avec un vérificateur de signature facultatif | Implémenté et testé |
| `Beacon\Beacon`, `Beacon\BeaconChain`, `Beacon\BeaconVerification`, `Contract\RandomnessBeacon`, `Contract\BeaconSignatureVerifier` | Implémenté et testé |
| `Drand\DrandBeacon`, `Drand\DrandChains`, `Drand\DrandScheme` : drand lu sur plusieurs relais, les rondes confirmées par un quorum | Implémenté et testé |
| `Beacon\RelayReport`, `Beacon\RelayOutcome`, `Event\BeaconRelaysRead` : les échecs et les désaccords de chaque relais signalés | Implémenté et testé |
| Vérification BLS d'une signature de drand | Non fournie : l'application branche un `BeaconSignatureVerifier` ; voir [la limite connue](doc/fr/drand.md#la-limite-connue) |
| `Contract\RaffleStore`, `Store\InMemoryRaffleStore`, `Event\RaffleDrawn` | Implémenté et testé |
| Exceptions sous une seule interface `RaffleException` | Implémenté et testé |

## Documentation

| Document | Contenu |
|---|---|
| [Installation](doc/fr/installation.md) | Les prérequis, l'installation et le branchement. |
| [Tenir un tirage](doc/fr/running-a-raffle.md) | Les enjeux et les sources, les inscriptions, le scellement, le tirage, les substituts et l'événement. |
| [Le tirage et sa preuve](doc/fr/draw-and-proof.md) | Les empreintes, le mélange, la preuve et la façon dont un joueur la vérifie seul. |
| [drand](doc/fr/drand.md) | L'adaptateur de balise : les chaînes, les relais, le quorum et la vérification d'une signature. |
| [Stockage](doc/fr/storing.md) | Le contrat du dépôt de stockage, les versions et les inscriptions concurrentes. |
| [Points d'extension](doc/fr/extension-points.md) | Ce qu'une application donne à la bibliothèque. |
| [Exceptions](doc/fr/exceptions.md) | `RaffleException` et ses exceptions, avec leurs messages. |
| [Tests](doc/fr/testing.md) | Les tests de la bibliothèque, ses cibles de fuzzing et ses bancs d'essai. |
| [Référence](doc/fr/reference.md) | Chaque type de la bibliothèque, avec son rôle. |

## Démarrage rapide

```php
use Symfony\Component\HttpClient\HttpClient;
use Tilttek\Raffle\Drand\{DrandBeacon, DrandChains};
use Tilttek\Raffle\{Raffles, Stake};
use Tilttek\Raffle\Store\InMemoryRaffleStore;

$beacon = new DrandBeacon(HttpClient::create(), DrandChains::quicknet(), DrandChains::RELAYS);
$raffles = new Raffles(new InMemoryRaffleStore(), $beacon);

$raffles->open('door-prize', Stake::Prize, winners: 3, alternates: 2);
$raffles->enter('door-prize', 'member:1042');           // once per entrant
$announcement = $raffles->seal('door-prize');           // publish it: the hash, the round, its time
// ... once $announcement->drawableAt() has passed:
$proof = $raffles->draw('door-prize');
$proof->winnerList();                                  // the podium, in order: $proof->place($entrant)
$json = $proof->toJson();                               // what a player checks
```

## Prérequis

PHP 8.5 ou une version plus récente avec `ext-sodium` ; `phil/url` 0.1, `psr/clock`, `psr/event-dispatcher` et
`symfony/http-client-contracts` ; un client HTTP, comme `symfony/http-client`, pour l'adaptateur drand.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt de PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et
le [code de conduite](CODE_OF_CONDUCT.md), en anglais. Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Tilttek project. Voir [LICENSE](LICENSE).
