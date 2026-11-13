<!-- phil-dev source-sha256=4e7849537d158654193fd36042196ed8f9bfe627cded4903028a8de307c0a9c4 -->
# Moteurs et garde-fous

Tilttek classe chaque paquet selon les moteurs de motivation qu'il touche, et chaque moteur qui met les joueurs sous
pression porte un garde-fou. Cette bibliothèque relève d'un seul moteur :

| Moteur | Ce que fait la bibliothèque | Garde-fou |
|---|---|---|
| Curiosité et autonomie | Des énigmes à résoudre dans un ordre que les joueurs choisissent parmi celles qui sont ouvertes, des indices demandés quand les joueurs le veulent | Rien au-delà des règles communes |

La bibliothèque n'ajoute aucune pression de son cru : ni minuterie, ni score, ni classement, ni série. Un jeu construit
dessus qui en ajoute une — la minuterie d'un escape game, un classement — porte lui-même le garde-fou de ce moteur.

## Les règles communes qu'elle respecte

- **Un membre est une référence opaque.** Une équipe est une `ResourceReference` donnée par l'application ; la
  bibliothèque ne lit aucun nom, courriel ni compte, et refuse une référence qui ressemble à une adresse courriel.
- **Tout se rejoue.** Un avancement est le relevé des coups — résolutions avec leur heure, indices avec leur palier et
  leur heure, propositions comptées —, si bien que l'état d'une partie découle de ce qui a été joué.
- **Rien n'est public sans le choix des membres.** La bibliothèque ne publie rien ; sa vue est pour l'écran de l'équipe
  elle-même.
- **Aucune pression pour payer.** Les indices se révèlent palier après palier, et leur pénalité est un nombre de
  secondes dont le jeu décide quoi faire ; la bibliothèque n'a aucune notion de prix.
