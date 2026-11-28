<!-- phil-dev source-sha256=740efe2b87bcf3711c5ff380178320a685038243057ec79624d6e361873d40b5 -->
# Crazy Eights

`Game\CrazyEights`, nommé `crazy-eights`, pour 2 à 6 sièges, avec les 52 cartes du jeu à enseignes françaises.

## Les règles

Chaque siège reçoit une main et une carte est retournée sur la défausse. À son tour, un siège joue une carte de même
valeur ou de même enseigne que le dessus de la défausse, ou un huit, qui est un joker et nomme l'enseigne à suivre ; un
siège qui ne peut pas ou ne veut pas jouer pige une carte, puis joue ou passe. Quand la pioche est vide, la défausse
sous sa carte du dessus y est remélangée. Un huit retourné au début nomme sa propre enseigne.

| Partie | Ce que c'est |
|---|---|
| Option | `hand`, les cartes données à chaque siège : 5, de 3 à 8 |
| Coups | `crazy-eights.play` (`{card, suit}`, l'enseigne `S`, `H`, `D` ou `C`, pour un huit seulement), `crazy-eights.draw`, `crazy-eights.pass` ; le délai d'un tour pige une carte quand aucune n'a été piochée et qu'il en reste une, puis passe |
| Refus | Une carte absente de la main du siège, une carte qui ne suit ni l'enseigne ni la valeur, un huit sans enseigne, une deuxième pige dans un tour, un passe avant de piger alors qu'il reste une carte à piger, une pige sans rien à piger |

## Les fins

| Raison | Quand | Gagnants |
|---|---|---|
| `emptied-hand` | Un siège joue sa dernière carte | Ce siège |
| `blocked` | Il ne reste rien à piger, et chaque siège passe de suite | Le moins de cartes, des mains égales partageant la victoire |
| `too-long` | Les cartes ont vu `CrazyEights::MAX_GESTURES` gestes, 1 000 | Le moins de cartes, des mains égales partageant la victoire |

L'issue rapporte les cartes restantes de chaque main comme `cards`.

## Les cartes

Les cartes sont gardées par `tilttek/card-deck`. La mise en place tire une graine de 32 octets du hasard de la table et
distribue à partir d'un jeu mélangé avec elle ; les données de la table contiennent cette graine et les gestes des
cartes. Chaque coup et chaque vue rejouent les gestes dans un `DeckState` : une partie reste sous 1 000 gestes, ce qui
borne le rejeu ([Tests](testing.md#bancs-dessai)). Le siège `n` de la table, à partir de 0, est le joueur `p(n+1)` de
`tilttek/card-deck`.

## Ce que voit chaque siège

| Champ | Ce qu'il contient |
|---|---|
| `hand` | Les cartes propres du siège, chacune `{card, face}` ; aucune pour l'auditoire |
| `top` | La face du dessus de la défausse, `9C` |
| `suit` | L'enseigne à suivre : celle qu'un huit a nommée, sinon celle de la carte du dessus |
| `draw` | Les cartes restantes dans la pioche |
| `hands` | Combien de cartes chaque siège détient |
| `turn` | Le siège qui joue, nul une fois la partie terminée |
| `drawn` | Si le siège qui joue a pigé ce tour |

Un siège ne voit jamais les cartes d'un autre siège, l'ordre de la pioche, la graine des cartes ni leurs gestes.
