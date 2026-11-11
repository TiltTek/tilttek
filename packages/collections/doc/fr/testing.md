<!-- phil-dev source-sha256=70ad918fdc302240e99c63cdef10755264ed6c14cc105d37361d514715e8d63f -->
# Tests

## Les tests de la bibliothèque

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

La suite couvre les définitions et leurs refus, le lecteur et chaque définition erronée, les chances et la
distribution des tirages, `Collections` — clés, conflits, exemplaires liés, ensembles remis en entier, ensembles de
base, déplacements, concurrence, exemplaires repris, l'album et les ensembles complétés —, les paquets avec des germes
fixes et leur vérification, le calendrier, la réclamation et la projection avec le journal de `tilttek/activity`, les
annulations et les reconstructions, les dés, l'effacement, et les cinq stockages en mémoire par les contrats que
respecte tout stockage. Dans le monodépôt, Infection maintient la bibliothèque à 100 % des mutants couverts.

## Tester du code qui lit les collections

Construisez les stockages en mémoire, une horloge que vous contrôlez et un `SeedSource` qui renvoie des germes fixes ;
remettez des exemplaires, ouvrez des paquets, enregistrez des activités, puis lisez :

```php
$collections->grant($member, 'deckhand', Source::Granted, 'k1');

self::assertSame(['deckhand'], array_map(static fn (Item $item): string => $item->code, $collections->album($member)->set('crew')->owned ?? []));
```

Un observateur qui consigne ce qui lui est annoncé vérifie les exemplaires remis et les ensembles complétés avec leurs
récompenses.
