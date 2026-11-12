<!-- phil-dev source-sha256=a92c685f2f1f6ba6748deb040bd684992c14265b6f66b1996c1aaf55e13d8f55 -->
# Consigner

## Consigner ce qui a été écrit

```php
$plan = $goals->log('novel-2026', 1_200);                               // today, in the author's timezone
$plan = $goals->log('novel-2026', 800, Day::fromString('2026-11-02'));  // an earlier day
$plan = $goals->log('novel-2026', -200);                                // taken back: logged too high
$plan = $goals->correct('novel-2026', 950);                             // today's count replaced
```

`log()` ajoute au compte d'un jour, en une seule étape auprès du magasin, de sorte que deux comptes consignés en même
temps comptent tous deux ; un compte retiré ne fait jamais descendre un jour sous 0. `correct()` remplace le compte d'un
jour. Chacune répond le plan au jour d'aujourd'hui de l'auteur. Un compte consigné va de -1 000 000 000 à
1 000 000 000 ; un jour compte de 0 à 1 000 000 000. Un jour postérieur au jour d'aujourd'hui de l'auteur est refusé : on
consigne des mots sur un jour venu ; un jour antérieur au premier jour de l'objectif l'est aussi.

L'application compte les mots — du manuscrit de l'auteur, d'une séance dans son éditeur — et consigne le compte ; la
bibliothèque ne lit jamais un texte.

## L'objectif atteint

Quand un compte fait passer l'objectif de sous sa cible à sa cible ou au-delà, `WritingGoals` envoie
`Event\GoalReached` avec l'identifiant de l'objectif, son auteur et le jour où est allé le compte. Un compte consigné
ensuite ne l'envoie pas de nouveau ; un objectif ramené sous sa cible par une correction, puis atteint de nouveau, l'envoie
de nouveau, et deux comptes consignés en même temps peuvent l'envoyer tous deux. Un écouteur qui accorde quelque chose —
un badge, des points — ne l'accorde qu'une fois grâce à la clé `idempotencyKey()` de l'événement, une par objectif.

## Célébrer

Atteindre un objectif mérite d'être souligné. La bibliothèque laisse la célébration à l'application : un écouteur de
`GoalReached`, ou la page qui reçoit un plan dont `isReached()` vient de devenir vrai, lance l'animation qu'offre le kit
d'interface du site — confettis, feux d'artifice — et respecte le choix de mouvement réduit du membre. La bibliothèque
n'ajoute aucune animation.

## Effacement

`$goals->erase($author)` supprime tous les objectifs de l'auteur et les comptes consignés pour eux, et répond combien
d'objectifs il y avait.
