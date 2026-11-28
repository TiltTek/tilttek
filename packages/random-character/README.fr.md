<!-- phil-dev source-sha256=43536acbb46abe1cf599d510391622d9a20b1e2f5227c273d4c3a0eaa4e49042 -->
# tilttek/random-character

**Un personnage prêt à jouer, tiré selon les règles de création d'un système de jeu — des attributs dans leurs bornes,
des noms et des traits tirés de tables —, rejouable par sa graine et modifiable ensuite.**

tilttek/random-character tire des personnages selon les règles de création qu'un système déclare. Les règles remplissent
les attributs d'un système de `tilttek/rpg-systems` — 3d6 pour chacun dans l'ordre, 4d6 en retirant le plus bas distribués
au hasard, ou un tableau fixe distribué au hasard — et refusent une valeur hors des bornes que le système fixe. Elles
ajoutent des champs, tirés dans l'ordre : un nom, une lignée, un passé ou deux traits différents tirés des générateurs de
`tilttek/generator`, un âge ou un or de départ lancé avec les dés de `tilttek/dice`. Un champ peut tirer d'un générateur
nommé par les champs qui le précèdent : `names.{lineage}` tire de `names.river` quand la lignée est sortie `river`, et
`names.{lineage.key}` lit une valeur d'un résultat structuré. Avant le tirage, les règles sont vérifiées contre le système
et les générateurs — attributs inconnus, valeurs fixes hors de leurs bornes, générateurs qui n'existent pas — et chaque
problème est listé.

Chaque personnage porte une graine, celle de l'appelant ou une graine tirée, et la même graine avec les mêmes règles et
les mêmes tables redonne le même personnage. Chaque partie d'un personnage tire d'une graine qui lui est propre, dérivée
de celle du personnage : un champ ajouté aux règles laisse les statistiques et les autres champs tels quels. Un personnage
est une valeur immuable : une modification en donne un nouveau, la graine conservée et la valeur marquée comme modifiée à
la main, prêt à être repris par une fiche de personnage. Les attributions des tables où les champs ont tiré l'accompagnent,
chacune une seule fois.

Les règles s'écrivent dans un document YAML ou JSON qui nomme un système une fois et laisse chaque jeu de règles déclarer
le sien ; chaque refus nomme son emplacement, `rules[0].fields[2].count`. Un tirage est communiqué à un observateur par les
clés des règles et du système, jamais par la graine ni par les valeurs.

Le paquet sert une application qui offre un bouton « tire-moi un personnage » — un outil de campagne, un générateur de
partie unique, un meneur de jeu qui a besoin d'un inconnu pour ce soir. Il ne conserve pas les personnages et ne les
affiche pas : l'application détient ses règles, ses tables et ses fiches. C'est une bibliothèque PHP simple dont les
dépendances sont `tilttek/rpg-systems`, qui donne les attributs et leurs bornes, `tilttek/generator`, qui tire les champs,
`tilttek/dice`, qui lance les statistiques et les champs lancés, et `symfony/yaml`, qui lit les documents YAML. Chaque
service ne garde rien entre deux appels et sert toutes les requêtes d'un *worker*.

## État

| Partie | État |
|---|---|
| `CreationRules`, `Stats`, `StatMethod`, `Field`, `FieldKind` : jusqu'à 32 attributs et 64 champs, par lancer, tableau lancé ou tableau fixe ; champs tirés ou lancés, jusqu'à 16 valeurs chacun | Implémenté et testé |
| Générateurs nommés par les champs précédents : `names.{lineage}`, `names.{lineage.key}` | Implémenté et testé |
| `CharacterRoller` : règles vérifiées contre le système et les générateurs, personnages tirés dans les bornes du système, à partir d'une graine donnée ou tirée | Implémenté et testé |
| `RandomCharacter` : le personnage, sa graine, ses attributions, ses modifications marquées par valeur | Implémenté et testé |
| `RulesCatalogue` : jusqu'à 1 000 jeux de règles, par clé et par système | Implémenté et testé |
| `Format\RulesReader`, `Format\Syntax` : les documents YAML et JSON, chaque refus à son emplacement | Implémenté et testé, fuzzé |
| `Contract\CharacterObserver`, `Event\CharacterRolled` : chaque tirage communiqué par des identifiants | Implémenté et testé |
| Exceptions sous une seule interface `RandomCharacterException` | Implémenté et testé |
| Achat de points et nouveaux tirages sous un total minimal | Non commencé |
| Le bundle Symfony : les règles comme service, le bouton de tirage | Non commencé |

## Documentation

| Document | Contenu |
|---|---|
| [Installation](doc/fr/installation.md) | Les prérequis et l'installation. |
| [Règles de création](doc/fr/rules.md) | Les statistiques et leurs méthodes, les champs, les générateurs nommés par des champs précédents, et le catalogue. |
| [Le format](doc/fr/format.md) | Le document YAML et JSON des règles de création d'un système, champ par champ. |
| [Tirage](doc/fr/rolling.md) | La vérification des règles, le tirage, les graines, les bornes, les attributions, les modifications et l'observateur. |
| [Points d'extension](doc/fr/extension-points.md) | Ce qu'une application donne à la bibliothèque, et où la bibliothèque s'arrête. |
| [Exceptions](doc/fr/exceptions.md) | `RandomCharacterException` et ses exceptions, avec leurs messages. |
| [Tests](doc/fr/testing.md) | Les tests de la bibliothèque, sa cible de fuzzing et ses bancs d'essai. |
| [Référence](doc/fr/reference.md) | Chaque type de la bibliothèque, avec son rôle. |

Le répertoire `examples/` contient les règles d'un petit système, `lanterns.yaml`, les tables où elles tirent,
`lanterns-tables.yaml`, et les attributs du système, `lanterns-system.yaml`.

## Démarrage rapide

```sh
composer require tilttek/random-character
```

```php
use Tilttek\Generator\Generator;
use Tilttek\Generator\Loading\FileLoader;
use Tilttek\RandomCharacter\CharacterRoller;
use Tilttek\RandomCharacter\Format\RulesReader;
use Tilttek\RpgSystems\Format\SystemReader;

$systems = new SystemReader()->load('examples/lanterns-system.yaml');
$generator = new Generator(new FileLoader()->catalog(['examples/lanterns-tables.yaml']));
$rules = new RulesReader()->load('examples/lanterns.yaml');
$roller = new CharacterRoller($systems, $generator);

$character = $roller->roll($rules->get('lanterns.wanderer'));
$character->stats;                  // ['grit' => 3, 'wits' => 2, 'glow' => 4]
$character->field('name');          // "Kesh", from the list of the lineage "ash"
$character->seed;                   // 32 hexadecimal digits: keep them to roll the same character again

$roller->roll($rules->get('lanterns.wanderer'), $character->seed)->toArray() === $character->toArray();   // true
$character->withField('name', 'Kestrel')->edited;                                                      // ['fields.name']
```

## Prérequis

PHP 8.5 ou une version plus récente, avec l'extension `mbstring`.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt de PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et
le [code de conduite](CODE_OF_CONDUCT.md), en anglais. Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Tilttek project. Voir [LICENSE](LICENSE).
