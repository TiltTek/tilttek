<!-- phil-dev source-sha256=6a9eba4da3035c7d1e5acfab20a4b889deb934b145e8e8cb72f5ad1738214003 -->
# Dépôts

## Le port

`Contract\RuleStore` est l'endroit où les règles sont gardées et cherchées :

| Méthode | Donne |
|---|---|
| `get(RuleId $id): ?Rule` | La règle d'un identifiant, ou null |
| `search(RuleQuery $query): RuleMatches` | Une page des règles qui répondent à la requête, la meilleure d'abord, chacune avec son score, et leur total |
| `referencing(RuleId $id): array` | Les règles qui renvoient à une règle |

`RulesLookup` ne lit le dépôt que par ces trois méthodes.

## Le dépôt en mémoire

`Store\InMemoryRuleStore` contient les règles qu'on lui donne, 100 000 au plus, et les indexe une fois : les mots
normalisés de chaque titre et de chaque texte par locale, les règles qui contiennent chaque mot, les mots triés pour le
début d'un mot, et les règles qui renvoient à chaque règle. Il calcule le score comme le dit
[Rechercher](searching.md#score).

```php
$store = new InMemoryRuleStore(...$rules);
$larger = $store->with(...$moreRules);   // a new store; a rule of the same identifier is replaced
$store->all();                           // the rules, in their order
```

Le dépôt ne change plus une fois construit : un *worker* le partage entre les requêtes, et `with()` donne un nouveau
dépôt. Deux règles de même identifiant sont refusées avec une `InvalidRule`.

Une recherche ne lit que les règles qui contiennent chaque mot dans un titre ou un texte, trouvées par l'index, avant de
les noter dans la locale du lecteur. Le début d'un mot tient lieu de 64 mots du dépôt au plus.

## Un dépôt à vous

Un dépôt dans une base de données — sur Doctrine DBAL — ou dans un moteur de recherche est un adaptateur que
l'application écrit, ou qu'un futur bundle fournit : il implémente `Contract\RuleStore`, normalise les mots des règles
comme le fait la bibliothèque, et garde avec lui la provenance de chaque règle, pour que chaque résultat porte encore
son attribution. La requête qu'il reçoit est déjà vérifiée et normalisée : `RuleQuery::$words` et
`RuleQuery::phrase()` sont ce qu'il cherche.

Quand les règles d'un système changent — un paquet de données importé ou mis à jour —, l'application envoie
`Message\ReindexRules` avec la clé et la version du système, et le *handler* de son propre dépôt relit les règles et les
indexe de nouveau :

```php
$bus->dispatch(new ReindexRules('srd-5.2', '5.2.1'));
```

Le message ne porte que des identifiants : le *handler* lit lui-même les règles.
