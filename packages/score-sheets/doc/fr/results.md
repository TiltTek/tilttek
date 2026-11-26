<!-- phil-dev source-sha256=1dfbf6af18801df6d8d02edad740ff6f449252597bd8c23883273375409515d9 -->
# Transmettre les résultats

`ScoreKeeper::close()` retourne un `MatchResult` : des données neutres qu'un journal de jeu, un classement ou un
tableau d'honneur lit sans que la bibliothèque dépende d'aucun d'eux.

| Champ | Sens |
|---|---|
| `match` | La partie |
| `game` | La clé du jeu |
| `revision` | 1 pour le premier résultat, 2 et plus pour une correction après la réouverture de la feuille |
| `participants` | Un `Placement` par participant, dans l'ordre des rangs : identifiant, rang, total |
| `teams` | Un `Placement` par équipe, dans l'ordre des rangs : identifiant, rang, total, membres |
| `complete` | Si chaque valeur a été saisie |

```php
$result = $keeper->close($match, $version);

$bus->dispatch($result->message());   // Message\MatchResultRecorded: the match and the revision only
```

`Message\MatchResultRecorded` ne porte que des identifiants ; son *handler* lit la feuille dans le magasin et recalcule
le résultat avec `MatchResult::of($sheet, $scorer->score($sheet->sheet, $sheet->entries))`. Un *handler* qui reçoit une
révision qu'il a déjà enregistrée, ou une plus ancienne, l'ignore ; une révision plus récente remplace le résultat
qu'il avait enregistré.

Un classement prend les places comme une suggestion : les rangs disent qui a fini où, et le classement décide de ce
qu'il fait des égalités, des feuilles incomplètes et des équipes.
