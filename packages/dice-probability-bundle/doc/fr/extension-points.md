<!-- phil-dev source-sha256=d6b11ec029e27d7cabcb43d7baf9b162191fe6e79df906fcfe71edeac02daf0d -->
# Points d'extension

## Gabarits

Remplacez les gabarits comme pour tout bundle, sous `templates/bundles/TilttekDiceProbabilityBundle/` :

| Gabarit | Ce qu'il dessine |
|---|---|
| `probabilities.html.twig` | La comparaison, puis chaque distribution |
| `_distribution.html.twig` | Une distribution : son résumé, son graphique et son tableau |
| `_probability.html.twig` | Une probabilité : son pourcentage et sa fraction |
| `components/Distribution.html.twig` | Le composant : ses champs, puis les résultats |

Un gabarit de remplacement garde le tableau : c'est la forme accessible du graphique. Il nomme les composants du kit par `tilttek_dice_probability_kit()`, afin de
suivre le sous-répertoire de l'application.

## Les recettes du kit UX

Les recettes que `ux:install` a copiées sont celles de l'application : changez leur Twig, ou installez-les depuis
`github.com/parler-haut-interagir-librement/ux-kit-shadcn` ou un autre kit aux mêmes noms et aux mêmes propriétés. Le
bundle a besoin de `Alert`, `Card`, `Chart`, `Field`, `Input`, `RadioGroup` et `Table`, avec leurs parties.

## Traductions

Les mots sont dans le domaine `tilttek_dice_probability`, en anglais et en français. Pour une autre langue, ajoutez un
fichier `translations/tilttek_dice_probability.<locale>.yaml` avec les clés des fichiers du bundle. Les raisons des refus du bundle sont sous `refusal` : `groups`, `outcomes` avec
`%outcomes%` et `%limit%`, et `throttled`.

## La limite de débit

`rate_limit.limiter` nomme une fabrique de limiteurs de débit de l'application, tout `RateLimiterFactoryInterface` : une
politique de `framework.rate_limiter`, une politique composée, ou une politique propre à l'application. Le composant lui
demande un limiteur par réseau de client, par une empreinte à clé, et consomme un jeton par calcul.

## Événements

Les événements de l'application implémentent `Tilttek\DiceProbability\Event\Event` et sont passés à
`DiceStatistics::chance()` : voir les points d'extension de `tilttek/dice-probability`.
