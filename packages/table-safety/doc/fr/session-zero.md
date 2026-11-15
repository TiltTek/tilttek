<!-- phil-dev source-sha256=459830354fc5f634525e901fdd40a47d85f46bf4bd349f9a09dcf45e4a90c02c -->
# Séance zéro

Une séance zéro est le questionnaire auquel une table répond avant de jouer : le ton, les thèmes, les attentes,
l'horaire. Ses réponses, rassemblées, forment le **pacte** de la table.

## Questions

Une `Question` a une clé, un genre et, pour un choix, ses options :

| Genre | Réponse |
|---|---|
| `QuestionKind::Text` | un texte, rogné, jusqu'à 2 000 caractères |
| `QuestionKind::Choice` | une option |
| `QuestionKind::Choices` | plusieurs options, gardées dans l'ordre de la question |

Une question intégrée n'a ni énoncé ni étiquettes d'options : l'application les traduit à partir de leurs clés, sous la
forme `session_zero.questions.<key>` et `session_zero.options.<question>.<option>`. Une question qu'écrit le meneur de
jeu porte son propre énoncé — de 1 à 300 caractères — et ses propres étiquettes :

```php
new Question('snacks', QuestionKind::Choice, 'What do we snack on?', ['chips' => 'Chips', 'fruit' => 'Fruit']);
```

Une question à choix a de 2 à 20 options ; une question peut être `required`.

## Les gabarits intégrés

| Gabarit | Questions |
|---|---|
| `one-shot` | ton, piliers, thèmes bienvenus, conflit entre personnages, mort de personnage, confort |
| `campaign` — le gabarit par défaut | ton, piliers, attentes, thèmes bienvenus, conflit entre personnages, mort de personnage, fréquence, horaire, règles de la table, confort |
| `convention` | expérience, ton, conflit entre personnages, confort |
| `online` | ton, piliers, thèmes bienvenus, caméra, enregistrement, règles de la table, confort |

`Templates::get('online')` en retourne un, `Templates::all()` tous. Le ton est obligatoire, tout comme
l'enregistrement d'une table en ligne.

## Le gabarit d'un meneur de jeu

Un meneur de jeu part d'un gabarit et le modifie ; un `Template` compte de 1 à 50 questions, chaque clé une seule fois :

```php
$template = Templates::get('convention')
    ->with(new Question('own-1', QuestionKind::Text, 'Anything we should know?'))
    ->without('experience');
```

`with()` ajoute une question à la fin, ou la met à la place de celle qui porte sa clé. Le gabarit est gardé dans les
[réglages](settings.md) de la table.

## Réponses

`Template::answer()` accepte les réponses d'un participant par clé de question : un texte est rogné, une option doit
être l'une de celles de la question, une réponse vide est écartée, une question obligatoire exige une réponse, et une
clé que le gabarit n'a pas est refusée.

```php
$safety->answerSessionZero($table, $participant, ['tone' => 'dark', 'pillars' => ['mystery', 'social']], $now);
```

## Le pacte

`TableSafety::pact()` rassemble les réponses d'une table sans dire qui a donné quoi : pour une question à choix, combien
ont choisi chaque option ; pour une question à texte, les réponses en ordre alphabétique plutôt que dans l'ordre où
elles ont été données.

```php
$pact = $safety->pact($table);

$pact->hasEnoughAnswers(); // true
$pact->tally('tone');      // ['light' => 0, 'mixed' => 1, 'serious' => 0, 'dark' => 2]
$pact->texts('comfort');   // ['Breaks every hour', 'Captions']
$pact->respondents;        // 3
```

Une réponse à une option ou à une question que le gabarit n'a plus est écartée.

### Le seuil

Dans un petit groupe, un décompte d'une ou deux réponses dit qui a choisi quoi. Le pacte ne contient donc des réponses
que par groupes entiers d'un **seuil** de participants, 3 par défaut, pris dans l'ordre de leur première réponse — le
même seuil que celui de l'[entente montrée aux gens](before-play.md#le-sommaire-montré-aux-gens) :

- sous le seuil, il ne contient rien : `hasEnoughAnswers()` est faux, chaque décompte est à 0, chaque liste de textes
  est vide et `respondents` est 0 ;
- au-delà, les réponses d'un groupe incomplet attendent : avec un seuil de 3, quatre ou cinq participants qui ont
  répondu donnent le pacte des trois premiers, six donnent les six.

```php
// Two participants answered "dark" and "light":
$pact->tally('tone');      // ['light' => 0, 'mixed' => 0, 'serious' => 0, 'dark' => 0]
$pact->respondents;        // 0

// A third answers "dark":
$pact->tally('tone');      // ['light' => 1, 'mixed' => 0, 'serious' => 0, 'dark' => 2]

// A fourth answers "serious": the tally waits for two more.
$pact->tally('tone');      // ['light' => 1, 'mixed' => 0, 'serious' => 0, 'dark' => 2]
```

Une réponse de plus ne change donc jamais le pacte par les seules réponses d'une personne. Des réponses à aucune des
questions du gabarit ne sont pas une réponse et ne prennent aucune place dans un groupe. L'application choisit le seuil,
par l'`answersThreshold` de `TableSafety` ; `Pact::of()` le prend aussi.

Le pacte ne filtre jamais le contenu : ce qui doit garder un thème hors du jeu relève des
[lignes et voiles et de la liste de consentement](before-play.md), qui appliquent chaque réponse.
