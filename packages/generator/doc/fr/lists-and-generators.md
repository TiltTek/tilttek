<!-- phil-dev source-sha256=2672849b36905f5c6a9e671b6cb653fab580568eae7582d5c752a1c363839c03 -->
# Listes et générateurs

Un catalogue se lit à partir de documents YAML ou JSON décodés. Un document contient des tables `lists` et
`generators` par nom, ou une seule liste, ou un seul générateur :

```yaml
lists:
  name.elf.first: [Aelis, Lórien]
generators:
  name.elf:
    templates: "{{ pick('name.elf.first') }}"
```

```yaml
list: weapon
entries: [sword, bow]
```

```yaml
generator: weapon.name
templates: "{{ pick('weapon')|capitalize }}"
```

`FileLoader` lit les fichiers `.yaml`, `.yml` et `.json`, donnés un par un ou par répertoire, parcouru en profondeur,
dans l'ordre de leurs chemins. `Reader` lit des documents déjà décodés, par le nom de leur source. Un document de
mauvaise forme est une `InvalidDefinition` qui dit où : `inn.yaml > generators > inn > fields > staff: …`. Deux listes
ou deux générateurs du même nom sont une erreur, où qu'ils soient.

## Noms

Un nom est fait de lettres, de chiffres, de `_` et de `-`, en parties reliées par des points :
`name.fantasy.elf.female.first`. Les points ne servent qu'à ordonner les noms pour les gens ; la bibliothèque ne les
lit pas. `Reader::isName()` dit si un texte est un nom. Un nom fait de chiffres seulement, comme `12`, que YAML et JSON décodent
comme un nombre quand il est une clé, se lit comme un nom.

## Listes et entrées

Une liste est une séquence d'au moins une entrée. Une entrée est un texte, ou une table :

```yaml
lists:
  weapon:
    - dagger
    - { text: épée, weight: 3, gender: f, price: 12, tags: { kind: blade, era: [old, middle] } }
```

