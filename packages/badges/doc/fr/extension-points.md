<!-- phil-dev source-sha256=388783c2243169d76b37c0d15eb821aabd60cd9a789d6ba508b45d80bd2705de -->
# Points d'extension

| Point | Ce que fournit une application | Où |
|---|---|---|
| `Store\AwardStore`, `Store\ProgressStore`, `Store\ProfileStore`, `Store\GrantStore` | Le stockage des attributions, de la progression, des profils et des consentements, à la place des stockages en mémoire | [Écrire un stockage](writing-a-store.md) |
| `Measures` | Les valeurs que lisent les critères `Measure` — un solde de points, un niveau — depuis les autres paquets de l'application | [Badges et critères](criteria.md#critères) |
| `Criterion\Criterion`, `Criterion\Leaf` | Un critère qui lui est propre, construit dans le code à côté des critères fournis | [Badges et critères](criteria.md#critères) |
| `AwardObserver` | Ce qui se fait quand un membre gagne ou perd un badge : un avis, une récompense | [Attribuer](awarding.md) |
| `ShareChannel` | L'endroit où un membre partage un succès, quand il le demande | [Ce que montre un membre](showing.md) |
| `Psr\Clock\ClockInterface` | L'horloge que lisent les services : celle du système en production, une horloge fixe dans les tests | [Tests](testing.md) |

Chaque point est passé au constructeur du service qui l'utilise. Les services ne gardent aucun état propre entre deux
appels.
