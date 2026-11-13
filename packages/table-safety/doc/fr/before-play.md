<!-- phil-dev source-sha256=3b485d1b7bcff974e9d284c8b5bd3488472115338761fb1b527380eefd66204c -->
# Avant la partie

## Lignes et voiles

Un participant dit, thème par thème, ce qu'il en demande : une **ligne** garde le thème entièrement hors du jeu, un
**voile** le laisse se produire hors scène, sans détail. Un thème sans l'un ni l'autre est ouvert.

```php
$answers = LinesAndVeils::none()
    ->with('spiders', Boundary::Line)
    ->with('torture', Boundary::Veil);

$answers->lines();     // ['spiders']
$answers->veils();     // ['torture']
$answers->without('torture');
```

`LinesAndVeils` est immuable, garde ses thèmes en ordre de clé et en nomme au plus `MAX_THEMES` (2 100). Une clé est une
clé de thème de `phil/content-warnings` : des lettres minuscules et des chiffres joints par des traits d'union, 64 octets
au plus.

## La liste de consentement

La liste donne à chaque thème un niveau : **vert**, bienvenu ; **jaune**, avec précaution ou hors scène ; **rouge**, pas
à cette table. Un thème omis n'a pas de réponse.

```php
$checklist = ConsentChecklist::none()
    ->with('gore', ConsentLevel::Red)
    ->with('war', ConsentLevel::Yellow);
```

`ConsentLevel::strictest()` donne le plus strict de plusieurs niveaux ; le rouge est plus strict que le jaune, le jaune
que le vert.

## Les thèmes d'une table

Les thèmes viennent d'un catalogue de `phil/content-warnings` — sa liste de base d'environ soixante-dix thèmes en douze
catégories, ou celui de l'application —, et chaque table peut ajouter les siens, avec l'étiquette que son meneur de jeu
leur a donnée :

```php
$themes = new TableThemes(BaseCatalogue::load(), ['table-clowns' => 'Clowns']);

$themes->has('table-clowns');        // true
$themes->label('spiders', 'fr');     // "Araignées"
$themes->unknown(['gore', 'kraken']); // ['kraken']
```

Un thème ajouté ne prend jamais la clé d'un thème du catalogue ; son étiquette compte de 1 à 120 caractères, et une table
ajoute au plus 100 thèmes. `TableSafety` refuse une réponse sur un thème que sa table ne connaît pas.

## Ce que la table a convenu

`TableAgreement` rassemble les réponses d'une table sans dire qui a donné quoi : pour chaque thème, la limite la plus
stricte et le niveau le plus strict que quiconque a donnés — **la réponse la plus restrictive l'emporte** —, et le nombre
de participants qui ont répondu.

```php
$agreement = $safety->agreement($table);

$agreement->lines();                      // the themes behind a line, in key order
$agreement->veils();                      // behind a veil and no line
$agreement->consent('gore');              // ConsentLevel::Red
$agreement->themesAt(ConsentLevel::Yellow);
$agreement->respondents;                  // 3
```

L'ordre dans lequel les participants ont répondu ne change rien. Une réponse change à tout moment, même pendant la
partie, et l'entente suit.

## Comme préférences d'avertissement

Les réponses représentent des préférences de `phil/content-warnings`, de sorte que le contenu d'une table est filtré par
ce qu'elle a convenu : une ligne ou un thème rouge masque le contenu averti, un voile ou un thème jaune le replie, le
reste est montré.

```php
$decision = new WarningMatcher($catalogue)->decide($sceneWarnings, $safety->agreement($table)->preferences());
```

`LinesAndVeils::preferences()` et `ConsentChecklist::preferences()` donnent la même chose pour un seul participant.
