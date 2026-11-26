<!-- phil-dev source-sha256=cc34d1432ea29219de5304fb3d61202db84a32a82fb28ec0345b1ff0f795bc6c -->
# tilttek/handouts

**Les documents remis aux joueurs pendant une partie : révélés à toute la table ou à certains joueurs — tout de suite,
à un moment fixé, ou sur un signal que donne le meneur de jeu —, cachés de nouveau, et ce que chaque joueur en a vu.**

tilttek/handouts garde les documents qu'un meneur de jeu prépare pour une table : la lettre trouvée sur un cadavre, la
carte du moulin, l'avis de recherche. L'application crée un document pour une table — une campagne, une séance, une
partie — sous une référence qu'elle donne, `handout:4`, avec un titre et un texte, des pièces jointes, ou les deux. Une
pièce jointe est un fichier du stockage de l'application, nommé par sa référence, `file:9`, avec son type de média et
une description qui le remplace ; la bibliothèque ne lit, ne stocke ni ne sert jamais le fichier, et refuse les types de
média que l'application n'autorise pas — le SVG est exclu par défaut.

Un document reste caché jusqu'à ce qu'il soit **révélé** : à toute la table, ou à des joueurs nommés par leurs
références. Une révélation peut aussi être **planifiée** d'avance : à un moment, que le planificateur de l'application
déclenche une fois venu, ou sur un **signal** — `crypt.opened` — que le meneur de jeu donne pendant la partie pour
révéler d'un coup tout ce qui l'attend. Un document peut être caché de nouveau, à tous ou à certains joueurs. Chaque
modification augmente sa **révision**, et chaque joueur marque ce qu'il ouvre : la vue du joueur liste les documents
qu'il voit, et dit s'il ne les a pas encore ouverts, s'il a vu une révision antérieure ou s'il a vu la révision
courante ; la vue du meneur de jeu liste qui a vu quelle révision, et quand.

Le paquet s'adresse à une table virtuelle, à un gestionnaire de campagne ou à un site de jeu. C'est une bibliothèque
PHP simple dont les dépendances sont `phil/resource`, la référence opaque d'un document, d'une table, d'un joueur ou
d'un fichier, et `psr/clock`. Les joueurs sont des références que donne l'application : la bibliothèque ne lit jamais un
nom, un courriel ni un compte, et ses événements ne portent que des identifiants.

## État

| Partie | État |
|---|---|
| `Handout`, `Attachment`, `MediaTypes` : les documents d'une table, leur texte, leurs fichiers et leurs révisions, les types de média autorisés | Implémenté et testé |
| `Handouts` : créer, modifier, supprimer, lire les documents d'une table | Implémenté et testé |
| `Audience`, `Disclosure` : révéler à toute la table ou à certains joueurs, cacher, lire l'audience, `canSee()` | Implémenté et testé |
| `Trigger`, `StagedReveal` : planifier une révélation à un moment ou sur un signal, annuler, `releaseDue()`, `cue()` | Implémenté et testé |
| `Sighting`, `Visible`, `Freshness` : ce que chaque joueur a vu, la vue du joueur, ce qui n'est pas vu | Implémenté et testé |
| `forget()` : un joueur effacé de toutes les tables | Implémenté et testé |
| `Contract\HandoutStore`, avec `Store\InMemoryHandoutStore` | Implémenté et testé |
| `Contract\HandoutObserver`, `HandoutChanged` : ce qui est communiqué, identifiants seulement | Implémenté et testé |
| `Limits` : documents par table, pièces jointes par document, audiences par document, révélations par déclenchement | Implémenté et testé |
| Un bundle Symfony : dépôt Doctrine, services, planificateur, mises à jour en direct, formulaires et traductions | Non commencé |

## Documentation

| Document | Contenu |
|---|---|
| [Installation](doc/fr/installation.md) | Les prérequis, l'installation et le câblage. |
| [Documents](doc/fr/handouts.md) | Créer, modifier et supprimer des documents, leurs pièces jointes et leurs types de média. |
| [Révélations](doc/fr/reveals.md) | Révéler à toute la table ou à certains joueurs, cacher, planifier à un moment ou sur un signal. |
| [Ce que les joueurs ont vu](doc/fr/sightings.md) | Marquer ce qui est ouvert, la vue du joueur, la vue du meneur de jeu, l'effacement. |
| [Points d'extension](doc/fr/extension-points.md) | Le dépôt de stockage, les observateurs, les limites, les types de média et l'horloge. |
| [Exceptions](doc/fr/exceptions.md) | `HandoutException` et ses exceptions, avec les refus et leurs messages. |
| [Tests](doc/fr/testing.md) | Les tests de la bibliothèque, et comment tester le code qui utilise des documents. |
| [Référence](doc/fr/reference.md) | Chaque type public, avec son rôle. |

## Démarrage rapide

```sh
composer require tilttek/handouts
```

```php
use Phil\Resource\ResourceReference;
use Tilttek\Handouts\Attachment;
use Tilttek\Handouts\Audience;
use Tilttek\Handouts\Handouts;
use Tilttek\Handouts\Store\InMemoryHandoutStore;
use Tilttek\Handouts\Trigger;

$handouts = new Handouts(new InMemoryHandoutStore(), $clock);

$table = ResourceReference::of('campaign', '3');
$letter = ResourceReference::of('handout', '4');
$map = ResourceReference::of('handout', '5');
$ada = ResourceReference::of('player', '12');

$handouts->create($letter, $table, 'The miller\'s letter', 'Meet me at the mill at midnight.');
$handouts->create($map, $table, 'The crypt', null, [
    new Attachment(ResourceReference::of('file', '9'), 'image/webp', 'A map of the crypt under the mill'),
]);

$handouts->reveal($letter, Audience::players($ada));          // only Ada reads the letter
$handouts->stage($map, Audience::everyone(), Trigger::cue('crypt.opened'));
$handouts->cue($table, 'crypt.opened');                        // everyone sees the map now

$handouts->markSeen($letter, $ada);
$handouts->unseen($table, $ada);                               // the map, not opened yet
```

## Prérequis

PHP 8.5 ou une version plus récente, avec `ext-mbstring`.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt de PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et
le [code de conduite](CODE_OF_CONDUCT.md), en anglais. Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Tilttek project. Voir [LICENSE](LICENSE).
