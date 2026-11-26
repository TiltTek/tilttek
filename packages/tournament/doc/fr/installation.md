<!-- phil-dev source-sha256=16da05b80bf71beb41209233ad39be20c5d156a009907e978ff9f7ee7fe1f0b4 -->
# Installation

```sh
composer require tilttek/tournament
```

Le paquet exige PHP 8.5 ou une version plus récente, et rien d'autre. Pour ordonner les participants selon leurs
cotes, installez `tilttek/rating` à côté et utilisez `Seeding\RatingSeeding`.

La bibliothèque ne garde ni état ni stockage : une application donne à `Tournaments` un `Contract\TournamentStore` bien
à elle, qui garde chaque tournoi dans sa base de données, comme l'expliquent [les points d'extension](extension-points.md).
