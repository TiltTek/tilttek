<!-- phil-dev source-sha256=b6377434da78ead39c48349451a66054281ad07dd18d608c555a527581bf3cee -->
# Parties

`NightPlays::record()` envoie un jeu joué à une table de la soirée au journal des parties de l'application, par
`Contract\PlayJournal`. Il ne conserve rien lui-même.

Un jeu est enregistré une fois la soirée commencée, sauf si elle est annulée. Il est joué à une table de la soirée, par
des membres confirmés, au moins un et chacun une seule fois, et par les invités que ces membres ont amenés ; le jeu est
dans le catalogue. Le `PlayedGame` que reçoit le journal des parties contient la soirée, la table, le jeu, les membres
par leurs identifiants, les invités sous forme de nombre, le moment de l'enregistrement et, quand elle est connue, la
durée en minutes.
