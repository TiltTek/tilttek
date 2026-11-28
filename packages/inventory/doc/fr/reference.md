<!-- phil-dev source-sha256=ded221fae94e3e34a77fb44f50e24afda72a9d410f8cf198a649a3f12ea7562f -->
# Référence

Chaque type public du paquet, avec son rôle.

| Type | Rôle |
|---|---|
| `Inventories` | Ouvre, exécute, lit et efface les inventaires, leurs objets, leurs charges et leurs journaux |
| `Inventory` | Un inventaire : référence, propriétaire, nom, système de jeu, moment d'ouverture |
| `Item` | Un objet sous une clé : nom, quantité, encombrement, lieu, contenant, équipé, moment d'ajout |
| `Burden` | Ce que pèse une unité et les emplacements qu'occupe une pile |
| `Place` | La réserve, un porteur ou un contenant, écrit et relu sous forme de texte |
| `Container` | Ce qui fait d'un objet un contenant : ses limites et si son contenu pèse |
| `Fill` | Ce qu'un contenant renferme par rapport à ses limites |
| `Measure` | `Weight` ou `Slots` |
| `CarryRules` | Les règles de transport d'un système : mesure, capacité par défaut, paliers, refus au-delà de la capacité |
| `LoadBand` | Un palier de charge, jusqu'à une fraction de la capacité |
| `Load` | Ce que porte un porteur, sa capacité, son palier, sa limite et sa marge |
| `LogEntry` | Une ligne du journal d'un inventaire |
| `Limits` | Inventaires par propriétaire, objets par inventaire, profondeur des contenants, entrées du journal |
| `InventoryChanged` | Communiqué après chaque acte, des identifiants seulement |
| `Change` | Ce qui est arrivé à un inventaire |
| `Refusal` | Pourquoi un acte est refusé |
| `Contract\InventoryStore` | Où sont conservés les inventaires, les objets et les journaux |
| `Contract\RuleBook` | Les règles de transport de chaque système |
| `Contract\CarrierCapacity` | La capacité de chaque porteur |
| `Contract\InventoryObserver` | Ce que l'application fait des événements |
| `Store\InMemoryInventoryStore` | Le dépôt en mémoire |
| `Rules\StaticRuleBook` | Des règles de transport données par clé de système |
| `Rules\DefaultCapacity` | La capacité par défaut des règles, pour tous les porteurs |
| `Exception\InventoryException` | Toute exception de la bibliothèque |
| `Exception\InventoryRefused` | Un acte refusé, avec son `Refusal` |
| `Exception\UnknownInventory` | Aucun inventaire sous une référence, aucun objet sous une clé |
| `Exception\InvalidInventory` | Une valeur hors limites |
