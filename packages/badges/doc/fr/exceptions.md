<!-- phil-dev source-sha256=2c3d24199abe54dbf212d5d06bf7744ea673a8ac2ba61e1e5a61194ae42c4f26 -->
# Exceptions

Chaque exception de la bibliothèque implémente `Tilttek\Badges\Exception\BadgesException` : attrapez-la pour les
attraper toutes.

| Exception | Étend | Levée quand |
|---|---|---|
| `InvalidBadge` | `InvalidArgumentException` | Un badge, un critère, un palier, un catalogue, une progression, une attribution, un profil ou un nom d'application n'est pas cohérent ; le message dit ce qui ne va pas |
| `UnknownBadge` | `OutOfBoundsException` | Un code que le catalogue ne contient pas : `No badge is named "gold".` |
| `NotEarned` | `DomainException` | Un badge que le membre n'a pas gagné est montré, mis en vitrine, porté, partagé ou délivré : `member:44 has not earned the badge "gold".` |
| `AccessDenied` | `RuntimeException` | Une autre application lit les badges d'un membre sans son consentement : `member:44 has not allowed "portfolio" to read their badges.` |
| `AwardConflict` | `RuntimeException` | D'autres attributions faites au même moment ont pris, à chaque tentative, le numéro d'un badge répétable attribué à la main : `The badge "games" could not be awarded by hand to member:44: other awards took its number 5 times in a row.` |
