<!-- phil-dev source-sha256=2c4c41a46662d74767e716e48b4cb18d92a07130316154a2f8629966a257e291 -->
# tilttek/bounties

**Des primes ludiques prépayées en points, détenues en séquestre, réclamées et attribuées dans un temps borné ; jamais de
l'argent.**

Ana veut une meilleure réponse à sa question de règles et offre une prime de 100 points, ouverte une semaine. Les
points quittent aussitôt son solde et sont détenus. Bea et Cy répondent et réclament la prime ; Ana attribue 60 points
à la réponse de Bea et 30 à celle de Cy, puis ferme la prime : Bea reçoit 60 points, Cy 30, et les 10 restants
retournent à Ana. Si Ana n'avait rien attribué à la fin du délai de grâce qui suit la semaine, tous les points lui
seraient revenus ; si elle avait attribué quelque chose et oublié de fermer, les attributions auraient alors été payées
et le reste rendu.

Une prime est offerte sur un sujet — une question, une quête, une demande —, une seule prime ouverte à la fois sur
chacun, avec une raison choisie dans une liste. Sa durée est bornée et obligatoire. Chaque prime est une machine à états
explicite ; chaque mouvement est vérifié, conservé seulement si aucun autre auteur n'en a conservé un autre avant,
puis réglé dans le séquestre et annoncé par un événement. Un mouvement envoyé de nouveau ne change rien. Toute heure est
en UTC.

C'est une bibliothèque PHP simple. L'application conserve les primes et les points dans son propre stockage, par deux
ports ; la bibliothèque fournit une implémentation en mémoire de chacun, et un séquestre sur le registre de
`tilttek/points`. Une prime est faite de points, jamais d'argent : la bibliothèque n'a ni champ ni adaptateur pour
l'argent.

## État

| Partie | État |
|---|---|
| `Bounties` : offrir, réclamer, attribuer, fermer, annuler, faire expirer, régler, effacer, avec une limite de primes ouvertes par commanditaire | Implémenté et testé |
| `Bounty` : la machine à états, ses limites, et son enregistrement écrit puis relu, vérifié | Implémenté et testé |
| `Contract\BountyEscrow`, avec `Escrow\InMemoryBountyEscrow` et `Escrow\PointsBountyEscrow` sur `tilttek/points` | Implémenté et testé |
| `Contract\BountyStore`, avec `Store\InMemoryBountyStore` | Implémenté et testé |
| `Event\BountyAwarded` et les autres événements, répartis par PSR-14 | Implémenté et testé |
| Des exceptions sous une seule interface `BountiesException` | Implémenté et testé |

## Documentation

| Document | Contenu |
|---|---|
| [Installation](doc/fr/installation.md) | Les prérequis, l'installation et le branchement. |
| [Utilisation](doc/fr/usage.md) | Offrir une prime, les réclamations, les attributions et le règlement d'une prime. |
| [Séquestre](doc/fr/escrow.md) | Comment les points sont détenus, payés et rendus, et un séquestre sur `tilttek/exchange-bundle`. |
| [Vie privée](doc/fr/privacy.md) | Effacer un membre : il quitte chaque prime, puis un pseudonyme prend sa place. |
| [Célébrations](doc/fr/celebrations.md) | Annoncer une prime attribuée, avec la célébration du kit PHIL UX. |
| [Points d'extension](doc/fr/extension-points.md) | Les ports, l'écriture d'un magasin, et les événements. |
| [Tests](doc/fr/testing.md) | Les tests de la bibliothèque, ses cibles de fuzzing et ses bancs d'essai. |
| [Référence](doc/fr/reference.md) | Chaque type de la bibliothèque, avec son rôle, et ses exceptions. |

## Démarrage rapide

```php
use Phil\Resource\ResourceReference;
use Tilttek\Bounties\Bounties;
use Tilttek\Bounties\BountyDraft;
use Tilttek\Bounties\BountyReason;
use Tilttek\Bounties\Prize;

$bounties = new Bounties($store, $escrow, $eventDispatcher);

$bounties->offer(new BountyDraft('01JD4', ResourceReference::of('question', '42'), BountyReason::Detail, new Prize(100), new DateTimeImmutable('+7 days')), $ana);
$bounties->claim('01JD4', 'answer-901', $bea, 'answer:901');
$bounties->award('01JD4', 'award-1', 'answer-901', 100, $ana);   // the whole amount: Bea receives 100 points at once
```

## Prérequis

PHP 8.5 ou une version plus récente avec `sodium`, `phil/resource` 0.1, `tilttek/points` 0.1 ou 0.2, `psr/clock`,
`psr/event-dispatcher` et `symfony/clock`.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt de PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et
le [code de conduite](CODE_OF_CONDUCT.md), en anglais. Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Tilttek project. Voir [LICENSE](LICENSE).
