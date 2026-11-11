<!-- phil-dev source-sha256=ad10f93e2ac4a7eae353a8b26b300c80405bf856fe95e114e9fffcfbfdfe2ad4 -->
# Référence

| Classe | Rôle |
|---|---|
| `TilttekBadgesBundle` | Le bundle et sa configuration `tilttek_badges` |
| `Infrastructure\DependencyInjection\BadgesServices` | Déclare les services à partir de la configuration |
| `Infrastructure\Doctrine\BadgesTables` | Les quatre tables |
| `Infrastructure\Doctrine\BadgesSchemaListener` | Les ajoute au schéma que génère l'ORM |
| `Infrastructure\Doctrine\DbalProgressStore`, `DbalAwardStore`, `DbalProfileStore`, `DbalGrantStore` | Les stockages sur Doctrine DBAL |
| `Infrastructure\Doctrine\Rows` | L'insertion idempotente selon la plateforme et la lecture des valeurs des lignes |
| `Infrastructure\Event\BadgeAwarded`, `BadgeRevoked` | Les événements |
| `Infrastructure\Event\DispatchingObserver` | Change en événements ce qu'annonce la projection |
| `Infrastructure\Twig\BadgesExtension` | Les fonctions Twig |
| `Infrastructure\Console\AwardCommand`, `RevokeCommand`, `ListCommand` | Les commandes |
| `Infrastructure\OpenBadges\BadgeCredentials` | Délivre le badge d'un membre comme une attestation |
| `Infrastructure\Privacy\BadgesPrivacy` | Le module de `phil/privacy-bundle` |
