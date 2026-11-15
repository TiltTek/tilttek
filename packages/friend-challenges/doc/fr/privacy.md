<!-- phil-dev source-sha256=ed436c5dbb72eeb9a61ce271538345af7744c56b1d01ee25fd970376e63452ca -->
# Vie privée

## Effacer un membre

`erase()` retire un membre de chaque défi qui le nomme — comme créateur, invité, participant, auteur d'une preuve,
votant ou gagnant —, un lot borné à la fois :

```php
while ($challenges->erase($member) > 0) {
    // 100 challenges a call by default; erase($member, 500) for the largest batch
}
```

Dans chaque défi, deux mouvements se suivent :

1. Le membre part. Le défi ouvert ou gelé qu'il a créé est retiré, et son lot lui est rendu ; ses preuves en attente
   d'examen dans un défi ouvert ou gelé sont retirées, pour qu'il ne puisse pas le gagner. Ce mouvement est réglé dans
   le séquestre et annoncé comme tout autre : `ChallengeEnded` pour un défi retiré, `ProofDecided` pour une preuve
   retirée. Un défi réglé plus tôt dont le séquestre a alors échoué est réglé maintenant, avant que quiconque soit
   remplacé.
2. Un pseudonyme le remplace, en silence : une référence du genre `erased`, `Pseudonym::KIND`, dont l'identifiant est
   tiré au hasard pour chaque défi. Chaque endroit où le défi le nommait — créateur, invités, ceux qui ont décliné,
   participants, preuves et leurs votes, gagnant — nomme le pseudonyme, et le justificatif de ses preuves est lui aussi
   remplacé par lui. Deux défis d'un même membre effacé ne partagent pas de pseudonyme, donc leurs enregistrements ne
   sont pas liés.

Ce que d'autres membres ont fait demeure : leurs preuves, leurs votes et leurs victoires, y compris les votes qu'a
donnés le membre effacé, sous forme de décomptes. Un défi gagné garde son issue, et ce qui a été payé reste payé. Le
titre et les conditions d'un défi demeurent : un site qui veut les faire disparaître retire le défi avant l'effacement.

Un défi dont le créateur ou le gagnant est un pseudonyme a été réglé avant l'effacement ; `settle()` le laisse tel
quel, pour qu'aucun point ne soit payé deux fois ni à un pseudonyme.

## Références refusées

Seul `erase()` tire des références du genre `erased`. Toute référence que donne un appelant est refusée avec
`InvalidChallenge` quand son genre est `erased`, avant que quoi que ce soit soit lu, écrit, signalé ou déplacé dans le
séquestre :

| Méthode | Référence vérifiée |
|---|---|
| `create()` | le créateur, et chaque invité du `ChallengeDraft` |
| `join()`, `submitProof()` | le participant |
| `decline()` | l'invité |
| `approve()`, `reject()` | le juge : le créateur, un votant ou un modérateur |
| `cancel()` | le créateur |
| `report()` | l'auteur du signalement |
| `freeze()`, `unfreeze()`, `remove()`, `removeProof()` | le modérateur, avant que la `Moderation` du site soit interrogée |
| `erase()` | le membre à effacer |

```php
try {
    $challenges->join('c1', $member);
} catch (InvalidChallenge $refused) {
    // A participant is erased:…, a pseudonym the library draws for an erased member; it is never taken from a caller.
}
```

`ChallengeDraft` lui-même accepte des invités du genre `erased`, parce qu'il relit aussi des défis conservés dont les
invités ont été effacés depuis ; `create()` les refuse. Un pseudonyme lu dans un défi n'y agit donc jamais : il ne peut
ni se joindre, ni prouver, ni juger, ni annuler, ni signaler, ni modérer, ni créer de nouveau le même défi pour le
récupérer, ni être effacé de nouveau. Ne donnez pas aux membres de références du genre `erased`.

## Ordre de l'effacement

Effacez les défis du membre avant ses points : un lot rendu à son créateur atteint son compte, dont l'effacement de
`tilttek/points` s'occupe ensuite. Les signalements que le membre a envoyés vont à la modération du site, et les
amitiés viennent du graphe social du site : c'est le site qui les efface.

## Ce que fait le magasin

`ChallengeStore::involving($member, $limit)` liste les identifiants des défis qui nomment le membre, `$limit` au plus :
voir [Écrire un magasin](extension-points.md#écrire-un-magasin).

Un défi qu'un autre auteur ne cesse de modifier lance `ChallengeConflict` après trois essais ; ce qui a été effacé reste
effacé, et l'appel suivant reprend le reste.
