<!-- phil-dev source-sha256=bd18d91e19f02f5b3683bf83f16dbde702df6299e03c086008a7d942c02ed9a2 -->
# Tests

## Les tests de la bibliothèque

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

La suite unitaire, sous `tests/Unit`, s'exécute sans noyau, sans base de données et sans réseau, sur le dépôt en
mémoire et le `MockClock` de `symfony/clock` : les réussites et leurs limites — clés, occurrences, paramètres, moment
d'arrêter d'être montré — ; la réception unique, pour un membre et non pour un autre ; les groupes par source et par
genre, leur ordre à la microseconde et par réception, les entrées qu'ils montrent et comptent ; la prochaine
célébration, les préférences des membres et la réduction des mouvements de l'appareil ; le marquage comme vu par
séquence, par groupe et tout à la fois ; les retraits signalés deux fois ; les entrées qui deviennent caduques quand
leur moment vient et quand la limite des entrées en attente est atteinte ; la boîte qui retire ses premières entrées
réglées au-delà de sa limite tout en gardant les entrées en attente ; les pages d'historique ; l'effacement, avec des
séquences jamais réutilisées ; et les événements signalés. Dans le monodépôt, Infection maintient la bibliothèque à
100 % des mutants couverts.

Le banc d'essai, sous `tests/Benchmark`, remplit une boîte aux limites par défaut — 200 entrées en attente de quatre
sources parmi 500 — et mesure le compte, la prochaine célébration et une réussite reçue à la limite.

## Tester le code qui utilise la boîte

`Store\InMemoryCelebrationStore` et un `MockClock` donnent une boîte complète dans un test. On teste un écouteur de
l'application en lui remettant l'événement du paquet de récompenses et en lisant `pending()`, ou par un observateur qui
enregistre les `CelebrationChanged` dont il est informé.
