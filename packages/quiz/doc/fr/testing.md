<!-- phil-dev source-sha256=894290c02f153547ae4b20d988490010eb4e1ae0561cea025aa78758449aeb9d -->
# Tests

## Les tests du paquet

```sh
composer install
vendor/bin/phpunit --no-configuration tests
node --test tests/js
```

La suite unitaire, sous `tests/Unit`, s'exécute sans noyau, sans base de données et sans réseau, sur une horloge que les
tests avancent ; elle produit le rendu du gabarit du résultat avec Twig. Les tests de scripts, sous `tests/js`, font
tourner avec Node.js les fonctions d'aide du contrôleur Stimulus. Dans le monodépôt, Infection maintient le paquet à
100 % des mutants couverts.

## Fuzzing

Deux cibles de fuzzing, sous `tests/Fuzz`, donnent à la bibliothèque ce qu'un joueur saisit : `pin`, un texte quelconque
lu comme un NIP, qui donne six chiffres ASCII ou `InvalidQuiz` ; `pseudonym`, un texte quelconque comme pseudonyme d'un
invité, passé dans la liste de mots, qui répond de la même façon deux fois. Dans le monodépôt, `composer fuzz` les lance.

## Bancs d'essai

`tests/Benchmark/QuizBench.php` mesure une réponse notée dans une session de 1 000 joueurs, le classement de cette
session après dix questions, le classement de 1 000 joueurs en dix équipes, et un pseudonyme vérifié contre la liste de
mots de la bibliothèque.
