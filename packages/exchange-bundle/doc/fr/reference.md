<!-- phil-dev source-sha256=55d299005f65e04c85c525127575b2966f3af073f53c53adfc200948451aa426 -->
# Référence

| Classe | Rôle |
|---|---|
| `TilttekExchangeBundle` | Le bundle, sa configuration et le module de vie privée |
| `Application\Contract\PointsHold` | Les points d'une récompense réservée retenus, gardés ou rendus |
| `Infrastructure\Points\SpendingPointsHold` | La retenue comme une dépense de `Points`, rendue par un remboursement |
| `Infrastructure\DependencyInjection\ExchangeServices` | Les services, vérifiés à la compilation du conteneur |
| `Infrastructure\Doctrine\ExchangeTables` | Les sept tables |
| `Infrastructure\Doctrine\ExchangeSchemaListener` | Les tables dans le schéma que génère l'ORM |
| `Infrastructure\Doctrine\Rows` | L'insertion idempotente par plateforme ; les colonnes relues |
| `Infrastructure\Doctrine\DbalCatalog` | Le catalogue dans sa table |
| `Infrastructure\Doctrine\DbalReservationStore` | Les réservations, sous la ligne de verrou de la récompense |
| `Infrastructure\Doctrine\DbalTransferLog` | Le journal des transferts |
| `Infrastructure\Doctrine\DbalOfferStore` | Les offres, leurs objets et leurs réservations d'objets |
| `Infrastructure\Doctrine\DbalTransaction` | Les étapes d'un échange dans une seule transaction |
| `Infrastructure\Event\DispatchingAnnouncer` | Les événements vers le répartiteur d'événements |
| `Infrastructure\Form\RedeemRewardType`, `TransferPointsType`, `OfferType` | Les formulaires |
| `Infrastructure\Console\ExpireCommand`, `TransfersCommand` | Les commandes |
| `Infrastructure\Privacy\ExchangePrivacy` | L'exportation et l'effacement pour `phil/privacy-bundle` |
