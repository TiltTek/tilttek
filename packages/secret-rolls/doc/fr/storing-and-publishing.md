<!-- phil-dev source-sha256=f4bd60f98870fb228c31bf2d9a08d4566738bd68b315c8103d2f3c02aa351c37 -->
# Stocker et publier

## Le dépôt de stockage

`Contract\SecretRollStore` garde les journaux des scènes et les jets scellés :

| Méthode | Fait |
|---|---|
| `entries($scene)` | Les entrées du journal de la scène, par position ; aucune pour une scène qui n'en a pas |
| `commit($entry, $roll)` | Garde le jet caché scellé et ajoute l'entrée de son engagement, les deux ou aucun |
| `append($entry)` | Ajoute une entrée qui révèle un jet ou clôt la scène |
| `hidden($roll)` | Le jet scellé de cet identifiant, ou null |

Le journal ne fait que croître : une entrée est ajoutée à la position qui suit la dernière, jamais modifiée ni retirée.
Un dépôt de stockage lance `JournalConflict` quand l'entrée ne vient pas à cette position — deux écrivains ont ajouté
en même temps — ; l'appelant relit le journal et recommence. Sur une base de données, une clé unique sur la scène et la
position donne cette garantie, et `commit()` écrit l'entrée et le jet scellé dans une seule transaction.

Les jets scellés sont le secret du dépôt de stockage : quiconque les lit connaît les résultats cachés avant la
révélation. Gardez-les à part de ce que la table lit, chiffrés au repos quand le stockage est partagé.
`HiddenRoll::toArray()` et `HiddenRoll::fromArray()` donnent les valeurs simples à garder ; `JournalEntry::toArray()` et
`JournalEntry::fromArray()` celles d'une entrée, son empreinte gardée telle qu'écrite.

`Store\InMemorySecretRollStore` garde tout dans le processus, pour les tests et pour une table qui vit dans un seul
processus.

## Publier

`Contract\JournalPublisher` est informé de chaque entrée juste après que le dépôt de stockage l'a ajoutée : une tour de
dés, une superposition ou un pont de clavardage la pousse vers la table. Une entrée est publique — un jet engagé montre
son engagement, jamais son résultat —, et peut donc être poussée telle quelle ; un message vers une file d'attente ne
porte que la scène et la position, et le consommateur lit l'entrée dans le dépôt de stockage. `Store\NoPublisher`, la
valeur par défaut, ne pousse rien : la table lit le journal quand elle le demande.
