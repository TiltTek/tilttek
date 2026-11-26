<!-- phil-dev source-sha256=321109972929f409eab3c0c22beb9e14bec1d4ee61c171d0d9d7a75d42690f1b -->
# Partager un résultat

Un `ShareGrid` est un résultat à coller n'importe où : des rangées de cellules et un sommaire.

```text
Bulls and Cows 12 4/10

🟨🟨⬛⬛
🟩🟨🟨⬛
🟩🟩🟩⬛
🟩🟩🟩🟩
```

## Les cellules

| `Cell` | Signifie |
|---|---|
| `Hit` | Bon, et à la bonne place |
| `Near` | Bon, mais à une autre place |
| `Miss` | Pas bon |
| `Empty` | Non joué |

Une grille contient jusqu'à 32 rangées de jusqu'à 16 cellules, et un sommaire d'au plus 32 caractères ASCII imprimables :
`4/10`, `X/10`.

## Ce qu'une grille ne dit jamais

Une grille dit comment chaque étape s'est passée, jamais ce qu'elle était. Un jeu la construit de sorte que personne qui
n'a pas joué ne puisse en déduire la réponse : Taureaux et Vaches écrit les taureaux d'un essai avant ses vaches, pour
que les places restent cachées ; Plus ou Moins écrit si un choix était bon, jamais s'il était plus grand ou plus petit,
ce qui trahirait les nombres.

## Écrire une grille

`toText($title, $symbols)` écrit une ligne de titre — le titre que l'application écrit dans sa langue, puis le sommaire
—, une ligne vide, et une ligne par rangée. Les suites d'espaces et les sauts de ligne du titre deviennent une seule
espace.

| `CellSymbols` | Bon, proche, raté, vide |
|---|---|
| `CellSymbols::squares()`, la valeur par défaut | 🟩 🟨 ⬛ ⬜ |
| `CellSymbols::plain()` | `#` `+` `.` `-` |
| `new CellSymbols(…)` | Quatre symboles de 1 à 16 octets d'UTF-8 chacun, sur une seule ligne |

Les symboles simples conviennent à un lecteur d'écran, qui lit les carrés colorés un nom à la fois, et à une police qui
ne les a pas. Une application peut offrir les deux, et décrire la grille en mots à côté d'elle.
