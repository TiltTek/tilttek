<!-- phil-dev source-sha256=c73405fc07a1df0864538e42c1f4184c89b35fb07a234b9637569ef6e6f45486 -->
# Référence

| Type | Rôle |
|---|---|
| `Raffles` | Ouvre, prend les inscriptions, scelle, tire, et donne l'annonce et la preuve |
| `Raffle` | Un tirage tel que le dépôt de stockage le garde, à une version ; écrit et relu en valeurs simples |
| `Stake` | `Prize` ou `None` : ce qu'un tirage met en jeu, ce qui décide de sa source |
| `SeedSource` | `Beacon` ou `CommitReveal` |
| `RaffleStatus` | `Open`, `Sealed` ou `Drawn` |
| `Announcement` | Ce qui est publié à la fermeture des inscriptions |
| `RaffleProof` | Tout ce qu'un joueur doit avoir pour tirer de nouveau, écrit et lu en JSON ; la place de chaque nom tiré |
| `RaffleVerifier` | Liste les défauts d'une preuve |
| `Beacon\Beacon` | Une ronde d'une balise, son aléa vérifié avec sa signature |
| `Beacon\BeaconChain` | Une chaîne d'une balise : empreinte, genèse, période, schéma et clé publique ; les heures de ses rondes |
| `Beacon\BeaconVerification` | `Signature` ou `RelayQuorum` : la façon dont une ronde a été vérifiée |
| `Beacon\RelayReport` | Ce que chaque relais a donné à une lecture, le quorum et si la lecture a été retenue |
| `Beacon\RelayOutcome` | Ce qu'un relais a donné : `Agreed`, `Disagreed`, `Invalid`, `Status`, `TooLarge`, `TimedOut`, `Unreachable` ou `Incomplete` |
| `Contract\RandomnessBeacon` | Une balise publique d'aléa |
| `Contract\BeaconSignatureVerifier` | Vérifie la signature d'une ronde |
| `Contract\RaffleStore` | Où les tirages et leurs participants sont gardés |
| `Drand\DrandBeacon` | drand lu sur plusieurs relais par Symfony HttpClient |
| `Drand\DrandChains` | Les chaînes publiques de drand, et ses relais |
| `Drand\DrandScheme` | Les schémas de signature de drand, et le message qu'une ronde signe |
| `Event\RaffleDrawn` | Envoyé une fois un tirage effectué, avec son identifiant |
| `Event\BeaconRelaysRead` | Envoyé après chaque lecture des relais de drand, avec l'empreinte de la chaîne et le rapport |
| `Store\InMemoryRaffleStore` | Le dépôt de stockage en mémoire, pour les tests et les scripts |
| `Exception\RaffleException` | L'interface de chaque exception de la bibliothèque |
| `Exception\InvalidRaffle`, `RaffleRefused`, `RaffleConflict`, `BeaconUnavailable`, `BeaconRefused` | Voir [Exceptions](exceptions.md) |

`Encoding`, `Entrants`, `Instant`, `Selection`, `SystemClock` et `Drand\DrandResponse` sont internes.
