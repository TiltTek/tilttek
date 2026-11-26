# Installation

```sh
composer require tilttek/tournament
```

The package needs PHP 8.5 or later, and nothing else. To order participants by their ratings, install
`tilttek/rating` beside it and use `Seeding\RatingSeeding`.

The library holds no state and no storage: an application gives `Tournaments` a `Contract\TournamentStore` of its
own, which keeps each tournament in its database, as [the extension points](extension-points.md) explain.
