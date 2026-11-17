<!-- phil-dev source-sha256=0e334df6e55be295829b3fd837434f83b8e4a893d43691c4106e745d28930413 -->
# Installation

```sh
composer require tilttek/quests
```

La bibliothèque exige PHP 8.5 ou plus récent, `tilttek/activity` (le journal que lisent ses étapes), `phil/resource` et
`psr/clock`. Elle n'a aucune autre dépendance, et aucun paquet de ludification : les points, les badges ou les objets
qu'une quête récompense sont donnés par l'application.

Pour l'utiliser, une application :

1. construit un `Catalog` : `InMemoryCatalog::fromArray()` sur sa configuration, ou un catalogue qui lui est propre sur
   une base de données ;
2. implémente les quatre magasins d'[Écrire un magasin](writing-a-store.md), ou prend ceux en mémoire pour les tests et
   les scripts ;
3. construit un `Evaluator` sur le catalogue et les magasins, puis `Quests`, `Duels` et une `QuestProjection`, en
   donnant à `Quests` le `TeamMembership` qui dit qui fait partie de quelle équipe — sans lui, toute quête d'équipe est
   refusée —, et aux deux derniers un `QuestObserver` qui donne les récompenses ;
4. enregistre la projection auprès du `Projector` de `tilttek/activity`, en direct par `LiveProjections` ou par une
   tâche qui la rattrape.

Dans une application Symfony, `tilttek/quests-bundle` fait tout cela.
