<!-- phil-dev source-sha256=eec4b87593d6a49c59252b9c107026670cad785fc62829742db5d14f255457d7 -->
# Les jeux d'exemple

Deux jeux traditionnels au crayon et au papier, libres de droits, écrits comme modèles d'un mini-jeu.

## Taureaux et Vaches

`Game\BullsAndCows`, nommé `bulls-and-cows` : trouver un code de chiffres différents. Chaque essai dit combien de ses
chiffres sont à la bonne place — les taureaux — et combien sont dans le code à une autre place — les vaches.

| Partie | Ce que c'est |
|---|---|
| Options | `length`, les chiffres du code : 4, de 3 à 5 ; `attempts`, les essais permis : 10, de 6 à 12 |
| Coup | `bulls-and-cows.guess`, `{guess: "0472"}` : autant de chiffres différents que le code ; toute autre chose est refusée par `IllegalMove` |
| Issue | Le code trouvé gagne, avec les essais pris comme `score` ; manquer d'essais termine la partie sans score |
| Pointage | `ScoreOrder::LowerIsBetter` |
| Commandes | `digit` sur `0` à `9`, `erase` sur `Backspace`, `guess` sur `Enter` |
| Vue | La longueur, chaque essai avec ses taureaux et ses vaches, les essais restants, et le code une fois la partie terminée |
| Partage | Une rangée par essai, les taureaux puis les vaches puis les ratés ; `taken/attempts`, `-` tant que la partie se poursuit, `X` quand le code n'a pas été trouvé |

`Game\Code` lit un essai — `Code::read('0472', 4)` — et le compare au code : `[bulls, cows]`.

## Plus ou Moins

`Game\HigherOrLower`, nommé `higher-or-lower` : un nombre de 1 à 100 est montré ; annoncez si le suivant est plus grand
ou plus petit, dix fois de suite. Les onze nombres sont tous différents.

| Partie | Ce que c'est |
|---|---|
| Coup | `higher-or-lower.call`, `{call: "higher"}` ou `{call: "lower"}` |
| Issue | Dix bons choix gagnent ; un mauvais choix termine la partie. Dans les deux cas, les bons choix sont rapportés comme `score` |
| Pointage | `ScoreOrder::HigherIsBetter` |
| Commandes | `higher` sur `ArrowUp` et `h`, `lower` sur `ArrowDown` et `l` |
| Vue | Les nombres montrés jusqu'ici — jamais le suivant —, si chaque choix était bon, et les choix restants |
| Partage | Une rangée de dix cellules : bon, mauvais, puis non joué ; `right/10` |
