<!-- phil-dev source-sha256=83c3ac5bb82d95a51aeaba2db74158a70332f0039153707405a236d487978fad -->
# Tests

## Les tests du paquet

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

La suite unitaire, sous `tests/Unit`, s'exécute sans noyau, sans base de données et sans réseau : les horloges vivent
dans `Store\InMemoryClockStore`, et l'heure est la `MockClock` de `symfony/clock`, avancée par chaque test jusqu'au
moment dont il a besoin — quelques secondes d'un tour, les jours d'une partie par correspondance, la durée de
vacances. Dans le monodépôt, Infection maintient le paquet à 100 % des mutants couverts.

Une application teste son propre `ClockStore` selon les règles du [stockage](extension-points.md#le-stockage) : rien
pour un identifiant jamais gardé, une nouvelle horloge gardée une fois, une horloge périmée refusée, la version
augmentée de un à chaque écriture, et le contrôle du temps, les joueurs, les vacances et les moments relus à la
milliseconde.
