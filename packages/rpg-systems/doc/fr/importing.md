<!-- phil-dev source-sha256=7677efb2b1265c754d1b22079bd0c0d076e2f3edd7473ad6d2aae574e6c02957 -->
# Importer des sources ouvertes

`Importer` transforme un extrait d'une source ouverte en système du format de la bibliothèque. Un extrait est un
tableau associatif de noms de tables à des listes d'enregistrements, en JSON ou en YAML ; un `ImportProfile` dit quelles
tables lire, quels champs contiennent quoi, et sous quelles licences la source peut être. L'appelant énonce la
source — son titre, sa licence et l'attribution qu'elle demande —, et chaque enregistrement du système la porte.

```php
$srd = new Provenance(
    'System Reference Document 5.2',
    Licence::CcBy40,
    'The attribution statement the SRD 5.2 asks for, copied from the document itself.',
    'Its publisher',
    'https://the-address-of-the-srd.example',
);

$system = new Importer()->importText(ImportProfile::srd52(), $json, Syntax::Json, $srd);

file_put_contents('systems/srd-5.2.json', new SystemWriter()->toJson($system));
```

L'import s'exécute une fois, quand l'extrait change ; l'application lit ensuite le système écrit dans le format, ou un
[paquet de données](data-packages.md) qui le porte. La
bibliothèque ne fournit aucun extrait : l'application obtient les données de la source et vérifie sa licence, et
l'importateur refuse une source dont la licence n'est pas une de celles que le profil accepte.

## Le profil du SRD 5.2

`ImportProfile::srd52()` produit le système `srd-5.2`, version `5.2`, à partir d'un contenu sous `CC-BY-4.0` :

| Table | Champs lus | Donne |
|---|---|---|
| `ability-scores` | `index`, `full_name` le nom, `name` l'abréviation, `desc` | Des attributs de 1 à 30 |
| `skills` | `index`, `name`, `desc` | Des termes `skill.…` |
| `conditions` | `index`, `name`, `desc` | Des termes `condition.…` |
| `damage-types` | `index`, `name`, `desc` | Des termes `damage-type.…` |
| `magic-schools` | `index`, `name`, `desc` | Des termes `magic-school.…` |

Il ajoute trois expressions de dés : `d20-test` `1d20`, `advantage` `2d20kh1` et `disadvantage` `2d20kl1`, et deux
ensembles de nombres de difficulté, tels que la source les donne :

| Clé | Méthode | Contenu |
|---|---|---|
| `check` | `check` | Les degrés de difficulté typiques : `very-easy` 5, `easy` 10, `medium` 15, `hard` 20, `very-hard` 25, `nearly-impossible` 30 |
| `encounter` | `budget` | Les paliers `low`, `moderate` et `high` ; `budgets`, le budget de PX d'un personnage selon son niveau de 1 à 20 ; `costs`, les PX d'une créature selon son indice de dangerosité de 0 à 30 — 10 pour l'indice 0 |

La source ne nomme aucune variante de règles et ne déclare aucun rôle de groupe.

## Le profil du SRD 5.1

`ImportProfile::srd51()` produit le système `srd-5.1`, version `5.1`, à partir d'un contenu sous `CC-BY-4.0`. Il lit les
mêmes tables que le profil du SRD 5.2, avec les mêmes champs, et ajoute trois expressions de dés : `d20-roll` `1d20`,
`advantage` `2d20kh1` et `disadvantage` `2d20kl1`, deux variantes de règles — `encumbrance` et
`skills-with-different-abilities` — et les nombres de difficulté `check`, les degrés de difficulté typiques du profil du
SRD 5.2. La source ne donne aucune règle de difficulté de rencontre et ne déclare aucun rôle de groupe.

## Le profil de Pathfinder Second Edition

`ImportProfile::pathfinder2e()` produit le système `pf2e` à partir d'un contenu sous `ORC` :

| Table | Champs lus | Donne |
|---|---|---|
| `attributes` | `slug`, `name`, `abbreviation`, `description` | Des attributs, sans bornes |
| `skills` | `slug`, `name`, `description` | Des termes `skill.…` |
| `conditions` | `slug`, `name`, `description` | Des termes `condition.…` |
| `traits` | `slug`, `name`, `description` | Des termes `trait.…` |

Il ajoute trois expressions de dés : `check` `1d20`, `fortune` `2d20kh1` et `misfortune` `2d20kl1`, quatre variantes de
règles — `automatic-bonus-progression`, `dual-class`, `free-archetype` et `proficiency-without-level` — et trois
ensembles de nombres de difficulté :

