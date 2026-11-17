<!-- phil-dev source-sha256=a95f26eabd39b37601b6f692297e1792906bc69b63a5d5d10f3135842c480e5a -->
# Exceptions

Chaque exception de la bibliothèque implémente `Tilttek\Quests\Exception\QuestsException`.

| Exception | Levée quand | Message, par exemple |
|---|---|---|
| `InvalidQuest` | Un code, une étape, une récompense, une quête, un calendrier, une définition, un enchaînement, une clé de duel ou une durée est refusé | `"Read" is not a step code: 1 to 64 lower-case letters, digits, "_", "." or "-", starting with a letter.` |
| `UnknownQuest` | Une quête est demandée par un code qu'aucune ne porte | `No quest is named "winter".` |
| `UnknownDuel` | Un duel est demandé par une clé qu'aucun ne porte | `No duel has the key "duel:9".` |
| `QuestRefused` | Ce qu'un membre a demandé est impossible : une quête fermée, verrouillée, accomplie ou jamais acceptée, une équipe absente, une équipe dont le membre ne fait pas partie ou une autre équipe, un duel auquel répond le mauvais côté ou qui n'attend plus de réponse | `"winter-reading" opens once "autumn-reading" is accomplished.` |

Les motifs de verbes et les conditions de contexte d'une étape sont vérifiés par `tilttek/activity` ; leurs messages
sont portés par une `InvalidQuest`.
