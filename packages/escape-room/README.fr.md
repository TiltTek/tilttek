<!-- phil-dev source-sha256=389524042c5ca189b3d122b98699cd72315aaa40e32c43960c5081baeedcff68 -->
# tilttek/escape-room

**Les escape games sur des énigmes enchaînées : une minuterie, des indices comptés dans le temps final, une console de
l'animateur, des séances réservables et un classement public par consentement.**

Une salle est un scénario d'énigmes de `tilttek/puzzle-builder`, enchaînées ou ouvertes selon le graphe que permet le
scénario, un temps pour s'échapper, et ce que coûtent un indice et une énigme sautée. Une équipe lance la minuterie ;
elle propose des réponses, demande des indices et s'échappe quand elle résout la finale. Depuis la console, l'animateur
envoie un indice à l'écran d'indices, écrit un message, efface l'écran, met la minuterie en pause et la relance, saute
une énigme pour une équipe bloquée ou met fin à la partie. Chaque indice, demandé ou envoyé, ajoute sa pénalité au temps
final, jamais à la minuterie : le temps joué plus les pénalités classe une équipe.

Les séances se réservent par un port que l'application implémente sur ses réservations, ou par l'adaptateur que la
bibliothèque fournit sur `phil/reservation` : une séance privée pour un groupe, qui prend toute la salle, ou des places
d'une séance partagée, jusqu'à la plus grande équipe de la salle. Le classement public ne montre une équipe qu'une fois qu'elle y consent, et elle peut
changer d'avis.

C'est une bibliothèque PHP ordinaire qui ne garde rien : chaque coup prend la partie de l'équipe et donne la partie à
stocker ensuite, et chaque coup de la console est un événement, pour un bundle qui les pousse en temps réel vers l'écran
d'indices. La minuterie se lit sur une horloge PSR-20, jamais gardée en marche. Les serrures, les capteurs et les
accessoires sont hors de la bibliothèque ; ses événements et son API leur restent ouverts.

## État

| Partie | État |
|---|---|
| `Room` : un scénario, sa durée, les pénalités d'un indice et d'un saut, la taille d'une équipe ; des salles enchaînées | Implémenté et testé |
| `EscapeRooms` : le départ, les réponses, les indices demandés et envoyés, les sauts, les énigmes ouvertes, les pauses, l'écran d'indices, l'expiration, l'abandon, le consentement, les résultats | Implémenté et testé |
| `Game`, `Timer`, `GameOutcome` : l'état d'une partie, écrit et relu ; la minuterie et le temps final | Implémentés et testés |
| `Booking\SessionBooker`, `Booking\Session`, `Booking\SeatBooking` : séances privées et partagées | Implémentés et testés |
| `Contract\SessionReservations`, avec `Store\InMemorySessionReservations` | Implémentés et testés |
| `Reservation\BookableSessionReservations` sur `phil/reservation`, son `Contract\SeatLedger` et `Store\InMemorySeatLedger` | Implémentés et testés |
| `Leaderboard`, `RoomResult`, `Standing` : le classement public, par consentement seulement | Implémentés et testés |
| Les événements de la partie, de la console et des réservations, envoyés par PSR-14 | Implémentés et testés |
| Les exceptions, sous une seule interface `EscapeRoomException` | Implémentées et testées |

## Documentation

| Document | Contenu |
|---|---|
| [Installation](doc/fr/installation.md) | Les prérequis, l'installation et le branchement. |
| [Utilisation](doc/fr/usage.md) | Les salles, les parties, la minuterie, les indices et les pénalités, la console de l'animateur, le stockage d'une partie, les résultats et le classement. |
| [Réservations](doc/fr/bookings.md) | Les séances privées et partagées, le port vers les réservations, et l'adaptateur sur `phil/reservation`. |
| [Accessibilité](doc/fr/accessibility.md) | L'affichage de la minuterie, de l'écran d'indices et du classement. |
| [Moteurs et garde-fous](doc/fr/drives-and-safeguards.md) | La place de la bibliothèque dans la grille des moteurs de Tilttek, et les garde-fous qu'elle porte. |
| [Points d'extension](doc/fr/extension-points.md) | Les ports, les événements de la console, et ce qu'ajoute un bundle. |
| [Tests](doc/fr/testing.md) | Les tests, la cible de fuzzing et les bancs d'essai de la bibliothèque. |
| [Référence](doc/fr/reference.md) | Chaque type de la bibliothèque, avec son rôle, et ses exceptions. |

## Démarrage rapide

```php
use Phil\Resource\ResourceReference;
use Tilttek\EscapeRoom\EscapeRooms;
use Tilttek\EscapeRoom\Room;
use Tilttek\PuzzleBuilder\Answer\AnswerKey;
use Tilttek\PuzzleBuilder\Hint;
use Tilttek\PuzzleBuilder\Limiter\InMemoryGuessLimiter;
use Tilttek\PuzzleBuilder\Puzzle;
use Tilttek\PuzzleBuilder\PuzzleEngine;

$room = Room::chain('crypt', 'The crypt', [
    new Puzzle('candle', 'The candle', new AnswerKey(['wick']), [new Hint('Look at the top')]),
    new Puzzle('tomb', 'The tomb', new AnswerKey(['1347'])),
], duration: 3_600, hintPenalty: 120);
$rooms = new EscapeRooms(new PuzzleEngine(new InMemoryGuessLimiter(10, 60), $eventDispatcher), $eventDispatcher);
$host = ResourceReference::of('member', '3');

$game = $rooms->start($room, ResourceReference::of('team', 'owls'));
$game = $rooms->pushHint($room, $game, 'candle', $host)->game;   // on the hint screen, 2 minutes added
$game = $rooms->guess($room, $game, 'candle', 'Wick')->game;
$game = $rooms->guess($room, $game, 'tomb', '1347')->game;      // escaped
$rooms->timer($room, $game)->final();                            // the time played plus 120 seconds
$game->toArray();                                                // to store
```

## Prérequis

PHP 8.5 ou une version plus récente avec `mbstring`, `tilttek/puzzle-builder` 0.1, `phil/resource` 0.1, `psr/clock`,
`psr/event-dispatcher` et `symfony/clock`.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt de PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et
le [code de conduite](CODE_OF_CONDUCT.md), en anglais. Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Tilttek project. Voir [LICENSE](LICENSE).
