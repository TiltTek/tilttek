<!-- phil-dev source-sha256=6c5964b82b87bcd42c7339431f44da92261c1ab14989570c34cc861360eca76f -->
# Tests

## Les tests du paquet

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

La suite unitaire, sous `tests/Unit`, s'exécute sans noyau, sans base de données et sans réseau : les entrées vivent dans
`Store\InMemoryEntryStore`, les règles dans `Rules\InMemoryRuleBook`, un registre des rôles des tests tient un maître de
jeu par campagne et un joueur par personnage, un lanceur des tests donne les totaux qu'on lui donne et enregistre les
expressions qu'on lui a demandé de lancer, et un observateur des tests enregistre les événements dans l'ordre. Dans le
monodépôt, Infection maintient le paquet à 100 % des mutants couverts.

Une application teste son propre `EntryStore` d'après les règles du [magasin](extension-points.md#le-magasin) : rien pour
un identifiant jamais conservé, une nouvelle entrée conservée une fois, une entrée périmée refusée, une liste conservée
toute ou pas du tout, la version augmentée de un à chaque écriture, l'activité relue entière, et les trois lectures dans
l'ordre choisi, filtrées par campagne, personnage et étape, jusqu'à la limite. Elle teste son propre registre des rôles
pour un maître de jeu, un joueur et un membre qui n'est ni l'un ni l'autre, et son lanceur pour des totaux dans la
plage de chaque expression.

## Bancs d'essai

`tests/Benchmark/AdvanceBench.php` mesure, sur la plus grande campagne qu'une avance lit — 125 personnages, chacun avec
une file pleine de 8 activités, 1000 entrées —, une avance d'un jour par le service : les 1000 entrées lues, la première
de chaque file avancée et les 125 changées conservées d'un coup. Il mesure aussi les plus grandes règles qu'un système
déclare — 64 activités de 16 issues et 16 plages — vérifiées d'un bloc. Depuis la racine du monodépôt :

```sh
composer bench
```
