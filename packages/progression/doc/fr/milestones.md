<!-- phil-dev source-sha256=a902589a3768a7ac809f2da0fa29c5a2765b6f1009d692b01982742de364976b -->
# Jalons

Un `Milestone` est une étape d'une piste qui mérite une récompense : une quantité d'expérience
(`new Milestone('thousand', 1000)`) ou un niveau (`new Milestone('veteran', 10, level: true)`). Un jalon de niveau doit
se trouver sur la courbe de la piste.

Quand une activité fait dépasser le seuil d'un jalon à un sujet (au-dessus de l'expérience qu'il avait, au plus
l'expérience qu'il atteint), la projection garde un `ReachedMilestone` et l'annonce à
`ProgressionObserver::milestoneReached()`. Un jalon est atteint une fois par piste, par sujet et par saison : sur une
piste saisonnière, il peut être atteint de nouveau la saison suivante.

La bibliothèque ne donne elle-même aucune récompense : elle ne dépend d'aucun autre paquet de ludification.
L'application écoute et donne ce qu'elle a décidé : des points par `tilttek/points`, un objet, un titre, un badge
décerné à la main.

Un jalon atteint reste atteint : une annulation qui ramène le sujet sous le seuil ne le retire pas, et une
reconstruction ne l'oublie pas et ne l'annonce pas de nouveau, puisque sa récompense a été donnée. Effacer un membre
retire ses jalons. `$progression->milestones($subject)` les énumère dans l'ordre où ils ont été atteints.