| Clé | Méthode | Contenu |
|---|---|---|
| `simple` | `check` | Les DD simples selon le rang de maîtrise : `untrained` 10, `trained` 15, `expert` 20, `master` 30, `legendary` 40 |
| `level` | `level-dc` | `levels`, le DD de chaque niveau de 0 à 25 ; le nombre de chaque palier est l'ajustement d'une difficulté, de `incredibly-easy` −10 à `incredibly-hard` +10 |
| `encounter` | `level` | Les paliers `trivial` à `extreme` ; `party-size` 4 ; `budgets` de 40 à 160 et `adjustments` de 10 à 40 par palier ; `costs`, les PX d'une créature, de quatre niveaux sous le groupe (10) à quatre niveaux au-dessus (160) |

La source ne déclare aucun rôle de groupe.

## Le profil de Fate Core

`ImportProfile::fateCore()` produit le système `fate-core` à partir d'un contenu sous `CC-BY-3.0`. Fate n'a aucun
attribut :

| Table | Champs lus | Donne |
|---|---|---|
| `skills` | `key`, `name`, `description` | Des termes `skill.…` |
| `approaches` | `key`, `name`, `description` | Des termes `approach.…`, les approches de Fate Accelerated |
| `ladder` | `key`, `name`, `description` | Des termes `ladder.…`, les échelons de l'échelle, `Good` décrit `+3` |

Il ajoute une expression de dés : `fate-roll` `4dF`.

## La version de la source

Un profil donne au système la version qu'il nomme — `5.1`, `5.2` —, ou aucune. `withVersion()` donne le même profil avec
la version des fichiers dont un extrait est tiré, pour que le système, et le paquet de données écrit d'après lui,
nomment la version qu'ils contiennent :

```php
$system = new Importer()->importText(ImportProfile::srd52()->withVersion('5.2.1'), $json, Syntax::Json, $srd);

$system->version; // "5.2.1"
```

Une version est un texte d'au plus 4 000 caractères sans caractère de contrôle ; une version vide est refusée par une
`InvalidRecord`.

## Comment un enregistrement est lu

- Les tables qu'un profil ne nomme pas sont omises ; une table que l'extrait n'a pas ne donne aucun enregistrement.
- Une clé est mise en minuscules, et chaque suite de caractères qu'une clé ne peut pas contenir devient un trait
  d'union : `Sleight of Hand` donne `sleight-of-hand`. La clé d'un terme est sa catégorie, un point, et cette clé.
- Une description est un texte, ou une liste de paragraphes joints par une ligne vide.
- Un enregistrement qui ne peut pas être lu est refusé avec son endroit : `conditions[0].name: a text is expected.`

## Un profil à vous

Un extrait de forme différente, ou une autre source ouverte, se lit avec un profil propre à l'application :

```php
$profile = new ImportProfile(
    'my-system',
    'Mon système',
    [Licence::CcBy40, Licence::CcBySa40],
    attributes: new TableMapping('caracteristiques', key: 'id', name: 'nom', abbreviation: 'abrege'),
    terms: [new TableMapping('etats', 'condition', key: 'id', name: 'nom', description: 'texte')],
    dice: ['test' => '1d20'],
    diceNames: ['test' => 'Test'],
    locale: 'fr',
    attributeMinimum: 1,
    attributeMaximum: 20,
    variants: ['nuit-longue' => 'La longue nuit'],
    difficulties: [
        ['key' => 'jet', 'name' => 'Difficulté', 'method' => 'check', 'bands' => [
            ['key' => 'facile', 'name' => 'Facile', 'number' => 8],
            ['key' => 'difficile', 'name' => 'Difficile', 'number' => 12],
        ]],
    ],
    partyRoles: ['guide' => 'Guide'],
);
```

`variants`, `difficulties` et `partyRoles` sont les enregistrements que la source énonce une fois pour tous, comme
`dice` : chaque enregistrement porte la source que l'appelant énonce, et est nommé dans la locale du profil. Un ensemble
de nombres de difficulté accepte `key`, `name`, et les champs facultatifs `description`, `method`, `bands` — chacun avec
`key`, `name` et un `number` facultatif — et `numbers`.

Un `TableMapping` nomme une table, la catégorie de ses termes — aucune pour les attributs, `term` quand une table de
termes n'en donne aucune —, et les champs de la clé, du nom, de l'abréviation et de la description.
