<!-- phil-dev source-sha256=faa110a9bdc23163feb423380363db17bf09f965231d596e68abaadb2765bdc1 -->
# Systèmes et enregistrements

Un `GameSystem` est ce que les autres outils lisent d'un système de jeu : sa clé, ses noms, sa version, sa propre
provenance, et six listes d'enregistrements dans l'ordre que le système donne.

| Enregistrement | Ce qu'il contient | Par exemple |
|---|---|---|
| `Attribute` | Une clé, des noms, des abréviations, des descriptions, et les valeurs la plus basse et la plus haute quand le système en a | `str`, Force, `STR`, de 1 à 30 |
| `DiceExpression` | Une clé, des noms, et un jet dans la notation canonique de `tilttek/dice` | `advantage`, Avantage, `2d20kh1` |
| `Term` | Une clé, des noms, des descriptions, et une catégorie | `condition.blinded`, Aveuglé, `condition` |
| `RuleVariant` | Une clé, des noms et des descriptions : une règle facultative qu'une table peut choisir | `encumbrance`, Encombrement |
| `Difficulty` | Une clé, des noms, des descriptions, une méthode, des paliers du plus facile et des tables de nombres entiers | `check`, Degrés de difficulté typiques, `easy` 10 … `nearly-impossible` 30 |
| `PartyRole` | Une clé, des noms et des descriptions : un rôle que remplissent les membres d'un groupe | `bearer`, Porte-lanterne |

Chaque enregistrement porte sa `Provenance`, la sienne ou celle du système ([Sources et attribution](attribution.md)).

## Les clés

Une clé est faite de 1 à 96 lettres ASCII minuscules, chiffres, `_` et `-`, avec des points simples entre eux :
`str`, `condition.blinded`, `sleight-of-hand`. Un système refuse deux enregistrements du même genre et de même
clé ; des enregistrements de deux genres — un attribut et un terme — peuvent en partager une. Une catégorie, une méthode
et la clé d'un palier sont aussi des clés.

## Les textes par locale

Les noms, abréviations et descriptions sont des `Translations` : un texte par locale, 64 locales au plus, chaque texte
de 4 000 caractères UTF-8 au plus, sans caractères de contrôle autres que les sauts de ligne et les tabulations. Une
locale s'écrit `fr`, `fr_CA`, `fr-ca` ou `es-419`, et se garde comme `fr`, `fr_CA` ou `es_419`.

`in()` trouve un texte pour un lecteur : la locale elle-même, puis sa langue, puis la locale de repli — l'anglais sauf
si une autre est donnée —, puis le premier texte détenu.

```php
$names = Translations::of(['en' => 'Strength', 'fr' => 'Force']);

$names->in('fr_CA');        // "Force", from its language
$names->in('de');           // "Strength", the fallback
$names->in('de', 'fr');     // "Force", another fallback
$names->with('es', 'Fuerza');
```

Un enregistrement a au moins un nom ; les abréviations et les descriptions peuvent être vides.

## Les attributs et leurs bornes

`minimum` et `maximum` sont les valeurs que le système permet, l'une ou les deux null quand il n'en fixe aucune.
`allows()` dit si une valeur se situe entre elles.

```php
$strength = $system->attribute('str');

$strength->allows(31);   // false, for a system whose scores go from 1 to 30
```

## Les expressions de dés

Une expression de dés est lue par `tilttek/dice` à sa construction : une notation que la bibliothèque ne sait pas lire
est refusée, et l'expression garde la forme canonique que la bibliothèque réécrit — ` 2D20KH1 ` est gardée comme
`2d20kh1`. `pool()` donne l'expression lue, prête à lancer avec `DiceRoller::roll()` ; `notationIn()` l'écrit dans la
notation d'une langue, `2W20kh1` en allemand.

```php
$roll = new DiceRoller()->roll($system->dice('advantage')->pool(), new Randomizer());
```

## Les nombres de difficulté

`Difficulty` contient les nombres qu'un système donne pour un seul usage. Ses `bands` vont du plus facile, chacun un
`DifficultyBand` avec une clé, des noms et, quand le palier représente un seul nombre, ce nombre : le degré d'un test de
cette difficulté, ou ce que la difficulté ajoute à un nombre trouvé ailleurs. Ses `numbers` sont des tables de nombres
entiers par étiquette — un niveau `1`, un indice de dangerosité `1/8`, un écart `-4`, un nom `party-size` —, imbriquées
sur trois niveaux au plus. La `method` dit comment un outil les lit ; la bibliothèque les garde tels qu'ils sont donnés
et ne vérifie que leur forme et leurs bornes.

