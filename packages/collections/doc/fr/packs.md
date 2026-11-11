<!-- phil-dev source-sha256=9f63dc5a01d3a999ff4183bc6c8c0eb6daa89028facb083409bb3f8067efd78b -->
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

La même ouverture en attente, ouverte de nouveau avec le même germe du client, renvoie l'ouverture qu'elle a faite ;
une clé qui a ouvert un autre paquet, ou pour un autre membre, ou à partir d'un autre germe ou d'un autre germe du
client, est refusée.

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
