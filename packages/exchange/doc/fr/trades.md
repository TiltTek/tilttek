<!-- phil-dev source-sha256=c25927b7f872df3e883823ee127c5cf4f03d1df7101293720da94cae3ba06c2a -->
# Échanges

Quand l'application le permet, les membres échangent des objets de leurs collections (des cartes virtuelles, les dés
d'un jeu) et peuvent ajouter des points d'un côté ou de l'autre.

## L'inventaire

Les objets vivent dans les collections de l'application. `Trades` les atteint par un `Inventory` que l'application
implémente : à qui appartient un objet, s'il peut changer de mains (un objet gagné par une quête peut rester lié à son
membre), et les déplacements d'un échange accepté, tous ou aucun. `InMemoryInventory` garde des objets en mémoire, pour
les tests et pour essayer les échanges.

## Une offre

`Trades::offer($key, $proposer, $recipient, $offered, $requested, $offeredPoints, $requestedPoints, $currency)` fait une
offre : les objets de l'auteur contre ceux du destinataire, au moins un objet en tout, au plus 50 de chaque côté, aucun
objet deux fois. Les gardes sont vérifiées ([Gardes](guards.md)). Chaque objet doit appartenir à celui qui le donne et
être échangeable, et chaque objet offert doit être libre. Dès l'offre, le stockage retient les objets offerts : aucune
autre offre ne peut les offrir avant que celle-ci se ferme. Les objets demandés ne sont pas retenus ; plusieurs offres
peuvent demander le même objet.

Une offre attend sa réponse pendant sa durée de vie, sept jours à moins que `Trades` ne reçoive une autre durée
ISO 8601.

## Les réponses

- **Accepter**, par le destinataire : la propriété, le caractère échangeable et les retenues sont vérifiés de nouveau,
  ainsi que les deux soldes selon les points qu'ils ajoutent. Ensuite, dans une seule transaction, l'offre se ferme,
  les points se déplacent par des transferts du grand livre sous `exchange:offer:<key>:offered` et `:requested`, et
  l'inventaire déplace les objets. Quand une étape échoue, rien ne bouge.
- **Refuser**, par le destinataire, et **retirer**, par l'auteur : l'offre se ferme et ses objets redeviennent libres.
- **Contrer**, par le destinataire : une offre à lui, adressée à l'auteur, qui nomme l'offre à laquelle elle répond.
  Dans une seule transaction, la première offre se ferme comme contrée et la contre-offre s'ouvre. Elle est vérifiée
  comme une offre avant que la première se ferme.

Un membre qui n'est pas le bon côté est refusé avec `Refusal::NotParty`. Une réponse à une offre qui n'est plus ouverte
est refusée avec `ExchangeClosed`, et une réponse après la durée de vie fait d'abord expirer l'offre. La même réponse
donnée deux fois retourne l'offre telle quelle ; la même clé pour une autre offre est un `ExchangeConflict`.

```php
$trades->offer('trade:812', $ada, $grace, offered: [$redDragon], requested: [$greenElf], offeredPoints: 20);
$trades->counter('trade:812', $grace, 'trade:813', offered: [$greenElf], requested: [$redDragon, $blueKnight]);
$trades->accept('trade:813', $ada);
```

## L'expiration et le journal

`expireDue()` fait expirer les offres qui ont dépassé leur durée de vie et libère leurs objets : c'est la tâche
qu'exécute un planificateur. `ofMember($member)` donne les offres qu'un membre a faites ou reçues, dans l'ordre où
elles ont été faites : le journal de ses échanges. `made($member)` compte les offres qu'un membre a faites sur la
période de l'allocation.

## Une seule transaction

Les étapes d'une acceptation et d'une contre-offre passent par une `Transaction`. `ImmediateTransaction` les exécute à
mesure : avec les stockages en mémoire, `Trades` vérifie ce qu'il peut avant sa première écriture. Un stockage sur une
base de données les exécute dans une de ses transactions, comme le fait le `DbalTransaction` du bundle ; le grand livre
de points et l'inventaire y prennent part quand ils sont gardés sur la même connexion.
