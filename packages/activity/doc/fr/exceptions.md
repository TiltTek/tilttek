<!-- phil-dev source-sha256=dfe0de76010edaa0ab786178f896ada066efb7f436b8acdec0f1130f67ea0e45 -->
# Exceptions

Chaque exception de la bibliothèque implémente `Tilttek\Activity\Exception\ActivityException`.

| Exception | Levée quand | Message, par exemple |
|---|---|---|
| `InvalidActivity` | Un verbe, un motif, une clé, un contexte, une quantité, un moment hors des années 1970 à 9999 ou de la fenêtre du journal, une position, une lecture ou une taille de lot est refusé | `"Game.played" is not a verb: lower-case segments of letters, digits and "_", each starting with a letter, joined by dots, such as "game.played".` |
| `IdempotencyConflict` | Une clé déjà enregistrée l'est de nouveau pour un autre geste | `The idempotency key "k" is already recorded for another gesture.` |
| `UnknownVerb` | Les verbes sont déclarés et le verbe de l'activité n'en fait pas partie | `The verb "news.read" is not declared.` |
| `UnknownProjection` | Une projection est demandée par un nom qu'aucune ne porte | `No projection is named "quests".` |
| `InvalidProjection` | Un nom de projection n'en est pas un, ou deux projections le partagent | `Two projections are named "points".` |
