<!-- phil-dev source-sha256=05c51ec904d3ac8c2149e332c678b7b32a10dae4ee6a6ea30ecf56fc826aaef4 -->
# Rejouer

Les cotes d'un jeu sont ce que ses résultats ont donné, l'un après l'autre. `replay()` les calcule de nouveau depuis le
début :

```php
$ratings->replay('chess');   // the number of rated results replayed
```

Elle lit chaque résultat du jeu dans le `Contract\PlaySource` donné à `Ratings`, dans l'ordre où ils ont été joués, cote
chacun à partir des cotes initiales, saute les amicaux, et remplace d'un coup toutes les cotes du jeu dans le dépôt de
stockage, avec les résultats que le jeu compte : ceux que la relecture a cotés, pour qu'en enregistrer un de nouveau soit
refusé et qu'un résultat supprimé de la source puisse être enregistré de nouveau. Chaque membre garde la visibilité qu'il a choisie ; un membre qui a une cote publique et plus aucun résultat la
garde, à la cote initiale. Rien n'est envoyé : une relecture ne change l'historique d'aucun membre, elle le corrige.

Rejouez un jeu après qu'un résultat a été corrigé ou supprimé, après qu'un membre a été effacé de la source, ou pour
changer de système ou de réglage : un `Ratings` avec un autre système refuse les cotes de l'ancien jusqu'à ce que le jeu
soit rejoué avec lui.

## La source

```php
final class PlayLogSource implements PlaySource
{
    public function results(string $game): iterable
    {
        foreach ($this->plays->ofGame($game) as $row) {   // oldest first
            yield GameResult::fromArray($row);
        }
    }
}
```

La source donne chaque résultat du jeu, amicaux compris, du plus ancien au plus récent ; les résultats du même moment
gardent l'ordre que donne la source. Une relecture refuse par `InvalidRating` un résultat d'un autre jeu, un résultat
antérieur à celui d'avant, et un résultat donné deux fois. Un journal des parties est la source naturelle ; tant que
`tilttek/play-log` n'en fournit pas, l'application implémente l'interface sur ses propres enregistrements.

## Les mêmes valeurs à chaque fois

Les cotes Elo sont gardées en centièmes et les habiletés en millionièmes, chacune arrondie une fois par partie : aucune
erreur ne se transmet d'une partie à l'autre, et les mêmes résultats dans le même ordre donnent les mêmes cotes, quoi
qu'on ait enregistré avant. Une relecture d'un jeu dont tous les résultats ont été enregistrés redonne exactement les
cotes que `record()` avait gardées.

Une relecture garde en mémoire les cotes des membres du jeu et lit les résultats un à un ; sur un grand jeu, lancez-la
depuis un *worker* ou une commande de console, pas dans une requête.
