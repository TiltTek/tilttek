<!-- phil-dev source-sha256=486aebba90264f8332443e505d7d0bdea0a7272976c6d6f72f04e8e24f4f1a88 -->
# Tests

## Les tests du bundle

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

Les tests unitaires vérifient la configuration et les services, `DiceStatistics` avec ses locales et ses arrondis, le
poids des groupes, les fonctions Twig et les bornes des fractions, avec et sans `ext-gmp`, les noms des composants du
kit, la limite de débit par réseau, et les résultats, les refus et les erreurs du composant. Le test fonctionnel démarre
une petite application (TilttekDiceBundle avec un dé personnalisé, Twig, TwigComponent et LiveComponent, en français)
avec les recettes du kit PHIL UX, de `phil/ux-kit`, copiées là où `ux:install` les met, à la racine du répertoire des
composants et dans un sous-répertoire, et produit le rendu du composant, du tableau, du graphique, des erreurs et des
refus, en moins d'une seconde pour les groupes qui coûtent le plus.

## Tester les pages qui montrent des probabilités

Les probabilités sont exactes : une page peut donc être comparée à son texte, `6/36`, `16.67 %`. Pour une estimation,
réglez peu d'échantillons et un germe dans la configuration de test (`samples: 200`, `seed: 42`) : la même estimation
revient chaque fois.

Un test fonctionnel qui envoie beaucoup de requêtes au composant désactive la limite de débit, ou la relève, dans sa
configuration : `rate_limit: false`.
