<!-- phil-dev source-sha256=e0aca6a74d16402c6c75649fe8ba7f4b5e76935cea8c662d116e6c3f259fa183 -->
# Le format

Un système s'écrit comme un document YAML ou JSON, avec les mêmes champs dans les deux. `SystemReader` le lit et
`SystemWriter` le réécrit ; un système écrit puis relu est égal au système d'origine.

## Un système

```yaml
format: tilttek-rpg-systems/1
locale: en
system:
  key: lanterns
  names: { en: Lanterns in the Dark, fr: Lanternes dans le noir }
  version: '1.0'
source:
  title: Lanterns in the Dark
  licence: owned
  author: The application
attributes:
  - key: grit
    names: { en: Grit, fr: Cran }
    abbreviations: { en: GRT, fr: CRN }
    minimum: 1
    maximum: 6
dice:
  - key: desperate
    notation: 3d6kl2
    names: Desperate roll
terms:
  - key: range.near
    category: range
    names: Near
    descriptions: Within a few steps.
    source:
      title: Open ranges
      licence: CC-BY-4.0
      attribution: Ranges from Open ranges, under CC BY 4.0.
      url: https://example.org/ranges
```

| Champ | Obligatoire | Ce qu'il contient |
|---|---|---|
| `format` | Oui | `tilttek-rpg-systems/1` |
| `kind` | Non | `system`, la valeur par défaut |
| `locale` | Non | La locale d'un texte écrit seul, `names: Near` plutôt que `names: { en: Near }` ; `en` par défaut |
| `system` | Oui | `key`, `names`, et une `version` facultative |
| `source` | Oui | La provenance du système, et de chaque enregistrement qui n'en énonce aucune |
| `attributes` | Non | Une liste de `key`, `names`, `abbreviations`, `descriptions`, `minimum`, `maximum`, `source` |
| `dice` | Non | Une liste de `key`, `notation`, `names`, `source` |
| `terms` | Non | Une liste de `key`, `names`, `category`, `descriptions`, `source` |
| `variants` | Non | Les variantes de règles : une liste de `key`, `names`, `descriptions`, `source` |
| `difficulties` | Non | Les nombres de difficulté : une liste de `key`, `names`, `descriptions`, `method`, `bands` — chacun `key`, `names`, `number` —, `numbers`, `source` |
| `roles` | Non | Les rôles de groupe : une liste de `key`, `names`, `descriptions`, `source` |
| `translations` | Non | Les provenances des traductions, une liste par locale : `{ es: [ { title: …, licence: … } ] }` |

Une source contient `title` et `licence`, ainsi que `attribution`, `author` et `url` selon ce que sa licence exige
([Sources et attribution](attribution.md)). Les textes sont un tableau associatif de la locale au texte, ou un texte
seul dans la locale du document. Une version écrite `5.2` sans guillemets est lue comme un nombre et gardée comme le
texte `5.2` ; une version comme `5.10` est mise entre guillemets, sinon elle se lit `5.1`.

## Variantes de règles, nombres de difficulté et rôles de groupe

```yaml
variants:
  - key: long-night
    names: { en: The long night, fr: La longue nuit }
difficulties:
  - key: check
    names: Difficulty of an action
    method: check
    bands:
      - { key: easy, names: Easy, number: 6 }
      - { key: hard, names: Hard, number: 9 }
  - key: encounter
    names: Threat of a scene
    method: budget
    bands:
      - { key: calm, names: Calm }
      - { key: tense, names: Tense }
    numbers:
      budgets:
        1: { calm: 2, tense: 4 }
      costs: { 1/2: 1, 1: 2 }
roles:
  - key: bearer
    names: { en: Lantern bearer, fr: Porte-lanterne }
```

Les `numbers` des nombres de difficulté sont un tableau associatif d'étiquettes vers des nombres entiers ou vers des
tableaux de ceux-ci, sur trois niveaux au plus : une étiquette écrite `1` est lue comme le nombre 1 et représente la même
étiquette ([Systèmes et enregistrements](systems-and-records.md#les-nombres-de-difficulté)).

## Les localisations

Un document de localisation superpose les textes d'une langue de plus à un système, avec la source de la traduction :

```yaml
format: tilttek-rpg-systems/1
kind: localisation
system: lanterns
locale: es
source:
  title: Linternas en la oscuridad, traducción
  licence: CC-BY-4.0
  attribution: Spanish translation of Lanterns in the Dark, under CC BY 4.0.
name: Linternas en la oscuridad
attributes:
  grit: { name: Temple, abbreviation: TMP }
dice:
  desperate: Tirada desesperada
terms:
  range.near: { name: Cerca, description: A pocos pasos. }
```

`system`, `locale` et `source` sont obligatoires. Un attribut accepte `name`, `abbreviation` et `description`, un terme, une
variante de règles et un rôle de groupe `name` et `description`, une expression de dés son nom, des nombres de
difficulté `name`, `description` et `bands`, les noms de leurs paliers par clé :

```yaml
variants:
  long-night: { name: La larga noche }
difficulties:
  check: { name: Dificultad de una acción, bands: { easy: Fácil, hard: Difícil } }
roles:
  bearer: { name: Portador de la linterna }
```

Une clé que le système n'a pas, un palier compris, est refusée.

## La lecture

```php
$reader = new SystemReader();

$systems = $reader->load('lanterns.yaml', 'lanterns.es.yaml');   // the systems first, then each localisation
$system = $reader->system($reader->decode($json, Syntax::Json));
$spanish = $reader->localise($system, $reader->decode($yaml, Syntax::Yaml));
```

`load()` prend des fichiers qui se terminent par `.yaml`, `.yml` ou `.json`, lit chaque système, puis superpose chaque
localisation au système qu'elle nomme, quel que soit l'ordre des fichiers. `decode()` lit un texte ; `system()`,
`localisation()` et `localise()` lisent un document décodé. `provenance()` lit seul un objet `source` décodé, comme le
manifeste d'un [paquet de données](data-packages.md) le contient.

Le lecteur est strict, pour qu'une faute de frappe soit signalée plutôt qu'abandonnée en silence :

- un champ qu'il ne connaît pas est refusé, avec l'endroit où il a été trouvé : `attributes[0]: "rank" is not one of key, names, …` ;
- une licence hors de la liste fermée est refusée ([Sources et attribution](attribution.md)) ;
- YAML est lu sans objets et sans balises, JSON jusqu'à une profondeur de 64 ;
- un document de plus de 2 000 000 octets est refusé avant d'être décodé — la limite est l'argument du lecteur —, une
  liste de plus de 10 000 enregistrements avant d'être lue.

## L'écriture

```php
$writer = new SystemWriter();

$writer->toYaml($system);
$writer->toJson($system);
$writer->toArray($system);
```

L'écrivain écrit la source du système une seule fois, et la source d'un enregistrement seulement là où elle diffère ;
les textes sont écrits comme des tableaux associatifs de la locale au texte, les champs vides sont omis, et les
provenances des traductions vont sous `translations`.
