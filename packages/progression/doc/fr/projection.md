<!-- phil-dev source-sha256=4e87c9ead6fe8894223069980cf9bd9c0b6a1b6242b0be56dc2873085d056eb8 -->
# La projection

`ProgressionProjection` est une projection de `tilttek/activity` : le journal lui remet chaque entrée que lit son
filtre (les verbes des règles de chaque piste et de chaque série), une fois en fonctionnement normal.

## Une activité enregistrée

Pour chaque piste dont les règles correspondent, par nom, et dont l'activité a le sujet :

1. la saison est celle où l'activité est tombée, vide en dehors de toute saison ;
2. l'expérience est celle de la règle, limitée par son plafond dans la période de l'activité ;
3. le gain est écrit une fois par piste, par règle et par activité : une entrée remise deux fois n'apporte rien de
   plus ;
4. un niveau franchi est annoncé à `ProgressionObserver::leveledUp()` avec un `LevelChange`, du niveau d'avant au
   niveau d'après, même plusieurs niveaux d'un coup ;
5. chaque jalon dépassé est atteint et annoncé, une fois.

Pour chaque série dont la règle correspond, l'unité du membre (dans son fuseau horaire) devient active, et l'état de la
série est mis à jour jusqu'au moment où le journal a écrit l'entrée : une série trouvée terminée est annoncée.

## Des annonces données au moins une fois

Un gain est écrit avec son annonce due, et chaque jalon qu'il dépasse est atteint avec son annonce due, avant que
l'observateur soit averti ; chacun est noté remis une fois l'observateur averti. Quand l'observateur lève une
exception, elle atteint le journal et les annonces restent dues. Elles sont données de nouveau quand l'entrée est remise
de nouveau — la requête répétée sous la même clé d'idempotence, ou le rattrapage suivant —, avec la prochaine activité
du sujet sur la piste, ou par `$projection->deliver($subject)`, qui renvoie combien de gains et de jalons il a réglés.
Les niveaux qu'ont apportés les gains encore dus sont annoncés d'un coup, du niveau d'avant le premier au niveau d'après
le dernier ; l'annonce d'une piste que la configuration ne contient plus est abandonnée. Une série brisée est annoncée
avant que son état soit enregistré : un observateur qui a échoué est averti de nouveau par le geste ou le balayage
suivant.

L'observateur peut donc être averti deux fois du même changement, et il est idempotent : il reconnaît un niveau à sa
piste, son sujet, sa saison et son numéro, un jalon à sa piste, son code, son sujet et sa saison, une série brisée à sa
série, son membre et sa dernière unité active. Un niveau perdu est annoncé une fois, quand l'annulation est appliquée.

## Une annulation

Chaque gain de l'activité annulée est repris ; un sujet dont le niveau baisse est annoncé par `leveledDown()`. Un jalon
atteint reste atteint. Les gestes de série de l'activité sont retirés et les états recomptés sans annonce.

## Reconstructions

Quand une règle, une courbe ou une saison change, `Projector::rebuild($projection, $since)` réinitialise la projection
(les gains et les gestes de série à partir de `since` sont oubliés, tous quand `since` est null) et rejoue le journal
de cette période selon la nouvelle configuration. Les jalons sont gardés. Pendant qu'elle rejoue les entrées écrites
avant la réinitialisation, la projection n'annonce que les niveaux au-dessus de ceux que les sujets avaient avant, et
n'annonce ni niveau perdu ni série brisée : les membres ont été avertis à ce moment-là. Une entrée écrite à la
réinitialisation ou après est annoncée comme d'habitude.

Les annonces de niveau sont propres à la projection : un niveau atteint puis perdu de nouveau dans le même rejeu est
annoncé une fois, et l'annulation qui le reprend, puisqu'elle fait partie du rejeu, ne l'est pas.
