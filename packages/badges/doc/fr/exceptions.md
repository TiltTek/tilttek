<!-- phil-dev source-sha256=848a0280e4a6ff706a07c34c404b84e1e7e2cd1eada184a460a0f7b666f4a440 -->
# Exceptions

Chaque exception de la bibliothèque implémente `Tilttek\Badges\Exception\BadgesException` : attrapez-la pour les
attraper toutes.

| Exception | Étend | Levée quand |
|---|---|---|
| `InvalidBadge` | `InvalidArgumentException` | Un badge, un critère, un palier, un catalogue, une progression, une attribution, un profil ou un nom d'application n'est pas cohérent ; le message dit ce qui ne va pas |
| `UnknownBadge` | `OutOfBoundsException` | Un code que le catalogue ne contient pas : `No badge is named "gold".` |
| `NotEarned` | `DomainException` | Un badge que le membre n'a pas gagné est montré, mis en vitrine, porté, partagé ou délivré : `member:44 has not earned the badge "gold".` |
| `AccessDenied` | `RuntimeException` | Une autre application lit les badges d'un membre sans son consentement : `member:44 has not allowed "portfolio" to read their badges.` |
