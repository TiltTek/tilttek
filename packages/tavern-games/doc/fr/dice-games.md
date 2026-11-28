<!-- phil-dev source-sha256=ed5d395ac0f5665cc71b4d6facf58e7bf78245cdae0aca714c3178e592eb9a05 -->
# Les jeux de dés

## Pig

`Game\Pig`, nommé `pig`, pour 2 à 8 sièges : à son tour, un siège lance un dé aussi souvent qu'il le veut, en ajoutant
chaque face au total du tour, et garde pour l'encaisser ; un 1 perd le total du tour et termine le tour. Le premier
siège à encaisser la cible gagne.

| Partie | Ce que c'est |
|---|---|
| Option | `target`, les points à encaisser : 100, de 20 à 200 |
| Coups | `pig.roll` et `pig.hold` ; le délai d'un tour garde |
| Issue | Le siège qui encaisse la cible gagne, `target-reached` ; les points encaissés de chaque siège comme `score` |
| Vue | Tout, Pig n'a aucun secret : le siège qui joue, les points encaissés, le total du tour, la dernière face, la cible |

## Navire, Capitaine et Équipage

`Game\ShipCaptainCrew`, nommé `ship-captain-crew`, pour 2 à 8 sièges : chaque siège lance à son tour cinq dés, jusqu'à
trois fois, et doit mettre de côté un 6 — le navire —, puis un 5 — le capitaine —, puis un 4 — l'équipage —, chacun
seulement une fois celui qui le précède mis de côté. Avec les trois, les deux autres dés sont la cargaison, que le
siège peut relancer tant qu'il lui reste des lancers. Une fois que chaque siège a joué, la plus grande cargaison
gagne.

| Partie | Ce que c'est |
|---|---|
| Coups | `ship-captain-crew.roll` ; `ship-captain-crew.stop`, qui garde la cargaison une fois l'équipage à bord ; le délai d'un tour arrête |
| Issue | La plus grande cargaison gagne, `most-cargo` ; des cargaisons égales partagent la victoire, `tie` ; quand personne n'en porte, la partie se termine sans gagnant, `no-cargo` ; la cargaison de chaque siège comme `cargo` |
| Vue | Tout : le siège qui joue, ses lancers, combien de navire, de capitaine et d'équipage sont à bord, les dés lancés en dernier, sa cargaison une fois l'équipage à bord, et la cargaison de chaque siège qui a joué |

Le navire, le capitaine et l'équipage sont mis de côté dès qu'un lancer les montre, dans leur ordre :
`ShipCaptainCrew::setAside()`. Un tour qui se termine sans l'équipage ne porte rien. `ShipCaptainCrew::chase()` donne
les chances du siège qui joue ([Chances](odds.md#la-poursuite-dune-cargaison)).
