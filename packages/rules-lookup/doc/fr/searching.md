<!-- phil-dev source-sha256=a1982d1e7bdf3d7b41f3218e24e9ab5a43f94ded6775b1f794d860723b003732 -->
# Rechercher

## Une requête

```php
$query = new RuleQuery(
    'épée longue',          // the words looked for; empty to list a category
    'fr_CA',                // the reader's locale
    system: 'srd-5.2',
    version: '5.2.1',
    category: 'weapon',
    limit: 20,
    offset: 0,
);

$results = $lookup->search($query);
```

| Argument | Bornes |
|---|---|
| `text` | UTF-8, 200 caractères et 16 mots au plus ; vide seulement avec une catégorie |
| `locale` | `fr`, `fr_CA`, `fr-ca` ou `es-419`, gardée normalisée |
| `system`, `category` | Des clés, ou null pour toutes |
| `version` | Une version, ou null pour toutes |
| `limit` | De 1 à 100 résultats par page |
| `offset` | De 0 à 10 000 |

Une requête qui sort de ses bornes est refusée avec une `InvalidQuery` à sa construction, avant qu'aucun dépôt la lise.

## Normalisation

Les mots de la requête et ceux des règles sont normalisés de la même façon : décomposés par NFKD, privés de leurs marques
sans chasse (`\p{Mn}`), mis en minuscules et recomposés par NFC — `Épée`, `EPEE`, `épée` et un `e` suivi d'un accent
aigu combinant sont un seul mot ; `ﬁ` se lit `fi`, `Ａ` se lit `a`. La normalisation est le `Normalizer` d'`intl` quand
l'extension est chargée, et `symfony/polyfill-intl-normalizer` sinon, ce qui donne les mêmes mots. Un texte en ASCII
pur est seulement mis en minuscules, et un texte qui n'est pas de l'UTF-8 valide n'a aucun mot.
 Un mot est une suite de lettres et de chiffres dans n'importe quelle écriture : `1d20` et
`Ελληνικά` sont des mots, la ponctuation les sépare.

## Ce que lit une recherche

Une règle répond à une requête quand chaque mot se trouve dans le titre ou le texte que voit un lecteur de la locale de
la requête : le texte de la locale, de sa langue, en anglais, puis le premier texte que la règle contient. Un lecteur en
`fr_CA` trouve `Aveuglé` par `aveugle` ; un lecteur en anglais ne le trouve pas, puisque le titre anglais est
`Blinded`.

Le dernier mot tapé tient aussi lieu des mots qu'il commence, à partir de deux caractères : `blin` trouve `Blinded`, à
mesure que le lecteur tape. Les autres mots sont des mots entiers.

## Score

Dans le dépôt en mémoire, chaque mot marque :

| Où se trouve le mot | Points |
|---|---|
| Un mot du titre | 10 |
| Le début d'un mot du titre (le dernier mot seulement) | 6 |
| Un mot du texte | 3 |
| Le début d'un mot du texte (le dernier mot seulement) | 1 |

Un titre égal aux mots cherchés ajoute 100, un titre qui commence par eux 20. Les résultats viennent du meilleur au
moins bon ; les égalités vont au titre dans l'ordre alphabétique de ses mots normalisés, puis à l'identifiant. Une
requête avec une catégorie et sans mots liste la catégorie par titre, chacun avec un score de 0.

## Résultats

`search()` donne des `SearchResults`, une page de `RuleHit` :

```php
$results->total;              // how many rules answer, on every page
$results->hasMore();          // whether a page follows
$hit = $results->hits[0];
$hit->title;                  // in the reader's locale
$hit->text;
$hit->score;
$hit->attribution;            // the notice of the rule's source
```

## Attribution

Chaque résultat porte la mention de sa source — son attribution, ou son titre, puis sa licence et la page du texte de la
licence — même quand la licence n'en demande aucune, pour qu'une page ne montre jamais une règle sans dire d'où elle
vient. Une page de résultats donne les mentions de chaque source qu'elle montre, chacune une seule fois :

```php
$results->sources();           // the provenances
$results->attributionText();   // one line per source
$results->attributionHtml();   // <ul class="attribution">, every text escaped, rel="license" on the licence
```

`attributionHtml()` est une chaîne vide pour une page sans résultat.
