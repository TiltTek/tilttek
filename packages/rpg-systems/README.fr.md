<!-- phil-dev source-sha256=b61a7d46beaed1e544fa13179b5c70bc5b79d1515d267f600c74d136710da818 -->
# tilttek/rpg-systems

**Décrivez un système de jeu de rôle une seule fois pour que tous les outils le lisent : attributs, jets, termes,
traductions, et la source et la licence de chaque enregistrement.**

tilttek/rpg-systems décrit un système de jeu de rôle comme les autres outils d'un site en ont besoin : une feuille de
personnage lit ses attributs et leurs bornes, un lanceur de dés ses jets nommés, une consultation de règles ou un
générateur ses termes par catégorie, chacun dans la langue du lecteur. Un système contient ses noms et sa version, ses
attributs — Force, `STR`, de 1 à 30 —, ses expressions de dés dans la notation canonique de `tilttek/dice` — le test
`1d20`, l'avantage `2d20kh1` —, vérifiées et normalisées à leur lecture, ses termes — états, compétences, types de dégâts,
traits —, les variantes de règles qu'une table peut choisir, ses nombres de difficulté — les classes d'un test, le
budget d'une rencontre — et les rôles qu'il déclare pour un groupe, chacun avec des noms et des descriptions par locale. Chaque enregistrement porte sa provenance : le
titre de sa source, qui la détient, où elle est publiée, sa licence et l'attribution que la licence demande ; une
traduction porte la sienne, puisqu'une traduction est une œuvre à part.

Les systèmes sont écrits dans le format de la bibliothèque, un document YAML ou JSON qui fixe une source une fois pour
tout le document et laisse un enregistrement énoncer la sienne ; un document de localisation superpose les textes d'une
langue de plus à un système. Le moteur d'attribution donne les mentions qu'une page affiche — chaque source dont la
licence en demande une, une seule fois, avec la licence et son adresse —, en texte ou en liste HTML sûre à imprimer.
L'importateur transforme un extrait d'une source ouverte en système du format : les System Reference Documents 5.1 et
5.2 sous CC BY 4.0, Pathfinder Second Edition sous la licence ORC et Fate Core sous CC BY 3.0 ont chacun un profil — avec les variantes de règles et les nombres de difficulté que
donne chaque source —, et un profil propre à l'application lit un extrait de forme différente. Un paquet de données porte une source dans une
version, déjà importée, avec un manifeste qui nomme sa source, sa licence et son attribution, l'empreinte de chaque
document et les fichiers de l'éditeur dont il vient ; le lecteur refuse un paquet dont les documents ont changé ou dont
le système n'est pas celui que son manifeste nomme ; les paquets que Composer a installés se chargent par leur nom. La
bibliothèque ne contient aucune donnée de jeu : le SRD 5.1, le SRD 5.2.1 et Fate Core sont publiés comme des paquets de
données à part. La liste des licences est fermée : un contenu sous toute autre
licence est refusé plutôt que repris sans être lu.

Il s'adresse à une application dont les outils partagent des systèmes de jeu — un site communautaire, un outil de
campagne, une plateforme d'édition — et qui doit montrer d'où vient chaque morceau de texte de règles. C'est une
bibliothèque PHP simple dont les dépendances sont `tilttek/dice`, qui lit les jets, `symfony/yaml`, qui lit les
documents YAML, et `phil/url`, qui lit l'adresse d'une source ; l'API d'exécution de Composer donne la liste des paquets de données qu'il a installés.

## État

