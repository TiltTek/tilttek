<!-- phil-dev source-sha256=2c52bff1e0386ef37b9513a340a03d4d152174c5228a6bbf97f4133387389c53 -->
# Votes

Un membre vote sur une entrée avec un `Ballot` ; voter de nouveau le remplace, et `Polls::withdraw()` le retire.

```php
$polls->vote(new Ballot(
    entry: 'tide',
    member: 'm-104',
    ratings: [3 => 4, 4 => 5],                                // a rating of 1 to 5 by number of players
    choices: [3 => PlayerCountChoice::Recommended, 4 => PlayerCountChoice::Best],
    weight: 3,                                                // 1 light to 5 heavy
    minimumAge: 12,
    languageDependence: LanguageDependence::Moderate,
));
```

Un bulletin contient au moins un vote, et des votes sur au plus 30 nombres de joueurs, chacun de 1 à 999. Un nombre de
joueurs accepte les deux sortes de vote : la note de 1 à 5 dit la qualité du jeu à ce nombre, et les trois choix —
idéal, recommandé, non recommandé — répondent à la question que les joueurs connaissent déjà d'autres catalogues.

## Les résultats

`Polls::results()`, ou les `results` d'une `Sheet`, compte les bulletins en `PollResults`. Les moyennes sont des
centièmes entiers, arrondis à la hausse à la moitié — 350 pour 3,5 —, pour qu'elles se comparent et s'affichent sans
virgule flottante.

| Méthode | Donne |
|---|---|
| `playerCount($n)` | Un `PlayerCountResult` : les notes, leur somme et `averageRating()`, les votes de chaque choix et `choice()` |
| `topPlayerCounts()` | Les nombres de joueurs notés 4,00 ou plus en moyenne |
| `recommendedPlayerCounts()` | Les nombres de joueurs notés 3,00 ou plus en moyenne |
| `bestByChoice()` | Les nombres de joueurs que la plupart des membres ont choisis comme idéaux |
| `recommendedByChoice()` | Les nombres de joueurs que la plupart des membres ont choisis comme idéaux ou recommandés |
| `weight()` | Le poids moyen, de 100 à 500 |
| `minimumAge()` | La médiane des âges votés, la plus basse des deux votes du milieu |
| `languageDependence()` | Le niveau le plus voté |

Une égalité de choix va au plus prudent — non recommandé, puis recommandé —, et une égalité de dépendance à la langue
au niveau le plus haut, qui avertit. Un nombre de joueurs sans note n'a pas de moyenne et ne figure dans aucune liste de
notes.

## À côté de l'éditeur

Les résultats ne remplacent jamais les valeurs de l'éditeur : une page montre `players` tel qu'annoncé et, à côté, les
nombres que les membres recommandent, l'âge minimal de la boîte et l'âge que les membres votent. Une correction change
les valeurs de l'éditeur ; un vote ne les change jamais.
