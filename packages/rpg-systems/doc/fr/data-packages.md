<!-- phil-dev source-sha256=d2610f1313d9024b92285fc716ca1a6991d601753f357e6a0787d4862eb2c7f8 -->
# Paquets de données

Un paquet de données porte une source ouverte, dans une version, déjà importée dans le format de la bibliothèque : un
paquet Composer qu'une application requiert au lieu d'importer elle-même la source. Il contient les documents du
système, un manifeste qui nomme la source, sa licence et l'attribution qu'une page affiche, l'empreinte de chaque
document, et les fichiers de l'éditeur dont les documents ont été importés, pour que chacun puisse vérifier l'import
d'après les fichiers mêmes de l'éditeur.

## Nom

Un paquet par source et par version, nommé d'après la source et sa version :

| Source | Paquet |
|---|---|
| System Reference Document 5.1 | `vendor/rpg-data-srd-5.1` |
| System Reference Document 5.2 | `vendor/rpg-data-srd-5.2` |
| Pathfinder Second Edition | `vendor/rpg-data-pf2e` |
| Fate Core System | `vendor/rpg-data-fate-core` |

## Paquets publiés

Quatre paquets de données sont publiés ; trois sont importés du fichier de leur éditeur, dont leur manifeste nomme
l'adresse, la taille et l'empreinte SHA-256 :

| Paquet | Système | Version | Licence | Enregistrements |
|---|---|---|---|---|
| `tilttek/rpg-data-srd-5.1` | `srd-5.1` | `5.1` | `CC-BY-4.0` | 6 attributs ; 18 compétences, 15 états, 13 types de dégâts et 8 écoles de magie ; 3 expressions de dés ; 2 variantes de règles ; les nombres de difficulté `check` |
| `tilttek/rpg-data-srd-5.2` | `srd-5.2` | `5.2.1` | `CC-BY-4.0` | 6 attributs ; 18 compétences, 15 états, 13 types de dégâts et 8 écoles de magie ; 3 expressions de dés ; les nombres de difficulté `check` et `encounter` |
| `tilttek/rpg-data-fate-core` | `fate-core` | `2013` | `CC-BY-3.0` | 18 compétences, 6 approches de Fate Accelerated et les 11 échelons de l'échelle ; 1 expression de dés |

Les variantes de règles et les nombres de difficulté sont ceux que déclare le profil d'importation, tels que la source les
donne ; aucune source ne déclare de rôles de groupe. Fate Core n'a pas de version numérotée : la version de son paquet
est l'année de son édition.

L'éditeur de Pathfinder Second Edition ne distribue aucun document de référence sous forme de fichier : les règles sous
la licence ORC se lisent dans ses livres, Pathfinder Player Core et Pathfinder GM Core, dont l'année de droit d'auteur
est la version du paquet. Son paquet contient les enregistrements que déclare son profil d'importation — les jets, les
variantes de règles et les nombres de difficulté —, ne nomme aucun fichier amont, et porte dans son attribution l'avis
ORC et les crédits des deux livres ; son README et son `LICENSE` ajoutent les autres avis que demande la licence ORC. Il
ne contient aucun attribut ni aucun terme : le profil les lit dans une source, ce que ce paquet ne revendique pas. Son
`composer.json` déclare la licence `LicenseRef-ORC`, la licence ORC n'ayant aucun identifiant SPDX.

Une nouvelle version d'une source qui change ses règles — de 5.1 à 5.2 — est un nouveau paquet, pour que l'application
choisisse les règles par lesquelles elle joue ; une correction de la même version est une nouvelle version du même
paquet.

## Structure

```text
rpg-data-srd-5.2/
├── composer.json      the package, under the licence of the material
├── rpg-data.json      the manifest
├── system.json        the system, in the library's format
├── ATTRIBUTION.txt    the notices a page that shows the material displays
└── LICENSE            the text of the material's licence
```

Le `composer.json` d'un paquet de données porte la licence du contenu, non celle de la bibliothèque :

```json
{
    "name": "vendor/rpg-data-srd-5.2",
    "description": "The System Reference Document 5.2, in the format of tilttek/rpg-systems",
    "license": "CC-BY-4.0",
    "type": "tilttek-rpg-data",
    "require": {
        "tilttek/rpg-systems": "^0.1"
    }
}
```

## Le manifeste

```json
{
    "format": "tilttek-rpg-data/1",
    "name": "vendor/rpg-data-srd-5.2",
    "system": "srd-5.2",
    "version": "5.2.1",
    "source": {
        "title": "System Reference Document 5.2.1",
        "licence": "CC-BY-4.0",
        "attribution": "The attribution statement the source asks for, copied from the source itself.",
        "author": "Its publisher",
        "url": "https://the-address-of-the-source.example"
    },
    "documents": {
        "system.json": "the SHA-256 digest of system.json"
    },
    "upstream": [
        {
            "title": "SRD 5.2.1, English",
            "url": "https://the-address-of-the-file.example/srd.pdf",
            "sha256": "the SHA-256 digest of the file",
            "bytes": 6031375,
            "locale": "en"
        }
    ]
}
```

