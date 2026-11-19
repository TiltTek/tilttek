<!-- phil-dev source-sha256=a1bacdb96c160a1f2eb05268a1c604fd2c63416b0b119e665e1594f626330ae1 -->
# Moteurs et garde-fous

Tilttek classe chaque paquet selon les moteurs de motivation qu'il touche, et chaque moteur qui met les joueurs sous
pression porte un garde-fou. Cette bibliothèque relève de trois moteurs :

| Moteur | Ce que fait la bibliothèque | Garde-fou |
|---|---|---|
| Curiosité et autonomie | Les énigmes de `tilttek/puzzle-builder`, des indices demandés quand l'équipe le veut | Rien au-delà des règles communes |
| Rareté et urgence | La minuterie de la salle | La minuterie est la partie que l'équipe a réservée, connue avant son début ; l'animateur peut la mettre en pause ; un indice s'ajoute au temps final, jamais à la minuterie, si bien que demander ne rapproche jamais la fin |
| Comparaison sociale | Le classement d'une salle | Privé par défaut : une équipe n'est montrée qu'une fois qu'elle y consent, et peut se retirer à tout moment ; les indices sont montrés à côté du temps |

## Les règles communes qu'elle respecte

- **Un membre est une référence opaque.** Une équipe, un groupe et un animateur sont des `ResourceReference` donnés par
  l'application ; la bibliothèque ne lit aucun nom, courriel ni compte, et refuse une équipe ou un groupe qui ressemble
  à une adresse courriel.
- **Tout se rejoue.** Une partie est le relevé de ses coups — départ, pauses, résolutions, indices, fin —, et sa
  minuterie et son temps final en découlent.
- **Rien n'est public sans le choix des membres.** Seul le classement est public, et seulement par consentement ;
  effacer une équipe efface ses parties et ses résultats du stockage de l'application, la bibliothèque n'en gardant
  aucun.
- **Aucune pression pour payer.** Les indices coûtent des secondes, jamais de l'argent ; aucun temps ne se vend, et la
  bibliothèque n'a aucune notion de prix.
