<!-- phil-dev source-sha256=40361da9796b6ccc77a76569a84fa67807e16537a04ac48a28e650dabd97aa6e -->
# Règles, versions et acceptation

## Règles

Une `Rule` est une règle maison : un identifiant, un titre, un texte et une catégorie facultative.

- L'**identifiant** reste le même d'une version à l'autre : c'est ainsi que la comparaison suit une règle dont le titre
  ou le texte a changé. Un nouvel identifiant est une nouvelle règle.
- Le **titre** tient sur une ligne de 1 à 200 caractères.
- Le **texte** est du texte brut de 1 à 10 000 caractères sur 100 lignes au plus. Ses sauts de ligne deviennent des
  sauts de ligne simples (LF), chaque ligne perd ses espaces de fin, et les lignes vides avant et après le texte sont
  retirées ; l'indentation des lignes est conservée. L'application l'affiche à sa guise — en Markdown, en paragraphes —
  et l'échappe quand elle le fait.
- La **catégorie** est un identifiant que l'application nomme : `safety`, `table-talk`, `dice`.

Les titres, les textes et les notes sont de l'UTF-8 valide, sans caractère de contrôle, sauf la tabulation et le saut
de ligne, et sans les caractères qui réordonnent le texte affiché (encastrements, remplacements et isolats
bidirectionnels). Les longueurs comptent des points de code.

Chaque identifiant — table, joueur, règle, catégorie, variante, système, auteur — compte de 1 à 128 lettres, chiffres,
`_`, `.`, `:` ou `-`, et commence par une lettre. Il ne contient aucun `@` : un joueur est un identifiant de
l'application, jamais une adresse.

## Versions

`TableRules::publish()` publie la prochaine `RulebookVersion` d'une table, qui fait foi dès lors :

| Valeur | Ce que c'est |
|---|---|
| `number` | 1 pour la première version, puis un de plus pour chacune, jusqu'à 10 000. |
| `rules` | Jusqu'à 200 règles dans l'ordre où la table les lit, chaque identifiant une seule fois, leurs textes totalisant 100 000 caractères au plus. |
| `variants` | Jusqu'à 100 identifiants des variantes de règles du système de jeu auquel la table joue, chacun une seule fois, gardés triés. |
| `system` | L'identifiant du système de jeu auquel les variantes appartiennent, ou null. |
| `note` | Ce qui a changé, pour les joueurs : jusqu'à 2 000 caractères sur 50 lignes ; une note vide n'est aucune note. |
| `publishedBy`, `publishedAt` | Qui l'a publiée, et quand, d'après l'horloge. |
| `requiresAcceptance` | Si chaque joueur répond de nouveau à cette version. |

Une version contient au moins une règle ou une variante. Une version dont les règles, dans le même ordre, les variantes
et le système sont ceux qui font foi est refusée avec `RulesRefused` : la note, l'auteur et le moment ne font pas une
nouvelle version. Une version publiée n'est jamais modifiée ; changer les règles, c'est publier la suivante.

## Variantes

Une variante est une option du système de jeu que la table choisit : une règle de coup critique, le flanquement, un
repos éprouvant. La bibliothèque garde leurs identifiants et les compare d'une version à l'autre ; ce que fait une
variante relève de l'application, et les autres parties de l'application lisent les variantes de la version qui fait
foi.

Sans catalogue, tout identifiant est une variante. Avec un `Contract\VariantCatalogue`, `publish()` vérifie chaque
variante contre le système de la version, et refuse avec `InvalidRules` celle que le système n'offre pas.

`Catalogue\SystemVariantCatalogue` est le catalogue des variantes de règles de `tilttek/rpg-systems` : une variante est
une que son système contient, par sa clé — `encumbrance` pour la SRD 5.1, `free-archetype` pour Pathfinder deuxième
édition, comme leurs profils d'importation les déclarent. Une version qui ne nomme aucun système, ou un système que
l'application n'offre pas, ne choisit aucune variante.