| Clé | Sens |
|---|---|
| `text` | Ce qu'affiche l'entrée. Obligatoire dans une table. |
| `weight` | Un nombre entier d'au moins 1, 1 par défaut : une entrée de poids 3 revient trois fois plus souvent. |
| `tags` | Une table d'étiquettes, chacune avec une valeur ou une séquence de valeurs, sur lesquelles filtre `pick(…, where: …)`. |
| `source`, `licence`, `attribution` | D'où vient cette entrée, quand ce n'est pas d'où vient sa liste ; voyez [D'où vient le texte](#dou-vient-le-texte). |
| toute autre clé | Un champ : un scalaire ou une séquence de scalaires, lu dans un gabarit comme `entry.price`. |

Une liste peut aussi s'écrire comme une table de ses entrées (`entries`), de sa chaîne de Markov (`markov`) et de son
origine ; voyez [Listes de Markov](#listes-de-markov) et [D'où vient le texte](#dou-vient-le-texte).

Ce que d'autres générateurs écrivent en intervalles d'index (« les six premiers adjectifs ne vont qu'avec les quatre
premiers noms ») s'écrit ici avec des étiquettes. Un filtre sur une étiquette qu'aucune entrée de la liste ne porte est
une erreur, et non un tirage vide.

Les filtres de grammaire lisent les champs `gender` (`m`, `f` ou `n`), `number` (`p` pour un mot toujours au pluriel),
`plural` et, pour les adjectifs, `m`, `f`, `mp` et `fp` ; un champ peut aussi être donné comme étiquette du même nom.

## Listes de Markov

Une liste munie d'une chaîne `markov` ne rend pas ses entrées : un tirage invente un mot nouveau à partir d'elles, lettre
par lettre. La chaîne apprend, pour chaque suite de lettres aussi longue que son ordre, quelles lettres suivent et à
quelle fréquence, comptée selon le poids des entrées ; un mot s'écrit du début d'un mot à sa fin, en minuscules, avec une
majuscule initiale quand toutes les entrées en ont une.

```yaml
lists:
  name.elf:
    markov: { order: 2, min_length: 4, max_length: 9 }
    entries:
      - { text: Aelis, tags: { gender: f } }
      - { text: Lórien, tags: { gender: f } }
      - { text: Thalion, tags: { gender: m } }
```

| Clé | Sens |
|---|---|
| `order` | Combien de lettres choisissent la suivante, de 1 à 6, 2 par défaut : un ordre plus élevé recopie davantage les entrées. |
| `min_length` | Le mot le plus court, en lettres, 3 par défaut. |
| `max_length` | Le mot le plus long, du plus court à 64, 16 par défaut. |
| `allow_entries` | Si une entrée peut sortir telle quelle, `false` par défaut. |

`markov: true` prend les valeurs par défaut. `pick('name.elf', where: {gender: 'f'})` invente à partir des entrées dont
les étiquettes correspondent, et le mot inventé porte les étiquettes que le tirage demandait : `|article` et `|agree`
lisent ainsi son genre. `pick('name.elf', 3)` invente trois mots différents, et `not:` exclut des mots, comparés sans
égard à la casse. Chaque essai de mot compte comme un tirage : un tirage essaie `MarkovChain::ATTEMPTS` (50) fois pour
chaque mot demandé, et un tirage simple qui n'invente rien de nouveau échoue avec `ExhaustedList`. Une liste de peu
d'entrées, ou d'entrées très courtes, invente peu de mots : baissez l'ordre, ou permettez les entrées.

## Générateurs

Un générateur a exactement un corps, et peut avoir une langue, des variables et un état :

| Clé | Sens |
|---|---|
| `templates` | Un gabarit, ou une séquence de gabarits, chacun un texte ou `{template: …, weight: …}` : un seul est choisi selon le poids pour chaque résultat. |
| `filters` | Avec `templates` seulement : des filtres sans arguments appliqués dans l'ordre au texte entier, comme `upper_first`, `capitalize` ou `elide`. |
| `fields` | Un résultat structuré : une table de champs, chacun un gabarit ou `{generate: name, count: 3}`. |
| `map` | Une carte de cases ou un graphe de nœuds ; voyez [Cartes](maps.md). |
| `vars` | Des gabarits par nom de variable, rendus une fois par résultat, dans l'ordre, avant le corps ; chacun voit les précédents. |
| `locale` | La langue des filtres de grammaire, comme `fr` ou `en_CA` ; sinon, celle du générateur par défaut. |
| `state` | Des valeurs portées d'un résultat au suivant : une table à mémoire ; voyez [Tables à mémoire](memory.md). |
| `source`, `licence`, `attribution` | D'où vient le texte de ses gabarits ; voyez [D'où vient le texte](#dou-vient-le-texte). |

Les champs d'un résultat structuré sont remplis dans l'ordre, et chacun voit les précédents comme des variables :

```yaml
generators:
  inn:
    vars:
      keeper: "{{ generate('person.name') }}"
    fields:
      name: "The {{ pick('inn.animal')|capitalize }}"
      keeper: "{{ keeper }}"
      description: "{{ name }}, kept by {{ keeper }}"
      staff: { generate: person.name, count: 3 }
      menu: { generate: inn.dish, count: 4 }
```

Un champ rempli par `generate` garde le résultat de l'autre générateur tel quel (un texte, un résultat structuré, une
carte), et un nombre donne une liste de résultats différents.

## D'où vient le texte

Une liste, une entrée et un générateur disent d'où vient leur texte par trois textes, chacun facultatif mais au moins
un donné, chacun de 1 à 1024 caractères en UTF-8 :

| Clé | Signification |
|---|---|
| `source` | D'où le texte a été pris : un livre, un site, un ensemble de tables. |
| `licence` | La licence qui le couvre, de préférence écrite comme un identifiant SPDX tel que `CC-BY-4.0`. |
| `attribution` | Le crédit que sa licence demande d'afficher, tel qu'il doit être affiché. |

```yaml
lists:
  oracle.action:
    source: The tables of a shared game
    licence: CC-BY-4.0
    attribution: Oracle tables by A. Author, CC BY 4.0
    entries:
      - seek
      - { text: defend, source: Another book, licence: CC0-1.0 }
```

Les textes propres d'une entrée prennent la place de ceux de sa liste. Un tirage donne les attributions de chaque
liste, entrée et générateur que ses résultats ont utilisés ([Tirages](drawing.md#attributions)), que l'application
affiche avec eux. Les noms `source`, `licence` et `attribution` ne sont pas des champs d'une entrée. Les importateurs
donnent une attribution à chaque liste et à chaque générateur qu'ils font ([Importations](imports.md)).

## Exemples neutres

Le répertoire `examples/` du paquet contient quelques générateurs qui se chargent avec `FileLoader` et que les tests
tirent : des noms syllabiques et des noms inventés par une chaîne de Markov, un oracle oui ou non dont les réponses
suivent un facteur de chaos, une auberge en français, un village sur une grille carrée, un donjon de salles, et trois
tables à mémoire : la météo, un voyage et l'exploration d'une carte d'hexagones. Ils montrent la syntaxe ; les listes
d'une application lui appartiennent. Le répertoire `tables/` contient des tables aléatoires prêtes à tirer ; voyez
[Tables aléatoires](tables.md).
