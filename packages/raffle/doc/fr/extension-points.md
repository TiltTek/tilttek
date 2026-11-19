<!-- phil-dev source-sha256=5c811af17ca1edb681957ec9f71c1e86899aa332740ee74d841947985ed21067 -->
# Points d'extension

Le paquet est en `0.x` : ses points d'extension sont stables seulement à partir de sa version 1.0. D'ici là, une
version mineure peut les changer, et son CHANGELOG dit comment.

| Point | Ce que l'application donne | Où |
|---|---|---|
| `Contract\RaffleStore` | Le stockage des tirages et de leurs participants | [Stockage](storing.md) |
| `Contract\RandomnessBeacon` | Une balise publique d'aléa ; `Drand\DrandBeacon` lit drand | [drand](drand.md) |
| `Contract\BeaconSignatureVerifier` | La vérification de la signature d'une ronde, une signature BLS pour drand | [drand](drand.md#vérifier-une-signature) |
| `Psr\EventDispatcher\EventDispatcherInterface` | Qui entend parler de chaque tirage, par `Event\RaffleDrawn`, et de chaque lecture des relais, par `Event\BeaconRelaysRead` | [Tenir un tirage](running-a-raffle.md#lévénement), [drand](drand.md#ce-que-chaque-relais-a-donné) |
| `Psr\Clock\ClockInterface` | L'heure des ouvertures, des fermetures et des tirages, et la fin de chaque lecture des relais | [Installation](installation.md#brancher-la-bibliothèque) |
| `Random\Randomizer` | La source des germes des organisateurs | [Installation](installation.md#brancher-la-bibliothèque) |
| `Symfony\Contracts\HttpClient\HttpClientInterface` | Le client avec lequel les relais sont lus | [drand](drand.md#les-relais-et-le-quorum) |

Chaque point est passé à un constructeur ; aucun service de la bibliothèque ne garde d'état entre deux appels.
