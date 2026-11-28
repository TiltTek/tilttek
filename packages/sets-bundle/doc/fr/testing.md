<!-- phil-dev source-sha256=1c414021e5d12d63afaef5640ffc63c49d0ac684952554e2981be00c03e56aa9 -->
# Tests

La suite unitaire, sous `tests/Unit`, construit le conteneur du bundle sans noyau : les services par défaut et ceux
d'une configuration, les stockages en mémoire, le recul, une intégration laissée de côté quand son composant manque, le
planificateur désactivé, les bornes refusées ; la commande, avec sa limite, son détenteur, un gestionnaire qui échoue et
un détenteur illisible ; le message, son *handler* et le planificateur, avec son intervalle, sa limite et son verrou.

La suite fonctionnelle, sous `tests/Functional`, démarre une application avec FrameworkBundle, Messenger et le
Scheduler : une complétion enregistrée et annoncée, la commande trouvée par la console, le transport du planificateur et
le message traité par son *handler*.

Dans une application, `InMemoryDefinitionStore` et `InMemoryCompletionStore`, les stockages par défaut quand aucun n'est
nommé, tiennent les tests loin d'une base de données.
