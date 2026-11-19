<!-- phil-dev source-sha256=a3b8c9226b115a1f75750642b991387de7dfaca7ba0a636da1195700d7922a6d -->
# Tirer les commandes

## Les tireurs des options

Un catalogue du noyau du commerce déclare les options de chaque produit ; une option tirée après le paiement nomme son
tireur. Le bundle tire deux genres d'options :

| Tireur | Sur | Tire |
|---|---|---|
| `loot-box:<key>` | une option de tirage : `OptionDefinition::draw('loot', 'Loot', Drawers::box('starter'))` | la boîte de cette clé dans le catalogue ; le produit déclare ses tables par elle |
| `loot-text` | une option de texte tirée parmi ses entrées : `OptionDefinition::text(…, drawer: Drawers::TEXT)` | une des entrées de l'option, avec un dé d'autant de faces |

Une boîte que le catalogue ne contient pas n'est pas prise en charge : le noyau du commerce refuse alors les tirages de
la commande avec son `DrawRefused` plutôt que de ne rien tirer.

## Engagé au moment de la commande

Quand le workflow de commande du noyau du commerce prend sa transition `place`, `CommitOnPlace` écoute
`workflow.phil_commerce_order.completed.place` et appelle `LootCommitments::commit()` : les engagements existent avant
qu'on demande à l'acheteur de payer, sans que l'application ait rien à appeler.

```php
$lifecycle->place($order, $clock->now()); // the draws of the order are committed here
```

- Un tirage par unité de chaque option qui reste à tirer ici : un germe de 32 octets tiré par `SecureSeeds` de
  `Random\Engine\Secure`, le générateur cryptographique du système d'exploitation, gardé secret, et son engagement, le
  SHA-256 du germe. Son identifiant est dérivé de la commande, de la ligne, de l'option et de l'unité.
- Un texte garde les entrées de son option telles qu'elles sont à ce moment, pour qu'une liste modifiée plus tard ne
  change pas ce qui a été engagé. Une option de texte dont un dé ne peut pas tirer les entrées (moins de deux) est
  refusée avec `LootDrawRefused`, et la transition avec elle.
- L'appeler de nouveau n'engage rien de plus : chaque unité garde son premier engagement, et les tirages sont
  retournés de nouveau.
- Avec le stockage Doctrine, chaque engagement est écrit dès qu'il est enregistré, avec tout ce que contient aussi le
  gestionnaire d'entités.

Avec `commit_on_place: false`, l'écouteur n'est pas enregistré, et l'application appelle elle-même
`LootCommitments::commit($order)`, une fois la commande passée et avant que l'acheteur ne paie :

```php
foreach ($commitments->commit($order) as $draw) {
    $draw->commitment();   // shown to the buyer, on the order page and in the confirmation
}
```

## Le germe du serveur seul

Un tirage se lance à partir du germe du serveur seul, et rien n'est demandé au navigateur de l'acheteur. Les lancers
vérifiables de `tilttek/dice` prennent un germe client ; le bundle leur donne l'identifiant du tirage, qui est public et
fixé avant que le germe ne soit tiré, pour que quiconque détient la preuve relance les dés avec les mêmes entrées.

Le germe du serveur, engagé avant que la commande ne soit payée, suffit : l'engagement est montré avant le paiement,
donc le germe qu'il couvre, et avec lui chaque face que donneront les dés, est fixé avant que la boutique ne soit payée,
et le changer ensuite brise la preuve. Demander à l'acheteur un germe bien à lui ajouterait une étape sur sa page, un
script qui doit s'exécuter dans son navigateur et une valeur à garder pour chaque unité ; le bundle se passe de cette
dépendance au navigateur.

Ce que la preuve montre, c'est que le tirage a été fixé avant le paiement et non changé après. Elle ne montre pas que la
boutique n'aurait pas pu tirer plusieurs germes avant de s'engager sur l'un d'eux : le bundle en tire exactement un par
unité, du générateur cryptographique, quand la commande est passée.

## Tiré une fois la commande payée

Une fois les paiements de la commande `Paid`, l'application appelle `OptionDraws::draw()` du noyau du commerce, qui
interroge `LootOptionDrawer` pour chaque unité :

- l'unité est révélée à partir de son germe engagé par les lancers vérifiables de `tilttek/dice`, une seule fois ;
  redemandée, elle donne le même tirage, sans lancer ;
- la ligne de commande garde, pour une boîte, son `DrawText` (`3-6: Gold ring, Bare head`), et pour un texte, le texte
  lui-même ;
- le tirage garde ses faces et sa preuve, qui publie le germe une seule fois, pour que n'importe qui puisse relancer le
  tirage, comme l'expliquent [les résultats](results.md).

Une unité jamais engagée est refusée avec `LootDrawRefused`, pour qu'aucun tirage ne se fasse sans un engagement montré
d'abord. Avec `require_commitment: false`, elle est engagée et révélée d'un coup : toujours vérifiable, mais son
engagement n'a jamais été montré avant le paiement.

Personne ne voit un tirage avant le paiement : le germe reste dans le stockage jusqu'à ce que la commande soit payée, et
les dés ne sont lancés qu'alors.
