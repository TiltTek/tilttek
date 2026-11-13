<!-- phil-dev source-sha256=bddef854f789d5f1b0565fdadcb74f3eef4ac8017cca31abc8e52144b4f419c5 -->
# Utilisation

## Un scénario

Un `Scenario` contient de 1 à 200 énigmes et, facultativement, sa finale : l'énigme qui le termine. Sans finale, le
scénario se termine quand chaque énigme est résolue. Les identifiants — du scénario et de chaque énigme — contiennent
de 1 à 64 caractères parmi `a-z`, `0-9`, `-` et `_` à l'intérieur, et commencent par une lettre.

Une `Puzzle` a :

| Champ | Sens | Bornes |
|---|---|---|
| `id`, `title` | Son identifiant et le titre que lisent les joueurs | Titre de 1 à 200 caractères |
| `answer` | La façon dont ses propositions sont vérifiées : une `Answer\AnswerKey`, ou une vérification propre à l'application | — |
| `hints` | Ses paliers d'indices, révélés dans cet ordre | 10 au plus, chacun de 1 à 2 000 caractères |
| `unlock` | Quand elle s'ouvre | Voir plus bas |

Un scénario se construit en PHP ou se lit depuis des valeurs simples ou du JSON — dans 256 Kio et 8 niveaux, un champ
que la bibliothèque ne connaît pas étant refusé :

```json
{
    "id": "manor",
    "finale": "vault",
    "puzzles": [
        {"id": "clock", "title": "The clock", "answers": ["Midnight", "Minuit"],
         "partials": {"twelve": "Which twelve?"},
         "hints": [{"text": "Look at the hands", "penalty": 60}, {"text": "Both point up"}]},
        {"id": "painting", "title": "The painting", "answers": ["Le Chêne"],
         "normalize": {"keep_accents": true}},
        {"id": "letter", "title": "A letter", "answers": ["Rosebud"], "unlock": {"manual": true}},
        {"id": "vault", "title": "The vault", "answers": ["4521"],
         "unlock": {"requires": ["clock", "painting"]}}
    ]
}
```

```php
$scenario = Scenario::fromJson($json);
$scenario->order();   // ['clock', 'painting', 'letter', 'vault']: an order a game can solve them in
```

## Les réponses

Une `Answer\AnswerKey` contient de 1 à 10 réponses acceptées et jusqu'à 20 réponses partielles, chacune avec le message
que lisent les joueurs. Chaque réponse est normalisée une fois, à la création de la clé, et chaque proposition de la
même façon, par une `Answer\AnswerNormalizer` :

| Étape | Valeur par défaut | Option |
|---|---|---|
| Forme de compatibilité : un `Ａ` pleine largeur devient un `a` | Toujours | — |
| Pliage de la casse : `Straße` devient `strasse` | Toujours | — |
| Caractères invisibles retirés : espaces de largeur nulle, caractères de contrôle | Toujours | — |
| Accents retirés : `chêne` devient `chene` | Activée | `keep_accents` |
| Ponctuation et symboles retirés : `l'oak!` devient `loak` | Activée | `keep_punctuation` |
| Espaces retirées : `old oak` devient `oldoak` | Activée | `keep_spaces`, qui garde une espace entre les mots |

Un texte contient 200 caractères au plus. Une réponse partielle égale à une réponse acceptée, ou donnée deux fois une
fois normalisée, est refusée à la création de la clé.

```php
$key = new AnswerKey(['Le Chêne'], ['chene' => 'Which tree, exactly?'], new AnswerNormalizer(keepAccents: true));
$key->check('le chêne')->outcome;   // GuessOutcome::Correct
$key->check('chene')->message;      // "Which tree, exactly?"
```

## Les propositions

`PuzzleEngine::guess()` prend le scénario, le `Progress` de l'équipe, l'équipe, l'énigme et la proposition, et donne un
`GuessResult` :

| Champ | Sens |
|---|---|
| `outcome` | `Correct`, `Partial`, `Wrong` ou `Throttled` |
| `message` | Le message d'une réponse partielle |
| `retryAfter` | Quand une équipe limitée peut proposer de nouveau |
| `opened` | Les énigmes que la résolution a ouvertes, dans l'ordre du scénario |
| `completed` | Si cette résolution a terminé le scénario |
| `progress` | L'avancement à stocker |

Une proposition sur une énigme verrouillée ou résolue, ou absente du scénario, est refusée avec `PuzzleRefused`. Une
proposition vide une fois normalisée, de plus de 200 caractères ou qui n'est pas en UTF-8 est refusée avec
`InvalidAnswer` : elle compte dans la limite, pas parmi les propositions de l'avancement. Une équipe est nommée par un
identifiant stable, jamais une adresse courriel.

Chaque proposition compte une tentative sous la clé `scenario|team|puzzle` du limiteur. Au-delà de la limite de la
période, la proposition n'est pas vérifiée : le résultat est `Throttled`, avec `retryAfter`, et l'avancement est
inchangé.

