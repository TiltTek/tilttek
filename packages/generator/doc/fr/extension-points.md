<!-- phil-dev source-sha256=04b143fb172f53fc7020fca6e964a23c92c24d362327ea92a6376a7f1bf62ce0 -->
# Points d'extension

Le `Generator` prend ses collaborateurs dans son constructeur ; chacun a une valeur par défaut et peut être remplacé.

| Port ou collaborateur | Valeur par défaut | Usage |
|---|---|---|
| `Tilttek\Generator\Grammar\Grammar` | `French`, `English`, `Spanish`, `German`, `Italian`, `Portuguese` | Les règles d'une langue derrière les filtres de grammaire : implémentez-le pour ajouter une langue, et donnez-le dans un `Grammars` par langue ou par locale. Voyez [Grammaire](grammar.md#ajouter-une-langue). |
| `Tilttek\Generator\Grammar\Grammars` | les grammaires intégrées | Les grammaires par locale ou par langue : celles de l'application s'ajoutent aux grammaires intégrées et en remplacent une sous la même clé. |
| `Tilttek\Generator\Timer` | `SystemTimer` | L'horloge monotone de la limite de temps : une horloge qui avance par pas dans les tests. |
| `Random\Randomizer` | un moteur sûr | Le hasard d'un tirage sans germe : un moteur scénarisé ou doté d'un germe dans les tests. |
| `Tilttek\Dice\DiceRoller` | un `DiceRoller` avec les limites par défaut | Lance les `roll()` des gabarits, avec les dés personnalisés et les notations de l'application. |
| `Tilttek\Generator\Loading\Reader`, `FileLoader` | — | Transforment des documents et des fichiers en `Catalog` ; une application qui garde ses listes ailleurs construit elle-même le `Catalog`. |
| `TraceryImporter`, `PerchanceImporter` | — | Transforment les grammaires et les tables d'autres outils en documents ; un importateur d'un autre format écrit les mêmes documents et laisse `Reader` les vérifier. Voyez [Importations](imports.md). |
| Le répertoire du cache (`$cache`) | `false` | Où Twig écrit les gabarits compilés, chargés de leurs fichiers plutôt que par `eval()`. Voyez [Tirages](drawing.md#limites). |