| Champ | Obligatoire | Ce qu'il contient |
|---|---|---|
| `format` | Oui | `tilttek-rpg-data/1` |
| `name` | Oui | Le nom Composer du paquet |
| `system` | Oui | La clé du seul système que les documents contiennent |
| `version` | Oui | La version de la source ; la version du système doit être la même |
| `source` | Oui | La provenance du système, comme l'objet `source` d'un document la contient ([Sources et attribution](attribution.md)) |
| `documents` | Oui | De 1 à 64 documents du format, chacun avec son empreinte SHA-256, par son chemin relatif au paquet |
| `upstream` | Non | Jusqu'à 64 fichiers de l'éditeur, chacun avec son titre, son adresse, son empreinte, sa taille et sa langue |

Le chemin d'un document se compose de segments de lettres, de chiffres, de `.`, de `_` et de `-`, aucun ne commençant
par un point, huit au plus, et se termine par `.yaml`, `.yml` ou `.json` : aucun chemin absolu, aucun `..`. La licence
de la source doit être une de la liste fermée ; un contenu sous toute autre licence est refusé comme il l'est dans un
document.

## Écrire un paquet

L'importateur donne le système, l'écrivain donne le paquet :

```php
$system = new Importer()->importText(ImportProfile::srd52()->withVersion('5.2.1'), $extract, Syntax::Json, $srd);

new DataPackageWriter()->write('build/rpg-data-srd-5.2', 'vendor/rpg-data-srd-5.2', $system, [
    new UpstreamFile('SRD 5.2.1, English', $address, hash_file('sha256', $pdf), filesize($pdf), 'en'),
]);
```

`write()` écrit `system.json`, `rpg-data.json` et `ATTRIBUTION.txt` dans un répertoire existant, en les remplaçant ;
`manifest()` donne le manifeste sans rien écrire, et `json()` son texte. Un système sans version est refusé : un paquet
de données contient une version de sa source, et `ImportProfile::withVersion()` donne au système la version des
fichiers dont il est importé — `5.2.1` pour le SRD 5.2. Le `composer.json` et le `LICENSE` sont écrits par le mainteneur du
paquet.

## Charger les paquets installés

Le `composer.json` d'un paquet de données déclare le type `tilttek-rpg-data`. `InstalledDataPackages` trouve les paquets
de ce type que Composer a installés, par leur nom, et lit chacun avec le lecteur strict : requérir un paquet est tout ce
que fait une application pour connaître son système.

```shell
composer require tilttek/rpg-data-srd-5.2
```

```php
use Tilttek\RpgSystems\Package\InstalledDataPackages;

$packages = new InstalledDataPackages();

$packages->names();                              // ["tilttek/rpg-data-srd-5.2"], in alphabetical order
$srd = $packages->load('tilttek/rpg-data-srd-5.2');
$systems = $packages->systems()->with($ownSystem);
```

`load()` lève `UnknownRecord` pour un paquet qui n'est pas installé, et `InvalidDocument` pour un paquet installé sans
répertoire, dont le manifeste déclare un autre nom que celui sous lequel il est installé, ou que le lecteur refuse ;
`systems()` lève `InvalidRecord` quand deux paquets contiennent un système de la même clé. Les paquets sont trouvés et
relus à chaque appel : une application qui les charge à chaque démarrage garde dans son cache les `Systems` qu'elle a lus.

Le constructeur prend le lecteur — avec la limite de ses manifestes — et, à la place de la liste de Composer, les paquets
et leurs répertoires par nom, pour un paquet gardé hors de `vendor/` ou pour un test :

```php
$packages = new InstalledDataPackages(installed: ['acme/rpg-data-open-ranges' => __DIR__.'/data/open-ranges']);
```

## Lire un paquet

```php
$reader = new DataPackageReader();

$system = $reader->load('vendor/vendor/rpg-data-srd-5.2');
$package = $reader->manifest('vendor/vendor/rpg-data-srd-5.2');
```

`load()` lit le manifeste, puis chaque document, et refuse le paquet — avec une `InvalidDocument` qui nomme l'endroit —
quand :

- un document n'est pas un fichier à l'intérieur du paquet, un lien qui en sort y compris ;
- un document ne correspond pas à son empreinte : il a changé après l'écriture du manifeste ;
- les documents ne contiennent pas exactement un système, de la clé du manifeste ;
- la version du système n'est pas celle du manifeste ;
- la source du système n'est pas celle du manifeste.

Le manifeste est lu comme du JSON, de 100 000 octets au plus — la limite est l'argument du lecteur —, strictement : un
champ inconnu est refusé avec son endroit. Les documents sont lus par `SystemReader`, avec ses propres limites
([Le format](format.md#la-lecture)).

## Ce que la bibliothèque contient

La bibliothèque ne contient aucune donnée de jeu : les enregistrements d'une source — ses attributs, termes, jets,
variantes de règles et nombres de difficulté, avec leurs textes — viennent dans son paquet de données, sous la licence du
contenu. Seuls les profils d'importation sont intégrés : ils disent comment un extrait de chaque source devient un système
et énoncent les enregistrements que la source donne une fois pour toutes — les jets nommés, les variantes de règles et les
nombres de difficulté, sans leurs textes au-delà de leurs noms —, de sorte qu'un paquet de données se construit à partir
d'eux ([Importer des sources ouvertes](importing.md)). La bibliothèque ne récupère rien : l'application, ou le mainteneur
d'un paquet de données, obtient les fichiers de la source auprès de leur éditeur et vérifie leur licence avant de les
importer.
