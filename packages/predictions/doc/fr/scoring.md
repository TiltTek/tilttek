<!-- phil-dev source-sha256=48104b919587c5e66c867e83bed66dc1821742397598996ef83a5d65374f8341 -->
# Notation

## Par choix

Le membre choisit un résultat. Un bon choix rapporte les points de la question ; un mauvais ne rapporte rien et ne coûte
rien.

## Par probabilité

Le membre donne à chaque résultat une probabilité, en pourcentages entiers dont la somme est 100. Une fois le résultat
déclaré, la prévision est notée par le score de Brier : la somme, sur les résultats, du carré de l'écart entre la
probabilité donnée et ce qui s'est produit — 100 % pour le résultat qui s'est produit, 0 % pour les autres. Une
prévision parfaite obtient 0 ; une confiance totale en un résultat qui ne s'est pas produit obtient 2.

La bibliothèque le calcule en nombres entiers, en dix-millièmes, pour qu'aucun arrondi de nombres à virgule flottante ne
change les points d'un membre : de 0 à 20 000. Les points sont la part des points de la question qui reste une fois le
score retranché de 20 000, arrondie à la demie supérieure :

```text
points = round(maximum × (20000 − score) / 20000)
```

Le score de Brier est une règle de notation propre : un membre gagne le plus, en moyenne, en donnant les probabilités
auxquelles il croit. Un 100 % audacieux rapporte tout quand il est juste et rien quand il ne l'est pas ; une prévision
prudente rapporte quelques points dans les deux cas.

## Valeurs calculées

Pour une question de 100 points :

| Prévision | Résultat | Score | Points |
|---|---|---|---|
| yes 100 %, no 0 % | yes | 0 | 100 |
| yes 100 %, no 0 % | no | 20 000 | 0 |
| yes 50 %, no 50 % | l'un ou l'autre | 5 000 | 75 |
| yes 70 %, no 30 % | yes | 1 800 | 91 |
| yes 70 %, no 30 % | no | 9 800 | 51 |
| home 60 %, draw 30 %, away 10 % | home | 2 600 | 87 |
| home 60 %, draw 30 %, away 10 % | away | 12 600 | 37 |

`Brier::score()` et `Brier::points()` les calculent pour une application qui montre son score à un membre.
