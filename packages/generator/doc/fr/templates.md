<!-- phil-dev source-sha256=1f48632f34cf011fcc1f8d87615080baff0de486afc907b9548e420bd7205a02 -->
# Gabarits

Un gabarit est un gabarit Twig rendu en texte, sans échappement. Dans sa sortie, chaque suite d'espaces et de sauts de
ligne devient une seule espace, et les espaces des extrémités sont retirées. Un gabarit peut ainsi s'écrire sur
plusieurs lignes, et les parties facultatives ne laissent pas de double espace.

## Fonctions

| Fonction | Ce qu'elle donne |
|---|---|
| `pick('list')` | Une entrée, choisie selon le poids. |
| `pick('list', 2)` | Deux entrées sans doublons (selon le texte), ou moins quand la liste n'en a pas assez. |
| `pick('list', where: {gender: 'f'})` | Une entrée dont les étiquettes correspondent : chaque étiquette demandée a l'une des valeurs demandées (`{kind: ['bow', 'tool']}`). |
| `pick('list', not: x)` | Une entrée dont le texte n'est pas exclu : un texte, une entrée, ou une liste de ceux-ci, même imbriquée. |
| `generate('name')` | Le résultat d'un autre générateur. |
| `generate('name', 3)` | Trois résultats différents d'un autre générateur, ou moins quand il n'en trouve pas assez. |
| `generate('story', with: {hero: name})` | Le résultat d'un autre générateur, dont les gabarits voient les valeurs données comme des variables. |
| `roll('2d6+1')` | Le total d'un lancer de `tilttek/dice`, avec le hasard du tirage ; le premier total d'une réserve. |
| `between(1, 100)` | Un nombre entier du premier au second, tous deux compris, avec le hasard du tirage. |

Une entrée affiche son texte ; `entry.price` lit un champ et `entry.tags.kind` une étiquette. Un tirage simple qui ne
trouve plus aucune entrée une fois ses filtres et ses exclusions appliqués échoue avec `ExhaustedList` ; un tirage de
plusieurs entrées retourne ce qu'il a trouvé. Un choix unique (un seul gabarit, un seul candidat) ne consomme aucun
hasard. `roll` et `between` comptent comme des tirages.

```twig
{% set first = pick('name.syllable') %}{{ first }}{{ pick('name.syllable', not: first) }}
```

## Variables

`{% set %}` garde une valeur pour le reste du gabarit ; les `vars` du générateur gardent une valeur pour tout le
résultat, dans tous ses gabarits et ses champs. `generate(…, with: {…})` donne des valeurs au générateur qu'il appelle,
comme des variables de ses variables, de son corps et de ses champs : leurs noms sont une lettre puis des lettres, des
chiffres ou `_`, jamais `loop`, `state` ni `previous`. Les champs d'un résultat structuré sont des variables des champs
suivants. Un générateur muni d'un état le lit comme `state`, et l'état d'avant le résultat comme `previous` ; voyez
[Tables à mémoire](memory.md).

## Filtres

Les balises `set`, `if`, `for` et `apply` ; les tests `defined`, `empty`, `null`, `none`, `even`, `odd`,
`divisible by`, `same as` et `iterable` ; les filtres de Twig `capitalize`, `title`, `upper`, `lower`, `trim`,
`replace`, `slice`, `join`, `first`, `last`, `length`, `default`, `abs` et `round` (`replace` et `join` bornés par la
limite d'octets, voyez plus bas) ; et ceux du générateur :

| Filtre | Ce qu'il fait |
|---|---|
| `upper_first` | Une majuscule initiale, le reste intact (`capitalize` met le reste en minuscules). |
| `elide` | Les élisions et les contractions de la langue sur tout le texte ; un mot écrit précédé de `^` est gardé tel qu'écrit ([Grammaire](grammar.md#garder-un-mot-tel-quel)). |
| `article(kind, gender, plural)` | Le mot avec son article : `definite`, `indefinite`, `partitive` ou `demonstrative`. |
| `agree(with, plural)` | Un adjectif au genre et au nombre d'une entrée de nom, ou de ceux qui sont donnés. |
| `plural(count)` | Le pluriel d'un mot, ou le mot lui-même quand le nombre prend le singulier. |

Voyez [Grammaire](grammar.md).

## Le bac à sable

Les gabarits s'exécutent dans le bac à sable de Twig, avec seulement les balises, les filtres, les fonctions et les
tests ci-dessus. Ils ne voient aucun objet sauf les entrées, dont ils ne lisent que le texte et les champs. L'opérateur
d'intervalle `..` est refusé, car il pourrait construire un tableau de n'importe quelle taille ; les boucles
parcourent des listes produites par des tirages, que borne la limite de tirages.

## Le budget d'un résultat

Le bac à sable borne ce qu'un gabarit atteint ; les limites bornent ce qu'il coûte, dans le gabarit comme entre les
gabarits :

- chaque tour d'une boucle `for` et chaque `{% set %}` est un pas, compté contre `Limits::$steps` ; à chaque pas, la
  mémoire dont le résultat a crû est comparée à `Limits::$megabytes`, et son temps et celui du tirage à leurs limites ;
- chaque tirage dans une liste, lancer, `between` et appel de générateur compte contre `Limits::$picks` et vérifie
  aussi les temps ;
- `replace` et `join` refusent de faire un texte plus long que `Limits::$bytes`, mesuré avant que le texte soit fait :
  pour `replace`, à partir des occurrences de chaque recherche dans le texte et de la longueur qu'ajoute son
  remplacement, pour `join`, à partir de ses parties et de ses liants ;
- l'opérateur d'intervalle `..` est refusé, et les boucles parcourent les listes que des tirages ont produites.

Une boucle qui ne tire jamais s'arrête à sa limite de pas ; un texte qui double à chaque affectation s'arrête au pas
qui franchit la limite de mémoire. Au-delà de l'une d'elles, le tirage échoue avec `LimitExceeded`. Le bac à sable de
Twig, un environnement Twig dédié et ces limites permettent ensemble à une application de charger des gabarits écrits
par d'autres (ses membres, ou les auteurs d'une grammaire importée) et d'y faire des tirages à un coût borné ; les
[vérifications](#les-vérifications) s'exécutent toujours sur chaque gabarit avant tout tirage.

## Les vérifications

Les vérifications s'exécutent à la construction du `Generator`, sur chaque gabarit, variable, champ, case et valeur d'un
état :

- le gabarit compile ;
- il n'utilise rien en dehors des listes du bac à sable ;
- chaque liste, étiquette et générateur qu'il nomme en toutes lettres existe (`pick('a.list', where: {gender: 'f'})`,
  `generate('npc')`) ; un nom donné par une variable est vérifié au moment du tirage ;
- chaque générateur que nomme un champ ou une case existe, l'expression de dés d'un graphe s'analyse, et la langue et
  les filtres du générateur existent.

Chaque problème est énuméré dans une seule `InvalidCatalog`, chacun avec son gabarit : `inn:field:menu: the generator
"dish" does not exist`.
