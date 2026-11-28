<!-- phil-dev source-sha256=67fccbedb18243cee9d34b5c634c3b8192bacc6f87d6c506602c61af7d47da62 -->
# Points d'extension

Le paquet est en `0.x` : ses points d'extension sont stables seulement à partir de sa version 1.0. D'ici là, une
version mineure peut les changer, et son CHANGELOG dit comment.

| Pour | Faites |
|---|---|
| Déclarer les règles de création d'un système | Écrivez des documents du [format](format.md) et lisez-les avec `Format\RulesReader`, ou construisez `CreationRules` en code. |
| Donner les attributs et leurs bornes | Passez les `Systems` de `tilttek/rpg-systems` à `CharacterRoller`. |
| Donner les tables où les champs tirent | Passez un `Generator` de `tilttek/generator` construit sur les listes et les générateurs de l'application. |
| Borner ou étendre les dés | Passez un `DiceRoller` de `tilttek/dice`, avec ses limites et ses dés personnalisés. |
| Donner le hasard des graines tirées | Passez un `Random\Randomizer` : un moteur sécurisé en production, un moteur fixe dans les tests. |
| Réagir à un tirage | Implémentez `Contract\CharacterObserver` et passez-le à `CharacterRoller`. |
| Conserver les personnages | Stockez `RandomCharacter::toArray()` avec la fiche, ou seulement la clé des règles et la graine quand rien n'a été modifié. |
| Montrer d'où vient le texte d'un personnage | Affichez `RandomCharacter::$attributions` avec lui, comme `tilttek/generator` les décrit. |

## L'observateur et son événement

`Contract\CharacterObserver::notify()` est informé de chaque personnage tiré, sous forme d'un `Event\CharacterRolled` :
la clé des règles, la clé du système, et si l'appelant a donné la graine. Une application envoie un message à partir de
là, avec ces identifiants seuls. [Tirage](rolling.md#lobservateur) donne les détails.

## Les règles sont des données

Les règles sont de simples données : la bibliothèque n'exécute aucun code de l'application. Ce qu'elles peuvent dire est
une liste fermée — trois méthodes pour les statistiques, des champs tirés et lancés, des marqueurs qui lisent les champs
précédents. Toute logique au-delà vit dans les générateurs, dont les gabarits Twig s'exécutent dans le bac à sable de
`tilttek/generator` avec son budget, ou dans l'application, qui lit le personnage et agit.

## Où la bibliothèque s'arrête

- Elle ne conserve ni personnages, ni règles, ni tables : l'application les stocke et en construit le catalogue, les
  systèmes et le générateur.
- Elle n'impose pas les bornes du système à une modification manuelle : une fiche de personnage le fait, quand elle
  reprend le personnage.
- Elle n'affiche pas un personnage : l'application produit le rendu de sa fiche.

## Les services

`CharacterRoller` et `Format\RulesReader` sont en lecture seule et ne gardent rien entre deux appels : une instance
sert toutes les requêtes d'un *worker*. `CreationRules`, `RulesCatalogue` et `RandomCharacter` sont des valeurs immuables
que les requêtes partagent.
