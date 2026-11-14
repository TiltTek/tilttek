<!-- phil-dev source-sha256=10470c761de6d4b99698b047a5438c30e72ef5363f6f8030b4c7bddcd3f0ff9f -->
# Paquets

## Chances publiées

`Packs::odds(pack)` renvoie les `Odds` que publie un paquet : la chance de chaque rareté et de chaque objet, avant tout
tirage. Un site qui fait tirer ses membres au hasard dit à quelles chances.

## Ouvrir un paquet

Un paquet s'ouvre en deux temps, pour que ni le site ni le membre ne puisse choisir le tirage :

1. **Engager.** `commit(member, pack, key)` tire un germe secret de 16 à 64 octets du `SeedSource` injecté —
   `SecureSeeds` en production, un germe fixe dans les tests — et renvoie une `PendingOpening` : la clé, le membre, le
   paquet et le germe en hexadécimal. Le site la garde et montre au membre son `commitment()`, le SHA-256 du germe.
   Rien n'est encore remis. Une `PendingOpening` se reconstruit par son constructeur à partir de ce qui a été gardé.
2. **Ouvrir.** Le membre, après avoir vu l'engagement, donne un germe du client de 1 à 256 octets de texte UTF-8 : des
   mots qu'il tape, ou des octets que son propre appareil tire. `open(pending, clientSeed, moment)` tire les objets du
   paquet — chacun séparément : une rareté selon son poids, puis un objet de cette rareté — et remet un exemplaire de
   chacun, sous la clé suivie de `#` et de la place de l'objet.

L'ouverture réserve sa clé avant de remettre quoi que ce soit : elle est ajoutée sans exemplaires, puis les exemplaires
sont remis et enregistrés. De deux requêtes qui ouvrent la même clé au même moment, seule celle dont l'ajout a écrit
tire l'ouverture ; l'autre renvoie l'ouverture gagnante, toujours complète — ou est refusée, quand la gagnante venait
d'un autre germe du client. Quand la gagnante n'a pas encore remis ses exemplaires, ou s'est arrêtée avant, l'autre les
remet sous les mêmes clés, qui ne remettent chaque exemplaire qu'une fois, de sorte que l'ouverture qu'elle renvoie
nomme des exemplaires qui existent. La même ouverture en attente, ouverte de nouveau avec le même germe du client, renvoie
l'ouverture qu'elle a faite, et remet les exemplaires d'une ouverture dont la remise a été interrompue ; une clé qui a
ouvert un autre paquet, ou pour un autre membre, ou à partir d'un autre germe ou d'un autre germe du client, est
refusée.

Le tirage est celui que donnent les deux germes et la clé : `Xoshiro256StarStar` dont le germe est le HMAC-SHA256, sous
le germe du site, du germe du client, d'un saut de ligne et de la clé. Le germe du site est fixé avant que le germe du
client soit connu, et le membre choisit le sien sans connaître celui du site : le site ne peut pas garder seulement les
germes dont le tirage lui convient, et le membre ne peut pas essayer des germes jusqu'à ce que l'un donne un objet
légendaire. Un germe du client que le site choisit à la place du membre n'offre pas cette garantie.

## Vérifier un tirage

La `PackOpening` garde le germe en hexadécimal, son engagement et le germe du client. Le membre vérifie ensuite que le
germe correspond à l'engagement affiché et que `verify(opening)` rejoue les mêmes objets. La vérification tire avec le
paquet tel que le catalogue le contient maintenant : un paquet dont les ensembles ou les poids ont changé depuis ne
vérifie plus ses anciennes ouvertures.
