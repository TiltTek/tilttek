<!-- phil-dev source-sha256=88c4fff313bfdb5681f38dcc44582a165dc83a10f41d9e26179c68d735f37ac9 -->
# Points d'extension

Le paquet est en `0.x` : ses points d'extension sont stables seulement à partir de sa version 1.0. D'ici là, une
version mineure peut les changer, et son CHANGELOG dit comment.

| Point | Ce qu'une application fournit | Valeur par défaut |
|---|---|---|
| `Contract\PredictionStore` | L'endroit où les questions et les prédictions sont conservées | `Store\InMemoryPredictionStore`, pour les tests |
| La devise | Un code de devise de `tilttek/points` | Sa devise par défaut |
| La source | Un nom de compte d'émission de `tilttek/points` | `predictions` |
| `Random\Randomizer` | La source des identifiants | Un `Randomizer` sur le moteur sécurisé |
| La taille des lots | Le nombre de prédictions qu'un règlement lit à la fois | 500 |

La bibliothèque ne garde aucun état entre deux appels ; le magasin qu'une application fournit n'en garde pas non plus.

## Le magasin

`Contract\PredictionStore` conserve les questions et les prédictions. Un magasin adossé à une base de données offre :

- une contrainte d'unicité sur la clé d'une question : `addQuestion()` renvoie la question conservée sous la clé quand
  il y en a une ;
- `close()`, qui fait passer une question ouverte à résolue ou annulée une seule fois, et renvoie telle quelle une
  question déjà close ;
- une contrainte d'unicité sur la question et le membre d'une prédiction : `save()` remplace le choix du membre en
  gardant l'identifiant de sa première prédiction ;
- `save()`, qui vérifie que la question accepte des prédictions à ce moment et écrit en une seule étape — une insertion
  ou une mise à jour conditionnelle jointe à la question ouverte avant son échéance —, et renvoie null sans rien écrire
  dans le cas contraire ;
- `predictions()`, qui lit les prédictions d'une question dans l'ordre de leurs identifiants, après celui qu'on lui
  donne, pour qu'une prédiction effacée entre-temps ne décale aucune page ;
- `eraseMember()`, qui supprime les prédictions du membre.