```php
use Tilttek\TableRules\Catalogue\SystemVariantCatalogue;

$tableRules = new TableRules($store, $clock, variants: new SystemVariantCatalogue($systems));
$tableRules->publish('table-7', 'gm-1', $rules, variants: ['encumbrance'], system: 'srd-5.1');
```

## Acceptation

Un joueur répond à la version qui fait foi avec `accept()` ou `decline()`, en donnant le numéro de la version qu'il a
lue. Si une autre version a été publiée depuis, la réponse est refusée avec `RulesRefused` : le joueur lit d'abord la
nouvelle. Être à la table n'accepte rien : un joueur qui n'a jamais répondu est en attente.

Le `Rulebook` garde la dernière décision de chaque joueur — la version, acceptée ou refusée, et quand — pour 500 joueurs
au plus. Répondre de nouveau ce que le joueur a déjà répondu ne change rien et n'envoie aucun événement ; un joueur qui
a refusé peut accepter plus tard, et inversement. `forget()` retire la décision d'un joueur qui a quitté la table ou
qui a demandé à être oublié.

## Nouvelle acceptation

Une version publiée avec `requiresAcceptance` — la valeur par défaut — demande de nouveau à chaque joueur. Une version
publiée sans elle — une coquille corrigée, une phrase précisée — conserve les décisions : l'acceptation d'une version
postérieure à la dernière qui exigeait une acceptation tient toujours. La première version exige toujours une
acceptation.

Le livre de règles garde le numéro de la dernière version qui exigeait une acceptation comme sa `baseline` (base de
référence), et l'`AcceptanceState` d'un joueur en découle :

| État | Quand |
|---|---|
| `Pending` | Aucune règle n'est publiée, le joueur n'a jamais répondu, ou le joueur a refusé une version antérieure à la base de référence. |
| `Accepted` | Le joueur a accepté la base de référence ou une version postérieure. |
| `Declined` | Le joueur a refusé la base de référence ou une version postérieure. |
| `Outdated` | Le joueur a accepté une version antérieure à la base de référence : les règles ont changé depuis. |

Un joueur en attente ou périmé a les règles qui font foi à qui répondre : `PlayerStatus::mustAnswer()`.

## Ce qui a changé

`Diff\RulebookDiffer` compare deux versions, dans un sens ou dans l'autre, et donne un `Diff\RulebookDiff` :

- les règles seulement dans la version postérieure (`added`), et seulement dans l'antérieure (`removed`) ;
- les règles des deux dont le titre, le texte ou la catégorie a changé (`changed`), chacune un `Diff\RuleChange` avec la
  règle avant et après, et les lignes de son texte, gardées, retirées ou ajoutées, les lignes retirées avant les
  ajoutées là où un passage a changé ;
- les règles des deux lues dans un autre ordre (`moved`) : le moins de règles qui, déplacées, donnent le nouvel ordre ;
- les variantes choisies et abandonnées, et le système avant et après.

`TableRules::diff()` compare deux versions d'une table par leurs numéros. Les règles sont appariées par identifiant, et
les lignes et l'ordre par la plus longue sous-suite commune ; le travail est borné par les bornes d'une version.

## Les vues

`TableRules::status()` donne un `View\PlayerStatus` : l'état du joueur, la version qui fait foi, la version à laquelle
le joueur a répondu en dernier, la décision et son moment, et ce qui a changé de la version répondue à celle qui fait
foi — null quand le joueur n'en a répondu aucune, ou a répondu celle qui fait foi.

`TableRules::roster()` donne un `View\RosterView` pour 500 joueurs au plus que l'application nomme, chacun une seule
fois — le livre de règles ne connaît que ceux qui ont répondu : l'état de chaque joueur dans l'ordre donné, combien sont
dans chaque état, et si tous les joueurs ont accepté les règles qui font foi. Les joueurs qui ont répondu à la même
version partagent une seule comparaison.

Les deux vues, les versions et la comparaison donnent leurs données simples avec `toArray()`, les moments au format
ATOM, à envoyer à la table en JSON.
