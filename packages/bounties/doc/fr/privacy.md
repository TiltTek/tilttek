<!-- phil-dev source-sha256=bb44272ca72e3464b2ee33d54a7b65a66d49e46259e094e7743a84e319c00ec8 -->
# Vie privée

## Effacer un membre

`erase()` retire un membre de chaque prime qui le nomme — comme commanditaire ou comme réclamant —, un lot borné à la
fois :

```php
while ($bounties->erase($member) > 0) {
    // 100 bounties a call by default; erase($member, 500) for the largest batch
}
```

Dans chaque prime, deux mouvements se suivent :

1. Le membre part. La prime ouverte qu'il commandite est réglée aussitôt : attribuée quand il a attribué quelque chose —
   les attributions payées, le reste rendu à lui —, expirée quand elle a des réclamations et aucune attribution,
   annulée autrement. Ses réclamations sur une prime ouverte sont retirées, avec les attributions données à ces
   réclamations, qui ne sont pas payées : ces points restent au commanditaire pour qu'il les attribue. Ce mouvement est
   réglé dans le séquestre et annoncé comme tout autre. Une prime réglée plus tôt dont le séquestre a alors échoué est
   réglée maintenant, avant que quiconque soit remplacé.
2. Un pseudonyme le remplace, en silence : une référence du genre `erased`, `Pseudonym::KIND`, dont l'identifiant est
   tiré au hasard pour chaque prime. Le commanditaire ou le réclamant de ses réclamations nomme le pseudonyme, et la
   preuve de ses réclamations est elle aussi remplacée par lui. Deux primes d'un même membre effacé ne partagent pas de
   pseudonyme, donc leurs enregistrements ne sont pas liés.

Ce que d'autres membres ont fait demeure : leurs réclamations et leurs attributions. Une prime réglée garde son issue,
et ce qui a été payé reste payé.

Une prime dont le commanditaire ou un gagnant est un pseudonyme a été réglée avant l'effacement ; `settle()` la laisse
telle quelle, pour qu'aucun point ne soit payé deux fois ni à un pseudonyme.

## Références refusées

Seul `erase()` tire des références du genre `erased`. Toute référence que donne un appelant — le commanditaire de
`offer()`, `award()`, `close()` et `cancel()`, le réclamant de `claim()`, le membre de `erase()` — et le sujet d'un
`BountyDraft` est refusée avec `InvalidBounty` quand son genre est `erased`, avant que quoi que ce soit soit lu, écrit
ou déplacé dans le séquestre :

```php
try {
    $bounties->claim('b1', 'answer-1', $member, 'answer:901');
} catch (InvalidBounty $refused) {
    // The sponsor of a bounty is erased:…, a pseudonym the library draws for an erased member; it is never taken from a
    // caller.
}
```

Un pseudonyme lu dans une prime n'y agit donc jamais : il ne peut ni réclamer, ni attribuer, ni fermer, ni annuler, ni
offrir de nouveau la même prime pour la récupérer, ni être effacé de nouveau. Ne donnez pas aux membres de références du
genre `erased`.

## Ordre de l'effacement

Effacez les primes du membre avant ses points : les points rendus au commanditaire atteignent son compte, dont
l'effacement de `tilttek/points` s'occupe ensuite.

## Ce que fait le magasin

`BountyStore::involving($member, $limit)` liste les identifiants des primes qui nomment le membre, `$limit` au plus :
voir [Écrire un magasin](extension-points.md#écrire-un-magasin).

Une prime qu'un autre auteur ne cesse de modifier lance `BountyConflict` après trois essais ; ce qui a été effacé reste
effacé, et l'appel suivant reprend le reste.
