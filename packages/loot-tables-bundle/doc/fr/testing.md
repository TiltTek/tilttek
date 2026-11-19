<!-- phil-dev source-sha256=6d6b66e57a503a69d97a01cd0e71019afd00b90c27be5f0282238bd6b9e9db1b -->
# Tests

## Les tests du bundle

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

La suite Unit vérifie les germes et leur source, les tireurs, les engagements et l'écouteur du workflow de commande,
la révélation unique, les textes, les dés physiques, les résultats et leur relance, ce qui s'imprime, sur des commandes
du noyau du commerce en mémoire ; les stockages selon un même contrat (Doctrine sur SQLite en mémoire), le formulaire
des dés, le composant de l'acheteur, la commande de relance, la fiche de production et les services qu'enregistre la
configuration. La suite Functional démarre une application avec le noyau du commerce, Twig et TwigComponent en
français, et les recettes du kit PHIL UX copiées là où `ux:install` les met : elle passe une commande par le workflow,
qui engage ses tirages, la paie, laisse le noyau la tirer, vérifie et relance chaque tirage ; elle produit le rendu des
tirages de l'acheteur avant et après le paiement, relance un tirage avec la commande, saisit des dés par le formulaire
et son composant, et imprime la fiche de production. Elle est ignorée quand le répartiteur d'événements, une
dépendance de développement, ou le kit PHIL UX manque.

## Tester une application qui s'en sert

Réglez `store: memory`. Les germes viennent aussi du générateur cryptographique dans les tests : un test affirme donc ce
qui vaut pour chaque tirage plutôt que des faces données. `LootResults::verify()` vérifie un tirage de bout en bout,
`LootResults::replay()` redonne les faces, et des dés physiques saisis avec `PhysicalDice::enter()` donnent les faces
choisies.
