<!-- phil-dev source-sha256=87a09187d8d4d002a3fd654d6b9c1fe60757ec2d6416df244df6155fbff74c8f -->
# Référence

Chaque type public du paquet, avec son rôle.

| Type | Rôle |
|---|---|
| `Entry` | Une date : identifiant, noms par locale, règle, source, communauté, date de vérification, étiquettes, jours, statut. |
| `Status` | `Verified` ou `ToVerify` : la règle a-t-elle été vérifiée contre sa source. |
| `EntryReader` | Lit des entrées à partir de tableaux : les lignes des données fournies et celles d'une application. |
| `Catalog` | Les entrées dans un fuseau horaire : les dates d'une année, d'une période ou d'un jour. |
| `Filter` | Les entrées que lit une question : étiquettes, communautés, vérifiées seulement. |
| `Occurrence` | Une date que le catalogue a calculée : son entrée et son étendue de jours. |
| `ShippedData` | Les lignes, les entrées et la version des données fournies. |
| `DataCheck` | Chaque problème d'un ensemble de lignes. |
| `Exception\GeekDatesException` | L'interface de chaque exception du paquet. |
| `Exception\InvalidEntry` | Une entrée qui ne peut pas être lue, ou qu'un catalogue contient déjà. |
| `Exception\InvalidQuery` | Une année hors plage, une entrée inconnue, un filtre sur rien. |

## Entrées

Une `Entry` vérifie ce qu'on lui donne et refuse le reste par une `InvalidEntry` qui le nomme :

| Champ | Bornes |
|---|---|
| `id` | Une lettre minuscule ou un chiffre, puis jusqu'à 63 autres ou `-` : `towel-day`. Stable : une application y rattache ses propres enregistrements. |
| `names` | Par locale, `en` et `fr` compris, au plus 32 locales comme `fr` ou `fr_CA` ; chaque nom de 1 à 200 caractères, non vide. |
| `rule` | Une `Phil\Almanac\Rule\Rule` ; `EntryReader` en lit le texte avec `RuleParser`. |
| `source` | Une adresse `https` sur un nom d'hôte, lue par `phil/url` et gardée sous sa forme canonique — hôte en minuscules, port par défaut retiré, `.` et `..` résolus —, d'au plus 2048 octets, sans espace ni identifiants : où la règle a été vérifiée. |
| `community` | Le pays ou la communauté qui garde le jour, sous forme de *slug* : `worldwide`, `tolkien-readers`. |
| `verified` | Le jour où la règle a été vérifiée contre sa source pour la dernière fois. |
| `tags` | Au plus 16, chacune une fois, chacune un *slug*. |
| `days` | De 1 à 366 : la Semaine mondiale de l'espace dure 7 jours. |
| `status` | `Status::Verified` par défaut ; `Status::ToVerify` pour une règle pas encore vérifiée. |

`name($locale)` donne le nom dans la locale la plus proche : `fr_CA`, puis `fr`, puis l'anglais. `definition()` est la
`Phil\Almanac\Definition` que l'almanach calcule : l'identifiant comme clé, le nom anglais comme titre.

## Les questions du catalogue

`new Catalog($entries, $zone)` refuse un identifiant donné deux fois ; `Catalog::shipped($zone, $own)` donne les
entrées fournies suivies de celles de l'application, et `with(...$entries)` un nouveau catalogue avec davantage
d'entrées. `entries()`, `has($id)` et `entry($id)` les lisent.

| Question | Donne |
|---|---|
| `year(2026)` | Les dates qui touchent l'année. |
| `between($from, $until)` | Les dates qui touchent une période, son début compris et sa fin exclue. |
| `on($day)` | Les dates qui couvrent un jour, lu dans le fuseau horaire du catalogue : l'objet du jour. |

Chacune accepte un `Filter` facultatif : `new Filter(tags: ['science'])` garde les entrées qui ont l'une des étiquettes,
`communities: ['worldwide']` celles d'une des communautés, `verifiedOnly: true` celles de `Status::Verified`. Un filtre
sur une liste vide d'étiquettes ou de communautés est une `InvalidQuery` : il ne lirait rien.

Les dates viennent par début, puis par nom anglais, sous forme d'`Occurrence` : `entry`, `span` (une
`Phil\Almanac\DateSpan`), `start()` et `end()` — les minuits du premier et du dernier jour dans le fuseau horaire du
catalogue — et `source()`. Comparez-les avec `<` et `>`, ou avec `overlaps()` de l'étendue ; jamais avec `==`. Une
question lit les années 1000 à 3000 : une période de `1000-01-01` jusqu'à `3001-01-01` au plus, sinon une
`InvalidQuery`.

## Données et vérifications

`ShippedData::rows()` donne les lignes de `data/dates.php`, `entries()` les entrées qu'on en lit, `version()` le contenu
de `data/VERSION` et `directory()` le dossier. `new DataCheck()->problems($rows)` liste chaque problème d'un ensemble de
lignes, comptées à partir de 1, et une liste vide quand toutes sont des entrées.
