<!-- phil-dev source-sha256=587e5b2c9f03723236f09e96288cf5272f761dafed3e77231c4e06823a727c93 -->
# Référence

Chaque type public du paquet, avec son rôle.

| Type | Rôle |
|---|---|
| `FanAwards` | Crée les éditions, reçoit les candidats et les bulletins, fait avancer et dépouille les phases, lit les résultats, efface les votants |
| `Edition` | Une édition : clé, nom, catégories, demi-finale ou non, phase et depuis quand |
| `Category` | Une catégorie et ses règles : candidatures, demi-finalistes, finalistes, méthode de la finale, « aucun prix » |
| `Nominee` | Un candidat : clé, titre, référence ; `NO_AWARD`, la clé réservée à « aucun prix » |
| `Phase` | `Draft`, `Nomination`, `Semifinal`, `Final`, `Counted`, `Announced`, et leur enchaînement |
| `PhaseCount` | Le décompte d'une phase : ses candidats, le résultat de `phil/poll`, les candidats qu'elle a fait passer |
| `Results` | Les gagnants d'une catégorie, « aucun prix », et le décompte de chaque phase |
| `Refusal` | Pourquoi un acte est refusé |
| `Contract\AwardStore` | Où l'on garde les éditions, les candidats, les bulletins et les décomptes |
| `Contract\NomineeEligibility` | Si un candidat peut se présenter dans une catégorie |
| `Contract\VoterEligibility` | Si un membre peut voter dans une édition |
| `Eligibility\AnyNominee` | Tous les candidats admissibles |
| `Eligibility\AnyVoter` | Tous les membres votants |
| `Store\InMemoryAwardStore` | Le magasin en mémoire |
| `Exception\FanAwardsException` | Toutes les exceptions de la bibliothèque |
| `Exception\AwardRefused` | Un acte refusé, avec son `Refusal` et la raison de l'admissibilité |
| `Exception\UnknownAward` | Aucune édition sous une clé, ou aucune catégorie sous une clé |
| `Exception\InvalidAward` | Une valeur hors bornes, ou un bulletin qui enfreint les règles de sa phase |
