<!-- phil-dev source-sha256=9390af0d28a90ce2b091694326872337d43c8acc67e2ce2f8e9caed4171cb9c5 -->
# tilttek/friend-challenges

**Des défis de membres, entre amis ou ouverts, avec un lot en points détenu en séquestre et la modération du site.**

Ana lance à ses amis le défi de peindre dix figurines en une semaine, pour 100 points. Les points quittent aussitôt son
solde et sont détenus pour le lot ; Bea et Cy participent, Dee décline sans qu'Ana en soit avisée ; Bea envoie une photo,
Ana l'accepte, et les 100 points vont à Bea. Si personne n'avait relevé le défi à la fin de son examen, les points
seraient retournés à Ana, comme lorsqu'elle l'annule avant que quiconque envoie une preuve, ou lorsqu'un modérateur le
retire.

Un défi est entre amis, à moins que son créateur l'ouvre à tous les membres. Ses preuves sont jugées par son créateur,
par un vote du créateur et des autres participants, ou par un modérateur du site. Un membre signale un défi ou une preuve
aux modérateurs, qui le gèlent — son lot reste détenu, rien ne bouge —, le dégèlent, le retirent, ou retirent une
preuve. Chaque défi est une machine à états explicite ; chaque mouvement est vérifié, conservé seulement si aucun autre
auteur n'en a conservé un autre avant, puis réglé dans le séquestre et annoncé par un événement. Un mouvement envoyé de
nouveau ne change rien. Toute heure est en UTC.

C'est une bibliothèque PHP simple. L'application conserve les défis, les amitiés, la modération et les points dans son
propre stockage, par quatre ports ; la bibliothèque fournit une implémentation en mémoire de chacun, et un séquestre sur
le registre de `tilttek/points`. Le lot est fait de points, jamais d'argent.

## État

| Partie | État |
|---|---|
| `FriendChallenges` : créer, participer, décliner, prouver, approuver, rejeter, annuler, faire expirer, signaler, geler, dégeler, retirer, régler, effacer | Implémenté et testé |
| `Challenge` : la machine à états, ses limites, et son enregistrement écrit puis relu, vérifié | Implémenté et testé |
| `Contract\PrizeEscrow`, avec `Escrow\InMemoryPrizeEscrow` et `Escrow\PointsPrizeEscrow` sur `tilttek/points` | Implémenté et testé |
| `Contract\ChallengeStore`, `Contract\Friendship`, `Contract\Moderation`, avec des implémentations en mémoire | Implémenté et testé |
| `Event\ChallengeWon` et les autres événements, répartis par PSR-14 | Implémenté et testé |
| Des exceptions sous une seule interface `FriendChallengesException` | Implémenté et testé |

## Documentation

| Document | Contenu |
|---|---|
| [Installation](doc/fr/installation.md) | Les prérequis, l'installation et le branchement. |
| [Utilisation](doc/fr/usage.md) | Créer un défi, y participer, les preuves et leur examen, comment un défi se termine. |
| [Séquestre](doc/fr/escrow.md) | Comment le lot est détenu, payé et rendu, et un séquestre sur `tilttek/exchange-bundle`. |
| [Modération](doc/fr/moderation.md) | Les signalements, le gel, le retrait, et les garde-fous entre amis. |
| [Vie privée](doc/fr/privacy.md) | Effacer un membre : il quitte chaque défi, puis un pseudonyme prend sa place. |
| [Célébrations](doc/fr/celebrations.md) | Annoncer un défi gagné, avec la célébration du kit PHIL UX. |
| [Points d'extension](doc/fr/extension-points.md) | Les ports, l'écriture d'un magasin, et les événements. |
| [Tests](doc/fr/testing.md) | Les tests de la bibliothèque, ses cibles de fuzzing et ses bancs d'essai. |
| [Référence](doc/fr/reference.md) | Chaque type de la bibliothèque, avec son rôle, et ses exceptions. |

## Démarrage rapide

```php
use Tilttek\FriendChallenges\ChallengeDraft;
use Tilttek\FriendChallenges\FriendChallenges;
use Tilttek\FriendChallenges\Prize;

$challenges = new FriendChallenges($store, $escrow, $friendship, $moderation, $eventDispatcher);

$challenges->create(new ChallengeDraft('01JD3', 'Paint ten minis in a week', new Prize(100), new DateTimeImmutable('+7 days'), [$bea, $cy]), $ana);
$challenges->join('01JD3', $bea);
$challenges->submitProof('01JD3', 'proof-1', $bea, 'upload:8812');
$challenges->approve('01JD3', 'proof-1', $ana);   // won: Bea receives the 100 points held
```

## Prérequis

PHP 8.5 ou une version plus récente avec `mbstring` et `sodium`, `phil/resource` 0.1, `tilttek/points` 0.1 ou 0.2,
`psr/clock`, `psr/event-dispatcher` et `symfony/clock`.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt de PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et
le [code de conduite](CODE_OF_CONDUCT.md), en anglais. Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Tilttek project. Voir [LICENSE](LICENSE).
