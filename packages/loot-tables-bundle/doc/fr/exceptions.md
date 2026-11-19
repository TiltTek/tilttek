<!-- phil-dev source-sha256=5e33397340aadb38f7dae65174f36558a312a5b365d02ec824314a3bbbff26b0 -->
# Exceptions

| Exception | Levée quand |
|---|---|
| `Domain\Exception\LootDrawRefused` (`DomainException`) | On demande de tirer une unité jamais engagée alors qu'un engagement est exigé ; un tirage est révélé ou saisi deux fois, ou révélé à partir d'un autre germe ; une option de texte n'a pas de liste dont un dé peut tirer ; des dés physiques sont saisis pour une ligne ou une option qui n'est pas une boîte qui reste à tirer, ou sans un code par unité |
| `Tilttek\LootTables\Exception\InvalidLoot` | La configuration déclare une table ou une boîte que la bibliothèque refuse, à la compilation du conteneur |
| `Tilttek\LootTables\Exception\InvalidCode` | Un code de boutique saisi à une caisse ne se lit pas comme les dés de sa boîte |
| `Tilttek\LootTables\Exception\UnknownLoot` | Un tirage nomme une boîte que le catalogue ne contient plus |

Une `LootDrawRefused` levée pendant que la commande est passée (une option de texte sans liste dont tirer) sort de la
transition `place` du workflow : la commande ne doit pas être enregistrée comme passée.

Le noyau du commerce refuse avec son propre `DrawRefused` une commande non payée, ou une option dont aucun service ne
prend en charge le tireur.