## Les indices

`PuzzleEngine::hint()` révèle le palier suivant d'une énigme ouverte et donne un `HintResult` : l'indice, son palier à
partir de 1, les paliers restants et l'avancement. `pushed: true` marque un indice que l'animateur a envoyé plutôt que
demandé par les joueurs ; les deux sont gardés dans l'avancement avec leur heure. La `penalty` d'un indice, en secondes,
est à l'usage du jeu — un escape game l'ajoute au temps final — ; le moteur ne fait que la garder. Une énigme sans
palier restant refuse avec `PuzzleRefused`.

## Les déverrouillages

Un `Unlock` dit quand une énigme s'ouvre :

| Déverrouillage | S'ouvre |
|---|---|
| `Unlock::open()` | Dès le départ |
| `Unlock::after(['clock', 'painting'])` | Une fois les deux résolues |
| `Unlock::after(['a', 'b', 'c'], 2)` | Une fois deux des trois résolues |
| `Unlock::afterSolves(3)` | Une fois trois énigmes du scénario, n'importe lesquelles, résolues |
| `new Unlock(['clock'], afterSolves: 2)` | Une fois l'horloge et deux énigmes en tout résolues |
| `Unlock::manual()` | Quand l'animateur l'ouvre, quoi qu'il y ait de résolu |

Une énigme en exige 50 autres au plus, chacune une fois, et jamais elle-même. À la création du scénario, le graphe est
vérifié :

- chaque énigme exigée est dans le scénario, et la finale aussi ;
- aucune énigme n'en exige une autre qui l'exige, directement ou par d'autres :
  `The puzzles b → c → d → b require one another.` ;
- chaque énigme est atteignable par une partie qui résout ce qu'elle ouvre, les énigmes que l'animateur ouvre comptées
  comme ouvertes : une énigme qui attend plus de résolutions que le scénario n'en permet, ou une énigme qui ne
  s'ouvre jamais, est refusée — `No game can reach the puzzles b, c.`

## Les coups de l'animateur

- `PuzzleEngine::open()` ouvre une énigme déclarée `Unlock::manual()` : un indice distribué au deuxième acte. Une autre
  énigme, ou une énigme déjà ouverte, est refusée.
- `PuzzleEngine::skip()` marque une énigme ouverte résolue pour des joueurs bloqués ; elle ouvre ce que l'énigme ouvre,
  et l'avancement garde qu'elle a été sautée.

## Stocker une partie

La bibliothèque ne stocke rien. `Progress::toArray()` donne des valeurs simples — les énigmes résolues avec leur heure
et si elles ont été sautées, celles que l'animateur a ouvertes, les indices révélés et les propositions de chaque
énigme —, chaque heure en UTC à la microseconde ; `Progress::fromArray()` les relit, vérifiées d'après le scénario : une
énigme que le scénario ne contient pas, un indice hors de son ordre, un compte hors des bornes est refusé avec
`InvalidProgress`.

```php
$progress = Progress::fromArray($scenario, $row['progress']);
$result = $engine->guess($scenario, $progress, $team, $puzzle, $request->getPayload()->getString('answer'));
$row['progress'] = $result->progress->toArray();
```

Deux coups de la même équipe en même temps lisent le même avancement : l'application stocke l'avancement avec une
version et refuse la seconde écriture, comme elle le fait pour toute ligne que deux requêtes changent.

## La vue

`PuzzleEngine::view()` donne ce que voient les joueurs, dans l'ordre du scénario : pour chaque énigme son état, son
titre, les indices révélés et combien il en reste, ses propositions et le moment où elle a été résolue. Une énigme
verrouillée ne montre que son identifiant et son état. `ScenarioView::toArray()` est prêt pour le JSON ; il ne contient
aucune réponse, aucune réponse partielle et rien qui en vérifie une.

```json
{"scenario": "manor", "completed": false, "puzzles": [
    {"id": "clock", "status": "open", "title": "The clock", "hints": ["Look at the hands"], "hints_left": 1,
     "guesses": 2, "solved_at": null, "skipped": false},
    {"id": "vault", "status": "locked", "title": null, "hints": [], "hints_left": 0, "guesses": 0,
     "solved_at": null, "skipped": false}
]}
```

## Afficher la partie

La bibliothèque ne dessine rien. La page qui montre une partie la garde utilisable par tous :

- l'état de chaque énigme se dit en mots — « Résolue », « Ouverte », « Verrouillée » —, jamais par une couleur ou une
  icône seule ;
- le champ de réponse a un libellé visible, et le résultat d'une proposition est annoncé dans une région dynamique
  polie, en une phrase entière : « Pas encore : quels douze ? », « Résolue ! La chambre forte est ouverte. » ;
- une proposition limitée dit en mots quand l'équipe pourra essayer de nouveau, et le champ reste utilisable ;
- une résolution qui termine le scénario est célébrée avec le composant de célébration de PHIL UX, qui respecte
  `prefers-reduced-motion`.
