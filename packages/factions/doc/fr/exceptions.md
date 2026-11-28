<!-- phil-dev source-sha256=ff9612de7bdcf1b4160137ff7baf830a963d9a109c458a720bd2e5f046d0dd18 -->
# Exceptions

Chaque exception de la bibliothèque implémente `Exception\FactionsException` : attrapez-la pour les attraper toutes.

| Exception | Étend | Levée quand |
|---|---|---|
| `Exception\InvalidRoster` | `InvalidArgumentException` | Une valeur sort de sa forme ou de ses bornes ; une clé, un sujet, un personnage ou une paire revient deux fois ; une position est hors de l'échelle ; une relation ou une appartenance nomme ce que la liste ou la faction n'a pas ; un appel nomme une faction ou une relation que la liste ne contient pas. |
| `Exception\RosterNotFound` | `RuntimeException` | Aucune liste n'est conservée pour la campagne. |
| `Exception\RosterRefused` | `RuntimeException` | Le membre n'est pas le maître de jeu de la liste ; une nouvelle liste n'est pas à la version 0 ou sa campagne en a déjà une ; une faction ajoutée contient des positions ou des membres. |
| `Exception\RosterConflict` | `RuntimeException` | La liste a changé depuis qu'elle a été lue, ou une nouvelle liste a la campagne d'une liste conservée. Rien n'est conservé : relisez-la et refaites le changement. |

Chaque message dit en anglais simple ce qui a été refusé, avec les clés et les identifiants en cause ; il ne contient
jamais le texte d'un résumé, d'une note ou d'une raison.
