<!-- phil-dev source-sha256=4a91b3ea623f2f09a4f3b636da180bb17a7a20b544ea20c3a176c18aac9a84f7 -->
# Tests

## Les tests de la bibliothèque

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

La suite unitaire, sous `tests/Unit`, s'exécute sans noyau, sans base de données et sans réseau, sur le registre et le
journal en mémoire et une horloge *mock* : une admission et ses premières fois, clés et contextes ; une lecture répartie
de nouveau, enregistrée une seule fois ; une rentrée qui n'ajoute aucune première fois ; les jours et les semaines ISO
du fuseau horaire de l'événement ; une horloge d'appareil en avance sur le serveur ; les sorties, les refus, les
conflits et les lectures que le registre ne contient pas, laissés de côté ; un membre effacé, laissé de côté ; les
verbes qu'un journal déclare ; les critères, lus par le `QuestReader` de `tilttek/quests` et comptés sur ce que
l'enregistreur a enregistré ; le passeport, chaque jour, les lieux distincts et une série de semaines avec des trous ;
et le résumé, refusé à tous sauf au membre. Dans le monodépôt, Infection tient la bibliothèque à 100 % des mutants
couverts.

Le test d'intégration, sous `tests/Integration`, enregistre un écouteur qui appelle l'enregistreur sur un répartiteur
d'événements de Symfony, et répartit deux fois les événements de `phil/checkin-bundle`, construits par ses propres
constructeurs nommés, en ligne et synchronisés depuis un journal hors ligne.

## Tester le code qui récompense la présence

Construisez le registre de `phil/checkin` et le journal de `tilttek/activity` en mémoire, sur une horloge *mock*,
ajoutez un enregistrement au registre et remettez son identifiant à l'enregistreur :

```php
$ledger = new InMemoryCheckInLedger();
$journal = new Journal(new InMemoryActivityLog(), new MockClock('2027-03-05 18:00:00 UTC'));
$recorder = new AttendanceRecorder($ledger, $presences, $journal);

$checkIn = CheckIn::restore(ScanId::of('scan-1'), 'ticket-44', CheckpointId::of('panel-7'), Direction::In,
    ScanOutcome::Admitted, DeviceId::of('desk-1'), $now, $now, 1);
$ledger->append($checkIn);
$recorder->record($checkIn->id);
```

Lisez ensuite les totaux du journal, ou les étapes d'une quête construite avec `AttendanceCriterion`, comme le fait
l'application.
