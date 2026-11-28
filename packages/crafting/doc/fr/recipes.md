<!-- phil-dev source-sha256=3929568578b445e56d46a50a5599d9da2701f0b414635dadaf4b17d316530fdd -->
# Recettes

Une `Recipe` est une donnée : un code, les ingrédients qu'elle prend ou dont elle a besoin, les objets qu'elle produit,
les objets qu'elle rend, son genre et ses attributs. Elle est toujours déterministe — elle produit ce qu'elle déclare, à
chaque fois. Un résultat aléatoire relève d'une table de butin dont les probabilités sont publiées, tirée par
l'application après la fabrication.

## Ingrédients

Un `Ingredient` demande un objet par son code, `Ingredient::item('red-herb', 2)`, ou n'importe quels objets qui portent
une étiquette, `Ingredient::tagged('rare-metal')` — un « métal rare » plutôt qu'un métal précis. Sa quantité va de 1 à
999.

Un ingrédient consommé est pris par la fabrication, sa quantité une fois par répétition : fabriquer trois potions prend
six herbes. Un **outil** — `consumed: false`, un mortier, un marteau, un atelier — est seulement détenu : la
fabrication a besoin de sa quantité une seule fois, quel que soit le nombre de répétitions, et le laisse. Une recette
consomme au moins un ingrédient, pour que des outils seuls ne produisent jamais rien, et nomme un ingrédient au plus une
fois comme consommé et une fois comme outil.

## Produits et objets rendus

Les produits sont les objets que la fabrication produit, de 1 à 8, chacun en une quantité de 1 à 999 par répétition. Les
objets rendus, jusqu'à 8, sont ce qui reste d'un ingrédient — le seau vide d'un seau d'eau, la fiole d'une potion —,
aussi par répétition. Un objet est nommé une fois parmi les produits et une fois parmi les objets rendus.

## Recettes qui font évoluer

Une recette du genre `RecipeKind::Evolve` transforme un objet en sa forme suivante : un œuf de dragon couvé par deux
feux en dragon, une lame trempée en une meilleure lame. Son premier ingrédient est cet objet, par son code, consommé,
en une quantité de un ; elle a exactement un produit, d'un autre objet, en une quantité de un. Les autres ingrédients et
les objets rendus sont libres. La fabrication nomme l'objet qui a évolué, afin qu'un inventaire qui garde des
objets — un exemplaire avec son propre identifiant — puisse le garder comme le même objet sous son nouvel objet.

## Attributs

Une recette porte jusqu'à 10 attributs pour l'application — `['minutes' => 10, 'workshop' => 'forge']` — : chacun est
nommé par un code, et sa valeur est un entier, un booléen ou de 1 à 100 caractères UTF-8 sans caractère de contrôle ni
espace en début ou en fin. La bibliothèque ne les lit jamais : un grandeur nature compte le temps de fabrication en
temps de jeu, un site montre l'atelier.

Un code — d'une recette, d'un objet, d'une étiquette ou d'un attribut — compte de 1 à 96 lettres minuscules, chiffres,
`_`, `-`, ou points simples entre eux. L'application traduit les codes en noms, `crafting.recipe.healing-potion`, de
sorte que tout texte reste hors du livre.

## Le livre

Un `RecipeBook` contient jusqu'à 1 000 recettes, chaque code une seule fois. `fromArray()` le lit depuis des valeurs
simples — un fichier YAML, une colonne JSON — et refuse toute clé qu'il ne connaît pas ; `toArray()` le réécrit, chaque
valeur écrite en toutes lettres :

```php
$book = RecipeBook::fromArray(['recipes' => [
    'healing-potion' => [
        'kind' => 'combine',                                  // or 'evolve'; 'combine' when left out
        'ingredients' => [
            ['item' => 'red-herb', 'quantity' => 2],
            ['tag' => 'clear-water'],                         // quantity 1 when left out
            ['item' => 'mortar', 'consumed' => false],        // a tool, held and never taken
        ],
        'outputs' => [['item' => 'healing-potion']],
        'returns' => [['item' => 'empty-bucket']],            // none when left out
        'attributes' => ['minutes' => 10],                    // none when left out
    ],
]]);
```

`recipe()` renvoie une recette par son code, `has()` dit si le livre la contient, `recipes()` les liste dans l'ordre où
le livre les a reçues, `consuming()` liste celles qui consomment un objet nommé par son code — ce qu'un membre peut faire
avec ce qu'il détient — et `producing()` celles qui produisent un objet — comment l'obtenir.

## Objets protégés

Un objet qu'un membre a payé, ou que le site garde hors de la fabrication, n'est jamais consommé. Le livre reçoit une
`Contract\ConsumptionPolicy` — `Policy\ProtectedItems` pour une liste fixe — : il refuse une recette qui consomme un tel
objet par son code, et une étiquette n'en choisit jamais un pour un ingrédient consommé. Un objet protégé peut quand
même servir d'outil.