```php
$check = $system->difficulty('check');

$check->bandKeys();                     // ['very-easy', 'easy', 'medium', 'hard', 'very-hard', 'nearly-impossible']
$check->band('hard')?->number;          // 20

$encounter = $system->difficulty('encounter');

$encounter->method;                     // 'budget'
$encounter->number('budgets', 1, 'low'); // 50, the XP budget of a level-1 character for a low difficulty
$encounter->number('costs', '1/8');     // 25
```

`number()` suit les étiquettes données et retourne un nombre, une table, ou null quand une étiquette manque. Les profils
d'importation lisent ces méthodes dans leurs nombres :

| Méthode | Paliers | Tables |
|---|---|---|
| `check` | Le nombre de chaque palier est le degré d'un test | Aucune |
| `level-dc` | Le nombre de chaque palier est ce que la difficulté ajoute au degré d'un niveau | `levels` : le degré de chaque niveau |
| `budget` | Les paliers d'une rencontre, du plus facile | `budgets` : selon le niveau d'un personnage, un montant pour chaque palier ; `costs` : selon le défi d'un adversaire, son coût ; `multipliers` (facultatif) : selon le nombre d'adversaires, un pourcentage |
| `level` | Les paliers d'une rencontre, du plus facile | `party-size` : le groupe standard ; `budgets` et `adjustments` : par palier, pour ce groupe et pour chaque personnage au-dessus ou en deçà ; `costs` : selon le niveau d'un adversaire comparé à celui du groupe |

Un enregistrement contient jusqu'à 24 paliers et 10 000 nombres, chacun dans ±1 000 000 000 ; une étiquette compte de 1 à
32 lettres minuscules, chiffres, `+`, `/`, `.`, `_` ou `-`. Un outil qui a des nombres propres les lit sous une méthode
propre.

## Chercher des enregistrements

| Méthode | Ce qu'elle donne |
|---|---|
| `attribute()`, `dice()`, `term()`, `variant()`, `difficulty()`, `partyRole()` | L'enregistrement d'une clé, ou `UnknownRecord` |
| `hasAttribute()`, `hasDice()`, `hasTerm()`, `hasVariant()`, `hasDifficulty()`, `hasPartyRole()` | Si le système contient une clé |
| `attributes()`, `diceExpressions()`, `variants()`, `difficulties()`, `partyRoles()` | Chaque enregistrement du genre, dans l'ordre |
| `terms()` | Chaque terme, ou ceux d'une catégorie, dans l'ordre |
| `categories()` | Les catégories des termes, dans l'ordre où elles apparaissent d'abord |
| `name()` | Le nom du système dans une locale |
| `provenances()` | Chaque source où le système puise, chacune une seule fois |

`Systems` contient les systèmes d'une application par clé : `get()`, `has()`, `all()`, et `with()` pour ajouter un
système ou en remplacer un de même clé. `SystemReader::load()` en donne un ([Le format](format.md)).

Un système contient 10 000 enregistrements au plus, de tous les genres ensemble. Chaque recherche par clé lit un index
construit à la création du système.

## Les localisations

Une `Localisation` contient les textes d'une langue de plus — le nom du système, et les textes des attributs,
expressions de dés, termes, variantes de règles, nombres de difficulté avec les noms de leurs paliers, et rôles de
groupe par clé — avec la provenance de la traduction. `GameSystem::localised()` donne le système
avec ces textes superposés aux siens ; un enregistrement que la localisation ne nomme pas garde ses textes, et une clé
que le système n'a pas — un palier de difficulté compris — est refusée avec `UnknownRecord`. La provenance de la traduction est gardée par locale dans
`translationSources`, et montrée par l'attribution d'une page dans cette langue.

```php
$spanish = $system->localised(new Localisation(
    'es',
    new Provenance('Spanish translation', Licence::CcBy40, 'Translated by the volunteers of the site, CC BY 4.0.'),
    'Linternas en la oscuridad',
    attributes: ['grit' => ['name' => 'Temple', 'abbreviation' => 'TMP']],
    dice: ['action' => 'Tirada de acción'],
    terms: ['condition.shaken' => ['name' => 'Conmocionado']],
));
```

Une localisation est d'ordinaire un document à part, lu par `SystemReader` ([Le format](format.md#les-localisations)).
