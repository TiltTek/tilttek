<!-- phil-dev source-sha256=3397f93470776687611701da95a2cd6ff96f4a411c4c5b861970d68e37727200 -->
# Gabarits

## Fonctions Twig

| Fonction ou filtre | Ce qu'elle donne |
|---|---|
| `tilttek_dice_distribution(expression, locale = null, rounding = null)` | La `Distribution` d'une expression |
| `tilttek_dice_versus(first, second, locale = null, rounding = null)` | Le `Versus` de deux expressions |
| `tilttek_dice_probabilities(expression, versus = null, show = 'exactly', locale = null, rounding = null)` | Le tableau et le graphique, rendus |
| `fraction\|tilttek_dice_fraction(digits = 12)` | Une probabilité sous sa forme irréductible, `4/9` ; null quand son dénominateur a plus de chiffres que `digits`, ou qu'un terme a plus du double de chiffres avant d'être réduit |

```twig
{% set distribution = tilttek_dice_distribution('4d6kh3') %}
<p>{{ (distribution.atLeast(15).toFloat * 100)|number_format(1) }} % to roll 15 or more.</p>

{{ tilttek_dice_probabilities('1d20+5', '2d10+4') }}
{{ tilttek_dice_probabilities('3d6', show: 'at_least') }}
```

L'arrondi est `floor`, `ceil`, `round`, `towards_zero` ou `away_from_zero`. Une expression invalide lève une exception,
comme le ferait un jet.

Le travail de `tilttek_dice_fraction` est borné, puisqu'un long tableau réduit trois fractions par ligne. `digits` est
pris entre 1 et 60. Un terme de plus du double de chiffres n'est pas réduit et donne null, d'après sa seule taille ; les
termes d'au plus 18 chiffres sont réduits sur des entiers natifs ; les plus longs, par `ext-gmp`, et donnent null sans
lui. Le pourcentage à côté dit la probabilité dans les deux cas.

`tilttek_dice_probability_kit(component)` donne le nom sous lequel l'application a installé un composant du kit UX :
`ui:Card` pour `Card` quand ses recettes sont dans `ui`. Les gabarits appellent
`{% component (tilttek_dice_probability_kit('Card')) %}`, et une application qui les remplace fait de même.

## Le tableau et le graphique

`@TilttekDiceProbability/probabilities.html.twig` montre, pour une expression, dans des cartes du kit UX :

- un résumé : la moyenne, l'écart type et l'étendue ;
- quand elle est estimée, de combien de jets elle a été tirée, pourquoi, et sa marge ; quand une explosion a été coupée
  à sa profondeur, la part des jets qui auraient explosé plus loin ;
- un graphique à barres de la probabilité de chaque valeur, ou d'au moins cette valeur, ou d'au plus cette valeur, en
  pourcentage, dessiné en SVG sans script par le `Chart` du kit ;
- un tableau du kit, `Table`, de chaque valeur avec sa probabilité, au moins et au plus, chacune en pourcentage et, quand elle est assez
  courte, en fraction irréductible.

Avec une seconde expression, il montre d'abord à quelle fréquence chacune est plus haute et à quelle fréquence les deux
sont égales, puis les deux tableaux.

**Le tableau est toujours présent.** Le graphique, avec le tableau de valeurs que le `Chart` du kit écrit pour les
lecteurs d'écran, est caché aux technologies d'assistance, `aria-hidden="true"` : le tableau du bundle, avec sa légende
et ses en-têtes de colonnes et de lignes, dit la même chose et davantage à tout le monde, et reste quand les styles ne
se chargent pas.

L'apparence est celle des recettes du kit et de phil-ux. Les classes, pour la mettre davantage en forme :
`tilttek-dice-probabilities`, `tilttek-dice-probabilities__versus`, `tilttek-dice-distribution`, `__summary`,
`__estimate`, `__remaining`, `__chart`, `__table`, et `tilttek-dice-probability` avec `__fraction` pour chaque
probabilité.

## Le composant

```twig
<twig:TilttekDiceProbability:Distribution expression="4d6kh3" />
<twig:TilttekDiceProbability:Distribution expression="1d20+5" versus="2d10+4" show="at_least" locale="fr" rounding="ceil" />
```

Un Live Component : ses champs (l'expression, une seconde à lui comparer, et ce que montre le graphique) recalculent les
probabilités pendant la saisie, sans recharger la page. Les champs sont le `Field` du kit, chacun avec son étiquette, et
le choix du graphique est un `RadioGroup` dans un ensemble de champs avec sa légende ; les champs de texte et les
boutons radio sont écrits avec le balisage de `Input` et de `RadioGroup:Item` du kit, puisqu'un `data-model` donné à un
composant enfant lierait l'enfant au lieu du champ. Les résultats sont annoncés poliment aux lecteurs d'écran
(`aria-live="polite"`). Une expression sans probabilités dit pourquoi dans l'`Alert` du kit : avec les mots de
`tilttek/dice` et de `tilttek/dice-probability` ou, pour un refus du bundle (groupes trop longs à chercher, trop de
valeurs, limite de débit atteinte), avec sa raison traduite.

Chaque fois que le navigateur renvoie les expressions, un calcul est compté contre la limite de débit du client ; la
[configuration](configuration.md#la-limite-de-débit-du-composant) la règle, ou la désactive.

| Propriété | Modifiable | Ce qu'elle contient |
|---|---|---|
| `expression` | oui | L'expression ; `2d6` par défaut |
| `versus` | oui | Une seconde expression ; aucune quand elle est vide |
| `show` | oui | `exactly`, `at_least` ou `at_most` ; une valeur inconnue montre `exactly` |
| `locale` | non | La notation dans laquelle les expressions sont écrites |
| `rounding` | non | L'arrondi des divisions ; celui de la configuration quand il est null ou inconnu |

Le composant n'écrit aucun JavaScript qui lui soit propre : les Live Components apportent le leur.
