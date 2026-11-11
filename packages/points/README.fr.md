<!-- phil-dev source-sha256=6392bdc92e7b89e563c4aa6223fb417cba0596f576f14bae53eceff8a8783867 -->
# tilttek/points

**Les points que vos membres gagnent, dépensent et donnent, gardés dans un registre où chaque point vient de quelque
part et va quelque part.**

tilttek/points garde les points des membres dans un registre en partie double. Chaque mouvement débite un compte et en
crédite un autre : le compte d'un membre, le compte d'où une règle émet, le compte d'une récompense, le compte des
points expirés. Un solde est donc toujours la somme des mouvements, jamais un champ que quelqu'un écrit. Des règles de
gain transforment l'activité des membres, enregistrée par `tilttek/activity`, en points (*dix points par partie,
cinquante au plus par semaine*), dans une ou plusieurs devises que nomme l'application. Une activité annulée annule ce
qu'elle a rapporté. Les points peuvent expirer, premiers entrés, premiers sortis. Une dépense ou un transfert ne rend
jamais un solde négatif, alors qu'une annulation qui le fait est écrite telle quelle. Quand une règle change, les
points tirés de l'activité sont recalculés à partir du journal sur la période que choisit l'application. Les
mouvements décidés par des personnes (dépenses, transferts, attributions) restent tels quels. Effacer un membre
supprime ses mouvements et garde les points des autres membres. Ce n'est pas de l'argent : les points ne sont jamais
convertis en devise monétaire, et rien ici ne traite un paiement.

Elle s'adresse à une application qui récompense ce que font ses membres (un site communautaire, le programme de
fidélité d'une boutique, un site de jeux) et qui veut des soldes qu'elle peut expliquer ligne par ligne.
`tilttek/points-bundle` l'intègre à Symfony et garde le registre dans Doctrine DBAL. Une application sans Symfony prend
la bibliothèque seule et écrit son propre `Ledger`, ou utilise celui en mémoire.

C'est une bibliothèque PHP simple dont les dépendances sont `tilttek/activity`, le journal que lisent les règles,
`phil/resource`, la référence opaque d'un membre, et `psr/clock`, l'horloge qui date chaque mouvement. Les membres sont
des références que donne l'application, `member:44` : la bibliothèque ne lit jamais un nom, un courriel ou un compte.

La conception est petite : un `Account` et un `Movement` entre deux comptes, gardés sous forme de lignes `LedgerEntry`
par un `Ledger`. `Points` lit les soldes et les historiques et écrit ce que décide l'application. `PointsProjection`
lit le journal d'activité par des `EarningRule`. `Lots` suit ce qui reste de chaque crédit pour l'expiration.

## État

| Partie | État |
|---|---|
| `Account`, `Currency`, `Currencies`, `MovementKey`, `Movement`, `LedgerEntry` : les valeurs du registre, vérifiées | Implémenté et testé |
| `Ledger` et `InMemoryLedger` : écrits une fois par clé, paiements couverts, soldes, net entre deux comptes, relevés, reconstructions, effacement | Implémenté et testé |
| `Points` : soldes, historiques, attributions, révocations, dépenses, remboursements, transferts, expiration, effacement | Implémenté et testé |
| `EarningRule`, `Cap`, `Period`, `Rules` : points par geste, plafonds par membre et par période dans un fuseau horaire, conditions de contexte | Implémenté et testé |
| `PointsProjection` : l'activité transformée en points, annulations renversées, reconstructions | Implémenté et testé |
| `Lots` et `Lot` : expiration premiers entrés, premiers sortis, dettes payées par les crédits suivants | Implémenté et testé |
| Exceptions sous une seule interface `PointsException` | Implémenté et testé |

## Documentation

| Document | Contenu |
|---|---|
| [Installation](doc/fr/installation.md) | Prérequis et installation. |
| [Le registre](doc/fr/ledger.md) | Comptes, mouvements, clés, soldes et historiques. |
| [Gagner des points](doc/fr/earning.md) | Règles de gain, plafonds, conditions, la projection, les annulations et les reconstructions. |
| [Dépenser et donner](doc/fr/spending.md) | Attributions, révocations, dépenses, remboursements, transferts et le solde qui ne devient jamais négatif. |
| [Expiration](doc/fr/expiry.md) | Durées de vie, premiers entrés, premiers sortis, dettes, et la tâche qui fait expirer les points de chaque membre. |
| [Effacement](doc/fr/erasure.md) | Ce qu'effacer un membre retire et ce que cela garde. |
| [Écrire un registre](doc/fr/writing-a-ledger.md) | Le contrat `Ledger` que le stockage d'une application implémente. |
| [Points d'extension](doc/fr/extension-points.md) | Ce qu'une application donne à la bibliothèque : le registre et l'horloge. |
| [Exceptions](doc/fr/exceptions.md) | `PointsException` et ses exceptions, avec leurs messages. |
| [Tests](doc/fr/testing.md) | Les tests de la bibliothèque et sa barre de mutation, et comment tester du code qui déplace des points. |
| [Référence](doc/fr/reference.md) | Chaque type de la bibliothèque, avec son rôle. |

## Démarrage rapide

```sh
composer require tilttek/points
```

```php
use Phil\Resource\ResourceReference;
use Symfony\Component\Clock\NativeClock;
use Tilttek\Points\Currencies;
use Tilttek\Points\Currency;
use Tilttek\Points\Ledger\InMemoryLedger;
use Tilttek\Points\Points;

$points = new Points(new InMemoryLedger(), new NativeClock(), new Currencies(new Currency('points')));
$member = ResourceReference::of('member', '44');

$points->grant($member, 50, 'welcome:44');
$points->spend($member, 30, 'order:2026-0193:redeem', 'order:2026-0193');
$points->balance($member); // 20
```

## Prérequis

PHP 8.5 ou plus récent.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt de PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et le
[code de conduite](CODE_OF_CONDUCT.md). Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Tilttek project. Voir [LICENSE](LICENSE).
