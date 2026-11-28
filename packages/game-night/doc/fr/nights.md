<!-- phil-dev source-sha256=1080ac6a972accde0dbe6c6e585f89a444ad340c0ebd2f9c55673c496b788e6c -->
# Soirées et inscriptions

`GameNights` est le service des soirées. Il lit une soirée dans l'entrepôt, la modifie, la conserve à sa version
suivante, puis informe l'observateur de ce qui s'est passé. Chaque changement est vérifié par `GameNight` dans son
ensemble : une soirée qui enfreindrait l'une de ses règles n'est jamais conservée.

## La planification

`plan()` prend l'identifiant de la soirée, son début et sa fin, l'identifiant de son lieu, le membre qui l'organise, et
ses tables. Une soirée dure une journée au plus, et commence dans le futur. Elle est conservée comme brouillon : personne
ne s'inscrit encore.

Chaque identifiant — soirée, lieu, table, membre — est opaque : de 1 à 128 lettres, chiffres, `_`, `.`, `:` ou `-`, et
commence par une lettre. Il ne contient aucun `@`, pour qu'une adresse ne tienne jamais lieu de membre. Un jeu garde la
forme du catalogue, où `@` est permis.

## Les tables

Une `Table` a ses sièges, de 1 à 30, et, quand ils sont connus, le jeu prévu et le membre qui l'anime. Une soirée a au
plus 50 tables ; ses places sont les sièges de toutes ses tables.

`addTable()` ajoute une table, ou remplace la table du même identifiant ; elle conserve au moins les places qui y sont
occupées. Les sièges qu'elle ajoute vont aux membres en attente. `removeTable()` retire une table quand les autres
tables accueillent les places confirmées ; les membres qui y étaient assis ne sont assis nulle part.

## La publication

`publish()` ouvre une soirée brouillon aux inscriptions, quand elle a au moins un siège et n'a pas commencé.
L'observateur apprend `NightPublished`.

## Les inscriptions

`register()` inscrit un membre, seul ou avec un invité. Une inscription prend une place, deux avec un invité ; l'invité
n'a pas d'identifiant, et rien de lui n'est conservé.

L'inscription détient ses places quand elles sont libres et que personne n'attend ; sinon, elle attend sur la liste
d'attente. Un membre s'inscrit une seule fois. La liste d'attente contient 200 membres au plus : au-delà, une
inscription est refusée.

Quand le dernier siège libre est pris, l'observateur apprend `NightFull`, une seule fois : il l'apprend de nouveau
seulement après qu'un siège a été libéré puis pris de nouveau.

## La liste d'attente

La liste d'attente est servie dans l'ordre où les membres se sont inscrits. Quand des places sont libérées — un membre
se retire, une table est ajoutée ou s'agrandit — elles vont au premier membre en attente, si ses places y tiennent, puis
au suivant, et ainsi de suite. Un membre en attente dont les places n'y tiennent pas retient les autres : un membre
inscrit plus tard ne passe jamais devant eux. Chaque membre qui détient ses places depuis la liste d'attente en est
informé une seule fois, par `SeatOffered`.

## Les retraits

`withdraw()` retire l'inscription d'un membre, et son vote. Les places qu'il libère vont aux membres en attente.

## Les sièges

`seat()` assoit un membre confirmé, avec son invité, à une table qui a des sièges libres, ou nulle part avec `null`. Un
membre en attente est assis une fois qu'il détient ses places.

## La fermeture et l'annulation

Les inscriptions, les retraits, les sièges et le vote se ferment quand la soirée commence.

`cancel()` annule une soirée avant sa fin, pour de bon : rien ne la change ensuite. L'observateur apprend
`NightCancelled`, une seule fois, et une application en informe les membres inscrits.

## Aucun rappel

La bibliothèque annonce chaque fait une seule fois et ne tient aucun calendrier : elle n'envoie aucun rappel, et n'en
répète jamais un aux membres qui ne sont pas venus.
