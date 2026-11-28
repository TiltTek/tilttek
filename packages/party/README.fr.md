<!-- phil-dev source-sha256=7c9f8f989e1a41530f79d5ca66665c1769140c64be266ae4410111f7ea3c2669 -->
# tilttek/party

**Le groupe d'une campagne : ses membres et leurs rôles, la bourse, le butin et les notes qu'ils partagent, et la
comparaison de sa composition avec les rôles que déclare le système de jeu.**

tilttek/party garde le groupe de personnages qui joue une campagne ensemble. L'application forme un groupe pour une
campagne sous une référence qu'elle donne, `party:7`, avec la clé de son système de jeu, `srd-5.2` ; les personnages
le rejoignent sous leurs propres références, `character:12`, chacun avec les rôles qu'il remplit — soins, défense,
dégâts — par les clés sous lesquelles le système les déclare, ou sans aucun : les rôles sont facultatifs, et un jeu sans classes
ni métiers se joue sans rôle. Un membre qui manque quelques séances est mis en
absence : il garde sa place, ses rôles et le butin qu'il porte, et ne compte nulle part jusqu'à son retour.

Ce que le groupe détient en commun vit à côté de ses membres. Sa **bourse** contient des pièces entières par devise —
`gp`, `sp`, `credits` —, jamais de l'argent réel ; un dépôt ou un retrait ne la rend jamais négative, et un
**partage** divise une devise également entre les membres actifs, ou entre les membres choisis, le reste demeurant dans
la bourse. Son **butin** est une liste d'entrées sous des clés, chacune avec une quantité, portée par un membre ou
déposée dans la réserve. Ses **notes** sont les pistes, les noms et les plans que ses membres écrivent pour les
autres.

Sa **composition** est lue d'après les rôles d'un système — un `RoleCatalogue` construit à partir des rôles de groupe d'un
système de `tilttek/rpg-systems`, ou fourni par l'application — et présentée comme un conseil, jamais comme un refus : quels rôles manquent, lesquels sont surchargés au-delà de leur juste part, quels membres ne remplissent
aucun rôle du système, et quels rôles remplis par des membres le système ne déclare pas.

Le paquet s'adresse à un gestionnaire de campagne, à une table virtuelle ou à un site de jeu. C'est une bibliothèque
PHP simple dont les dépendances sont `phil/resource`, la référence opaque d'un groupe, d'une campagne ou d'un membre,
`psr/clock`, et `tilttek/rpg-systems`, dont les termes donnent les rôles. Les membres sont des références que donne
l'application : la bibliothèque ne lit jamais un nom, un courriel ni un compte, et ses événements ne portent que des
identifiants.

## État

| Partie | État |
|---|---|
| `Party`, `Member`, `Standing` : les groupes d'une campagne, leurs membres, rôles et statut | Implémenté et testé |
| `Parties` : former, renommer, changer de système, dissoudre, rejoindre, quitter, attribuer des rôles, fixer le statut, lire, effacer | Implémenté et testé |
| `Purse`, `Split` : les pièces communes, les dépôts, les retraits, les parts et leur distribution | Implémenté et testé |
| `LootItem` : le butin partagé, sa quantité, son porteur ou la réserve | Implémenté et testé |
| `Note` : les notes que partagent les membres, avec leur auteur et leurs moments | Implémenté et testé |
| `Composition\RoleCatalogue`, `Composition\CompositionAdvisor`, `Composition\Composition` : la composition d'après les rôles du système | Implémenté et testé |
| `Contract\PartyStore`, avec `Store\InMemoryPartyStore` | Implémenté et testé |
| `Contract\PartyObserver`, `PartyChanged` : ce qui est communiqué, identifiants seulement | Implémenté et testé |
| `Limits` : membres par groupe, groupes par membre, entrées de butin et notes par groupe | Implémenté et testé |
| Un bundle Symfony : dépôt Doctrine, services, formulaires et traductions | Non commencé |

## Documentation

| Document | Contenu |
|---|---|
| [Installation](doc/fr/installation.md) | Les prérequis, l'installation et le câblage. |
| [Groupes et membres](doc/fr/parties.md) | Former, rejoindre, les rôles, le statut, quitter, dissoudre et l'effacement. |
| [Ce que le groupe partage](doc/fr/shared.md) | La bourse et ses parts, le butin et ses porteurs, les notes. |
| [Composition](doc/fr/composition.md) | Les rôles d'un système, la juste part, les rôles manquants et surchargés. |
| [Points d'extension](doc/fr/extension-points.md) | Le dépôt de stockage, les observateurs, les limites et l'horloge. |
| [Exceptions](doc/fr/exceptions.md) | `PartyException` et ses exceptions, avec les refus et leurs messages. |
| [Tests](doc/fr/testing.md) | Les tests de la bibliothèque, et comment tester le code qui utilise des groupes. |
| [Référence](doc/fr/reference.md) | Chaque type public, avec son rôle. |

## Démarrage rapide

```sh
composer require tilttek/party
```

```php
use Phil\Resource\ResourceReference;
use Tilttek\Party\Composition\RoleCatalogue;
use Tilttek\Party\Parties;
use Tilttek\Party\Store\InMemoryPartyStore;

$parties = new Parties(new InMemoryPartyStore(), $clock);

$party = ResourceReference::of('party', '7');
$ada = ResourceReference::of('character', '12');
$bo = ResourceReference::of('character', '13');

$parties->form($party, ResourceReference::of('campaign', '3'), 'The Lantern Bearers', 'srd-5.2');
$parties->join($party, $ada, ['role.healer']);
$parties->join($party, $bo, ['role.healer']);

$parties->deposit($party, 'gp', 251);
$parties->distribute($party, 'gp')->share;            // 125 each, 1 left in the purse
$parties->addLoot($party, 'loot.rope', 'Silk rope', 1, $bo);
$parties->writeNote($party, 'lead.mill', $ada, 'The miller lies.');

$composition = $parties->composition($party, RoleCatalogue::fromSystem($system));
$composition->missing();                              // the roles no active member fills, none for a game without roles
```

## Prérequis

PHP 8.5 ou une version plus récente, avec `ext-mbstring`.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt de PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et
le [code de conduite](CODE_OF_CONDUCT.md), en anglais. Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Tilttek project. Voir [LICENSE](LICENSE).
