# Before play

## Lines and veils

A participant says, theme by theme, what they ask of it: a **line** keeps it out of the game entirely, a **veil** lets
it happen off screen, without detail. A theme with neither is open.

```php
$answers = LinesAndVeils::none()
    ->with('spiders', Boundary::Line)
    ->with('torture', Boundary::Veil);

$answers->lines();     // ['spiders']
$answers->veils();     // ['torture']
$answers->without('torture');
```

`LinesAndVeils` is immutable, keeps its themes in key order and names at most `MAX_THEMES` (2,100) of them. A key is a
theme key of `phil/content-warnings`: lower case letters and digits joined by hyphens, 64 bytes at most.

## The consent checklist

The checklist gives each theme a level: **green**, welcome; **yellow**, with care or off screen; **red**, not at this
table. A theme left out is not answered.

```php
$checklist = ConsentChecklist::none()
    ->with('gore', ConsentLevel::Red)
    ->with('war', ConsentLevel::Yellow);
```

`ConsentLevel::strictest()` gives the strictest of several levels; red is stricter than yellow, yellow than green.

## The themes of a table

The themes come from a catalogue of `phil/content-warnings` — its base list of about seventy themes in twelve
categories, or the application's — and each table may add its own, with the label its game master gave:

```php
$themes = new TableThemes(BaseCatalogue::load(), ['table-clowns' => 'Clowns']);

$themes->has('table-clowns');        // true
$themes->label('spiders', 'fr');     // "Araignées"
$themes->unknown(['gore', 'kraken']); // ['kraken']
```

An added theme never takes the key of a theme of the catalogue; its label has 1 to 120 characters, and a table adds at
most 100 themes. `TableSafety` refuses an answer about a theme its table does not know.

## What the table agreed

`TableAgreement` gathers the answers of a table without saying who gave which: for each theme, the strictest boundary
and the strictest level anyone gave — **the most restrictive answer wins** —, and how many participants answered.

```php
$agreement = $safety->agreement($table);

$agreement->lines();                      // the themes behind a line, in key order
$agreement->veils();                      // behind a veil and no line
$agreement->consent('gore');              // ConsentLevel::Red
$agreement->themesAt(ConsentLevel::Yellow);
$agreement->respondents;                  // 3
```

The order in which the participants answered does not change it. An answer changes at any moment, even during play,
and the agreement follows.

## As warning preferences

The answers stand for preferences of `phil/content-warnings`, so that a table's content is filtered by what it agreed:
a line or a red theme hides warned content, a veil or a yellow theme folds it, the rest is shown.

```php
$decision = new WarningMatcher($catalogue)->decide($sceneWarnings, $safety->agreement($table)->preferences());
```

`LinesAndVeils::preferences()` and `ConsentChecklist::preferences()` give the same for one participant.