| Partie | État |
|---|---|
| `GameSystem`, `Systems` : un système, ses enregistrements cherchés par clé et par catégorie, les systèmes d'une application | Implémenté et testé |
| `Attribute`, `DiceExpression`, `Term`, `Translations` : des enregistrements avec des textes par locale, des bornes, et des jets vérifiés par `tilttek/dice` | Implémenté et testé |
| `RuleVariant`, `Difficulty`, `DifficultyBand`, `PartyRole` : les variantes qu'une table peut choisir, les nombres de difficulté par palier et par table, les rôles d'un groupe | Implémenté et testé |
| `Provenance`, `Licence` : la source, la licence et l'attribution de chaque enregistrement, d'après une liste fermée de licences | Implémenté et testé |
| `Localisation` : les textes d'une langue de plus superposés à un système, avec la source de la traduction | Implémenté et testé |
| `Format\SystemReader`, `Format\SystemWriter`, `Format\Syntax` : le format YAML et JSON, lu et réécrit | Implémenté et testé |
| `Attribution\AttributionRenderer` : les mentions d'un système ou d'une page, en texte ou en HTML | Implémenté et testé |
| `Import\Importer`, `Import\ImportProfile`, `Import\TableMapping` : des extraits du SRD 5.1 et 5.2, de Pathfinder Second Edition, de Fate Core, ou lus par un profil propre à l'application | Implémenté et testé |
| `Package\DataPackage`, `Package\DataPackageReader`, `Package\DataPackageWriter`, `Package\UpstreamFile` : les paquets de données, une source dans une version, vérifiés d'après leur manifeste | Implémenté et testé |
| `Package\InstalledDataPackages` : les paquets de données que Composer a installés, chargés par nom en systèmes | Implémenté et testé |
| Les paquets de données `tilttek/rpg-data-srd-5.1`, `tilttek/rpg-data-srd-5.2` et `tilttek/rpg-data-fate-core`, importés d'après les fichiers des éditeurs | Construits et relus par les tests ; des paquets à part, chacun avec ses tests |
| Le paquet de données `tilttek/rpg-data-pf2e`, les jets, les variantes de règles et les nombres de difficulté de Pathfinder Second Edition sous la licence ORC, sans fichier amont | Construit et relu par les tests ; un paquet à part, avec ses tests |
| Exceptions sous une seule interface `RpgSystemsException` | Implémenté et testé |

## Documentation

| Document | Contenu |
|---|---|
| [Installation](doc/fr/installation.md) | Les prérequis et l'installation. |
| [Systèmes et enregistrements](doc/fr/systems-and-records.md) | Les systèmes, attributs, expressions de dés, termes, variantes de règles, nombres de difficulté, rôles de groupe, traductions, et leur recherche. |
| [Le format](doc/fr/format.md) | Les documents YAML et JSON d'un système et d'une localisation, champ par champ. |
| [Sources et attribution](doc/fr/attribution.md) | Les provenances, les licences et les mentions qu'une page affiche. |
| [Importer des sources ouvertes](doc/fr/importing.md) | Les profils du SRD 5.1, du SRD 5.2, de Pathfinder Second Edition et de Fate Core, la forme d'un extrait, et un profil à vous. |
| [Paquets de données](doc/fr/data-packages.md) | Une source ouverte dans une version, déjà importée : son nom, sa structure, son manifeste, son écriture et sa lecture. |
| [Points d'extension](doc/fr/extension-points.md) | Ce qu'une application donne à la bibliothèque. |
| [Exceptions](doc/fr/exceptions.md) | `RpgSystemsException` et ses exceptions, avec leurs messages. |
| [Tests](doc/fr/testing.md) | Les tests de la bibliothèque, sa cible de fuzzing et ses bancs d'essai. |
| [Référence](doc/fr/reference.md) | Chaque type de la bibliothèque, avec son rôle. |

## Démarrage rapide

```php
use Tilttek\RpgSystems\Attribution\AttributionRenderer;
use Tilttek\RpgSystems\Format\SystemReader;

$systems = new SystemReader()->load('systems/lanterns.yaml', 'systems/lanterns.es.yaml');
$lanterns = $systems->get('lanterns');

$lanterns->attribute('grit')->names->in('es');   // "Temple"
$lanterns->dice('desperate')->notation;          // "3d6kl2", ready for tilttek/dice
$lanterns->terms('condition');                   // the conditions, in their order

echo new AttributionRenderer()->html($lanterns, 'es');
```

Les deux documents sont dans [`examples/`](examples), avec deux petits extraits que l'importateur lit.

## Prérequis

PHP 8.5 ou une version plus récente avec `ext-mbstring` ; `tilttek/dice` 0.3, `symfony/yaml` 8.2 et `phil/url` 0.1,
qui demande `ext-intl` ; Composer 2.2 ou plus récent, dont l'API d'exécution liste les paquets de données installés.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt de PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et
le [code de conduite](CODE_OF_CONDUCT.md), en anglais. Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Tilttek project. Voir [LICENSE](LICENSE). La licence
couvre le code de la bibliothèque ; le contenu d'un système garde la licence que sa provenance énonce.
