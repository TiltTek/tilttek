<!-- phil-dev source-sha256=b31306578c2fd22820e49923648ce4807f54c98541bf76569b0b35201087807b -->
# Tests

## Les tests du bundle

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

Les tests unitaires couvrent la configuration et les services, les tables et l'écouteur de schéma, les trois magasins
DBAL sur SQLite en mémoire par des copies des cas de test de contrat de la bibliothèque, les événements, les fonctions
Twig, les commandes et le module de vie privée. Le test fonctionnel démarre une petite application (FrameworkBundle
avec son traducteur, DoctrineBundle sur SQLite, TwigBundle, `phil/privacy-bundle`, `tilttek/activity-bundle`) et
enregistre des activités : niveaux, un jalon, une annulation, le rendu des gabarits, le balayage, la commande d'affichage
et un membre exporté puis effacé. Dans le monodépôt, Infection maintient le bundle à 100 % des mutants couverts.

`phil/privacy-bundle` est une intégration facultative, non installée avec le bundle : ses tests, et son bundle dans
l'application de test, sont sautés avec leur raison quand il manque, et un test vérifie qu'aucun module de vie privée
n'est alors enregistré.

## Tester une application qui l'utilise

Configurez `store: memory` dans l'environnement de test, enregistrez des activités par `Tilttek\Activity\Journal`, puis
lisez `Tilttek\Progression\Progression`. Figez le service `clock` pour tester les séries et les saisons.
