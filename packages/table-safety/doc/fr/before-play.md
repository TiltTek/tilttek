<!-- phil-dev source-sha256=edf28498a4b8db023f90dbf8348e8186706883e5ce77de9c19a97d53b54159db -->
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

`TableAgreement` rassemble les réponses d'une table sans dire qui a donné quoi. Il a deux parties : ce qui protège la
table, à partir de chaque réponse, et le sommaire montré aux gens, par groupes entiers d'un seuil de participants.

### Ce qui protège la table

Pour chaque thème, la limite la plus stricte et le niveau le plus strict que quiconque a donnés — **la réponse la plus
restrictive l'emporte**. Cette partie applique chaque réponse, si peu nombreux que soient les participants : une ligne
ou un voile qu'une personne a posé protège la table dès qu'il est donné.

```php
$agreement = $safety->agreement($table);

$agreement->lines();                      // the themes behind a line, in key order
$agreement->veils();                      // behind a veil and no line
$agreement->consent('gore');              // ConsentLevel::Red
$agreement->themesAt(ConsentLevel::Yellow);
$agreement->preferences();                // what the table's warned content becomes
```

L'ordre dans lequel les participants ont répondu ne change rien. Une réponse change à tout moment, même pendant la
partie, et l'entente suit.

### Le sommaire montré aux gens

`shown()` donne un `AgreementSummary` : la même vue — `lines()`, `veils()`, `boundary()`, `consent()`, `checklist()`,
`themesAt()` — faite seulement des réponses de groupes entiers d'un **seuil** de participants, 3 par défaut, pris dans
l'ordre de leur première réponse. `respondents` compte les réponses qu'il contient.

- Sous le seuil, il ne contient rien : `hasEnoughAnswers()` est faux, chaque liste est vide et `respondents` est 0.
- Au-delà, les réponses d'un groupe incomplet attendent : avec un seuil de 3, quatre ou cinq participants qui ont
  répondu donnent le sommaire des trois premiers, six donnent les six.

```php
// Ana set a line on spiders; Bo answered too. Two participants, under the threshold of 3:
$agreement->lines();                      // ['spiders']: the line protects the table
$agreement->shown()->lines();             // []: not enough answers to show
$agreement->respondents;                  // 0
$agreement->hasEnoughAnswers();           // false

// Cy answers: three participants, a whole group.
$agreement->shown()->lines();             // ['spiders']
$agreement->respondents;                  // 3

// Di answers: the fourth waits for two more before the summary changes.
$agreement->respondents;                  // 3
```

Une réponse de plus ne change donc jamais le sommaire par les seules réponses d'une personne. Une participation sans
ligne, sans voile ni niveau n'est pas une réponse et ne prend aucune place dans un groupe. L'application choisit le
seuil, le même pour l'entente et le [pacte](session-zero.md#le-pacte) :

```php
$safety = new TableSafety($catalogue, $tables, $participations, $signals, $feedback, answersThreshold: 4);
```

Un seuil de 1 montre chaque réponse dès qu'elle est donnée. Un écran montre `shown()` aux gens et donne `preferences()`
au contenu qu'il filtre ; [Vie privée](privacy.md#lentente-et-le-pacte) dit ce que chacun révèle.

## Comme préférences d'avertissement

Les réponses représentent des préférences de `phil/content-warnings`, de sorte que le contenu d'une table est filtré par
ce qu'elle a convenu : une ligne ou un thème rouge masque le contenu averti, un voile ou un thème jaune le replie, le
reste est montré.

```php
$decision = new WarningMatcher($catalogue)->decide($sceneWarnings, $safety->agreement($table)->preferences());
```

`LinesAndVeils::preferences()` et `ConsentChecklist::preferences()` donnent la même chose pour un seul participant.
