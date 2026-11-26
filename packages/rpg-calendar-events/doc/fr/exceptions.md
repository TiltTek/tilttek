<!-- phil-dev source-sha256=bdd4d86a382b2d86d6ade2cf07654d6d65658e62a305fbcf79b313633858863a -->
# Exceptions

Chaque exception du paquet implémente `Tilttek\RpgCalendarEvents\Exception\RpgCalendarEventsException`. Un programme
qui montre le catalogue attrape cette interface ; celui qui charge ses propres lignes les lit d'abord avec `DataCheck`,
qui liste chaque problème au lieu de lever une exception au premier.

| Exception | Levée quand | Message, par exemple |
|---|---|---|
| `InvalidEntry` | Une ligne ou un `Entry` est refusé — un champ inconnu, une règle qui ne se lit pas, une source qui n'est pas une adresse https sur un nom d'hôte, un nom, une étiquette, une communauté ou un nombre de jours hors limites, une invite par jour sur un seul jour —, ou un identifiant est donné deux fois à un catalogue | `The entry "rpgaday" has no source: an https address on a host name, of at most 2048 bytes, without spaces.` |
| `InvalidQuery` | Une année hors de 1000 à 3000, une période qui sort de ces années, un identifiant que le catalogue ne contient pas, ou un filtre sur une liste vide d'étiquettes ou de communautés | `The catalog reads the years 1000 to 3000, not 3001.` |

Les deux étendent `InvalidArgumentException`. Le message d'une `InvalidEntry` nomme l'entrée par son identifiant,
coupé à 100 caractères, ou par `?` quand la ligne n'en a pas ; le texte tiré d'une ligne — un champ inconnu, une
locale, une étiquette — est coupé lui aussi, de sorte qu'un message reste court quoi que la ligne contienne.

`DataCheck::problems()` transforme chaque `InvalidEntry` d'une ligne en une ligne de texte,
`Row 3: The entry "gm-day" …`, et en ajoute une pour un identifiant présent deux fois :
`Row 5: the identifier "gm-day" is already row 3.`
