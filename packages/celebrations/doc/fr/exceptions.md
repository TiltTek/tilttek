<!-- phil-dev source-sha256=33ef4d3e05ce059aa80a3d9279c59fc6fc5731419f74e895adb5ad72c2154a85 -->
<!-- phil-dev source-sha256=0 -->
# Exceptions

Toute exception de la bibliothèque implémente `Exception\CelebrationException` : attrapez celle-ci pour les attraper
toutes.

| Exception | Lancée quand | Message |
|---|---|---|
| `InvalidCelebration` | Une clé, une occurrence, un paramètre, un moment, une séquence, un groupe, une taille de page, une liste de séquences ou une limite est hors limites | Ce qui est hors limites, et la limite |

`InvalidCelebration` étend `InvalidArgumentException`. Ses messages :

| Valeur | Message |
|---|---|
| Une source, un genre ou un nom de paramètre | `The key of a source must be 1 to 96 lowercase letters, digits, "_", "-" or single dots between them; "…" is not.` |
| Un sujet ou une occurrence | `The subject of an achievement is 1 to 191 visible ASCII characters, without spaces.`, et de même pour l'occurrence |
| Trop de paramètres | `An achievement carries at most 10 parameters; this one has ….` |
| Le texte d'un paramètre | `The parameter "…" is 1 to 100 characters of UTF-8 without control characters nor surrounding spaces.` |
| Un `showUntil` qui ne suit pas `earnedAt` | `The moment an achievement stops being shown comes after the moment it was earned.` |
| Une séquence inférieure à 1 | `The sequence of an entry starts at 1; … is not one.` |
| Une entrée en attente avec un moment de règlement, ou une entrée réglée sans | `A pending entry has no settling moment, and a settled entry has one.` |
| Régler une entrée comme en attente | `An entry is settled as seen, withdrawn or lapsed, never as pending.` |
| Un groupe vide | `A group shows at least one entry.` |
| L'entrée d'un groupe d'une autre source ou d'un autre genre | `A group holds the entries of one source and one kind; sequence … is not of "…" and "…".` |
| Un groupe qui compte moins d'entrées qu'il n'en montre | `A group counts at least the … entries it shows; … is fewer.` |
| Trop de séquences marquées comme vues à la fois | `At most … entries are marked seen at once; … were given.` |
| Une page d'historique | `A page of the history holds 1 to 100 entries; … is out of bounds.` |
| Une limite | `The limit of pending entries per member is 1 to 1000; it is ….`, et de même pour les autres |

Les exceptions de `phil/resource` parviennent telles quelles à l'appelant quand une référence est mal formée.
