<!-- phil-dev source-sha256=335f0b77fccc0944d787655b81bf9100721b62d97edb46adc3cbc1a84271d763 -->
# OpenSkill

`OpenSkill\OpenSkillSystem` cote selon l'approximation bayésienne du classement en ligne publiée par Ruby C. Weng et
Chih-Jen Lin (*A Bayesian Approximation Method for Online Ranking*, Journal of Machine Learning Research 12, 2011). Son
identifiant est celui de son modèle : `openskill-pl` ou `openskill-tm`.

## Habiletés et incertitude

L'habileté d'un membre est une loi normale : sa moyenne `mu` et son écart-type `sigma`. Un nouveau membre commence à
`mu = 25` et `sigma = 25 / 3`. Avant chaque partie, `sigma` grandit de `tau`, pour qu'une habileté puisse encore changer
après de nombreuses parties. La moyenne d'un camp est la somme des moyennes de ses membres, et sa variance la somme de
leurs variances.

Le modèle donne à chaque camp une correction de sa moyenne, Ω, et de sa variance, Δ. Chaque membre en prend la part de
sa propre variance dans celle du camp, `σ² / σ²side` :

```text
mu ← mu + σ² / σ²side × Ω
σ² ← σ² × max(1 − σ² / σ²side × Δ, κ)
```

de sorte que le membre le plus incertain d'une équipe bouge le plus, et que `κ` garde de l'incertitude dans tous les cas.

## Les modèles

| Modèle | `OpenSkillModel::` | Lit un résultat comme |
|---|---|---|
| Plackett-Luce | `PlackettLuce` | L'ordre d'arrivée comme une suite de choix : chaque camp est choisi en premier parmi ceux qui restent, en proportion de `e^(mu/c)`. Les camps du même rang se partagent chaque choix. |
| Thurstone-Mosteller | `ThurstoneMosteller` | Chaque paire de camps comme un duel de deux performances normales, avec une marge de nulle. |

Plackett-Luce convient aux parties de nombreux joueurs ou équipes et est la valeur par défaut ; Thurstone-Mosteller
convient aux duels et aux jeux où les nulles sont fréquentes.

## Réglages

```php
new OpenSkillSystem(
    model: OpenSkillModel::PlackettLuce,
    mu: 25.0,              // the mean skill of a new member, 1 to 1 000
    sigma: null,           // its deviation; mu / 3
    beta: null,            // the deviation of one performance around the skill; sigma / 2
    tau: null,             // the growth of the deviation before each game; mu / 300
    kappa: 0.0001,         // the smallest share of a variance kept
    drawMargin: 0.1,       // the margin of a draw, Thurstone-Mosteller only
    z: 3.0,                // the deviations taken off for the conservative estimate, 0 to 10
    provisionalSigma: null, // the deviation above which a skill is provisional, above 0; none by default
);
```

`provisionalSigma` marque comme provisoire une habileté encore incertaine, quel que soit le nombre de parties jouées :
`Ratings::isProvisional()` le lit, à côté des parties que demande `provisionalGames` ; voir
[Les cotes provisoires](privacy-and-standings.md#les-cotes-provisoires).

## L'estimation prudente

Le classement ordonne les membres par `mu − z × sigma`, une habileté que le membre a au moins avec la confiance que
donne `z` — environ 99,9 % pour 3 —, en centièmes. Un nouveau membre commence à 0 et monte à mesure que ses résultats
confirment son niveau, plutôt que de dominer le classement après une victoire chanceuse. `SkillRating::conservative($z)`
la donne pour un autre `z`.

## Valeurs

`mu` et `sigma` sont gardés en millionièmes, `SkillRating::$mu` et `SkillRating::$sigma`, arrondis une fois par partie,
si bien qu'une relecture donne les mêmes valeurs. La loi normale est calculée par la bibliothèque : la fonction d'erreur
complémentaire par sa série de Maclaurin en dessous de 2 et sa fraction continue au-dessus, à la précision d'un nombre à
virgule flottante ; une correction trop loin dans la queue pour un tel nombre prend sa limite.
