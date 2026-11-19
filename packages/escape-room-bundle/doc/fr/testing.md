<!-- phil-dev source-sha256=8314c7e37eb297db158cfc7f88f74ba24bd5e666305885f1ed12435010e0d491 -->
# Tests

## Les tests du bundle

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

Les tests unitaires couvrent `GameDesk` — chaque coup, les écrans informés, les résultats, les expirations une fois
chacune, le classement et ses bornes —, les magasins en mémoire et sur SQLite par les mêmes scénarios, le verrou et
l'ordre des réservations, les réservations sur `phil/reservation` dans la base de données, la configuration et les
services qu'elle branche, Mercure et les abonnements, l'horaire, son *handler* et la commande, le module de vie privée,
les tables, l'écouteur de schéma et les fonctions Twig. Les tests fonctionnels démarrent une petite application —
FrameworkBundle, DoctrineBundle sur SQLite, TwigBundle et les composants Twig, un hub qui enregistre ce qu'on lui donne,
les recettes du kit PHIL UX copiées là où `ux:install` les met — et pilotent la console avec et sans JavaScript, l'écran
d'indices et sa célébration, le consentement et le classement ; ils sont ignorés quand le kit PHIL UX n'est ni à côté du
monodépôt ni installé.

## Tester une application

Gardez tout en mémoire, remis à zéro entre deux requêtes :

```yaml
when@test:
    tilttek_escape_room:
        store: memory
        mercure: false
```

et donnez aux services une horloge qui ne bouge pas — `clock` comme un `Symfony\Component\Clock\MockClock` —, de sorte
que `$clock->sleep(3_600)` mène une minuterie à sa fin avant `GameDesk::expireDue()`.
