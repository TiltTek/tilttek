<!-- phil-dev source-sha256=e12584d52d940a27e07be07dbee80c9260ce0984c61921f333ba364ee3b20794 -->
# Tables aléatoires

Le répertoire `tables/` du paquet contient des tables aléatoires prêtes à tirer, ou à copier et à adapter : les
générateurs que la plupart des jeux demandent, écrits avec la syntaxe de la bibliothèque. Leur contenu est neutre, lié à
aucun jeu. Chaque table est écrite en anglais, dans `tables/en/`, et en français, dans `tables/fr/`. `Tables::directory()`
donne le répertoire des tables anglaises et `Tables::directory('fr')` celui des tables françaises, `Tables::file('shop',
'fr')` le fichier d'une table, `Tables::NAMES` leurs noms et `Tables::LOCALES` leurs langues ; une autre langue est une
`InvalidDefinition` :

```php
use Tilttek\Generator\Generator;
use Tilttek\Generator\Loading\FileLoader;
use Tilttek\Generator\Tables;

$generator = new Generator(new FileLoader()->catalog([Tables::directory('fr'), 'generators']));
$generator->draw('table.shop')->first();
```

Les deux versions d'une table ont la même forme : les mêmes listes, les mêmes générateurs, les mêmes valeurs d'état, les
mêmes variables et les mêmes champs, sous les mêmes noms. Une application charge une langue ou l'autre, jamais les deux,
puisque leurs noms sont les mêmes, et garde ses états quand elle change de langue : les valeurs que l'application fixe
gardent leurs identifiants en français aussi (`terrain: forest`, `odds: likely`, `chaos: 6`), sauf le lieu des
rumeurs, un texte écrit avec son article (`le village`). Les tables françaises ne sont pas traduites mot à mot : elles
sont écrites en français, avec les filtres de grammaire de la langue : l'accord des créatures, l'élision des accroches
et des rumeurs, l'article de l'enseigne d'une boutique.

Chaque table est un fichier dont les listes et les générateurs se nomment `table.<name>` et `table.<name>.…`, et qui ne
nomme rien hors de son fichier : un fichier peut se charger seul, ou se copier sous un autre nom et s'adapter. Charger
une table et la copie qu'en a faite une application donne deux listes du même nom, que le catalogue refuse : une
application prend la table, ou sa copie.

| Table | Générateurs | Ce qu'ils donnent |
|---|---|---|
| `encounter` | `table.encounter` | Qui le groupe rencontre, combien, ce qu'ils font, à quelle distance et comment ils le prennent. Le terrain (`forest`, `road`, `hills`, `swamp`, `ruins` ou `underground`) est une valeur d'état que fixe l'application ; `road` au premier tirage. |
| `magic-item` | `table.magic-item` | Un objet magique structuré : son nom et son pouvoir, son apparence, une manie et ses charges. |
| `loot` | `table.loot`, `table.loot.unidentified` | Des pièces, des gemmes avec leur valeur, une œuvre d'art, et un objet non identifié : ce dont il a l'air, ce qu'il est vraiment, et un indice qui y mène. |
| `shop` | `table.shop`, `table.shop.merchant` | Une boutique : son genre et son enseigne, son marchand avec un trait et une humeur, et cinq articles de son stock avec leur prix en argent. |
| `hook` | `table.hook` | Une accroche de scénario : qui demande, quoi, où, pourquoi, et le revirement que le groupe ignore encore. |
| `rumour` | `table.rumour` | Une rumeur sur un lieu et sa part de vérité. Le lieu est une valeur d'état que fixe l'application ; `the village` au premier tirage. |
| `oracle` | `table.oracle`, `table.oracle.yes-no`, `table.oracle.inspiration` | Un oracle de jeu en solo : une réponse oui ou non pesée selon les chances de la question et un facteur de chaos, avec un événement aléatoire sur un double ; une réponse oui ou non simple ; une action et un thème pour l'inspiration. |
| `fortune` | `table.fortune`, `table.fortune.day`, `table.fortune.prophecy` | La bonne aventure : un tirage de trois cartes d'un jeu de vingt-deux, chacune à l'endroit ou renversée ; une carte du jour ; une prophétie faite de trois tables. |

## L'oracle du jeu en solo

`table.oracle` garde deux valeurs d'état que l'application fixe entre les questions : `odds`, l'une de `certain`,
`likely`, `even`, `unlikely` et `impossible`, et `chaos`, le facteur de chaos de 1 à 9. La chance d'un oui est celle des
chances (90, 70, 50, 30 ou 10 sur cent), déplacée de 5 par cran de chaos au-dessus ou au-dessous de 5, et gardée de 1 à
99 ; un jet d'un cinquième de la chance ou moins est un oui exceptionnel, un jet dans le dernier cinquième du reste un
non exceptionnel. Un double (11, 22, … 99) dont les dizaines ne dépassent pas le facteur de chaos amène un événement
aléatoire. Le résultat est structuré :

```php
$generator->draw('table.oracle', new Draw(state: ['odds' => 'likely', 'chaos' => '6']))->first();
// ['roll' => '34', 'answer' => 'Yes', 'event' => '']
```

L'arithmétique reste en nombres entiers (`//`), pour que le même germe donne partout la même réponse.

## Écrire une table

Les tables emploient les techniques de la bibliothèque, que les tables d'une application peuvent reprendre : une entrée
choisie par une variable au moyen d'une étiquette (`table.loot.unidentified` tire un nombre, puis l'apparence et
l'identité qui en portent l'étiquette, pour qu'elles correspondent), le genre d'une boutique tiré dans une variable et
passé aux tirages de son stock comme étiquette, une valeur d'état comme entrée que donne l'application, et des boucles
sur les entrées d'un tirage multiple, séparées au moyen de `loop.index0`.
