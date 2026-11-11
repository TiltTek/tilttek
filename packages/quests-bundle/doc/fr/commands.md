<!-- phil-dev source-sha256=ea5f1738714e72650cc418a9fa07e1d22d268aaf9cdf19e6943a99a2abd0042f -->
# Commandes

| Commande | Rôle |
|---|---|
| `tilttek:quests:list` | Liste les quêtes du catalogue : genre, admission, étapes (reliées par une flèche quand elles sont ordonnées), la quête que chacune suit et les récompenses |
| `tilttek:quests:show member:44` | Affiche l'état de chaque quête pour un membre, étape par étape, avec son équipe |
| `tilttek:quests:expire` | Fait expirer les offres de duel restées sans réponse et les duels dont le temps est écoulé sans gagnant, en répartissant `DuelExpired` pour chacun |

Lancez `tilttek:quests:expire` toutes les quelques minutes à partir du planificateur de l'application. Rien d'autre n'a
besoin d'un calendrier, car les dates d'une quête sont lues quand une activité survient.
