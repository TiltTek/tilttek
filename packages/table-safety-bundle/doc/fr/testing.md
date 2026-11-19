<!-- phil-dev source-sha256=aaf664a0d5a691d8c58b3416ce8200e0aa1d81a18cb31f803e709aa6be705b94 -->
# Tests

## Les tests du bundle

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

Les tests unitaires couvrent la configuration et les services qu'elle câble, les tables, les quatre stockages DBAL sur
SQLite par les contrats de la bibliothèque, les sujets, la diffusion vers un hub et les abonnements, les formulaires et
leurs conversions, le module de vie privée et la commande de purge. Les tests fonctionnels démarrent une petite
application — FrameworkBundle avec sessions, formulaires et CSRF, DoctrineBundle sur SQLite, TwigBundle et composants
Twig, le catalogue de `phil/content-warnings-bundle`, un hub qui enregistre ce qu'on lui donne, les recettes du kit PHIL
UX copiées là où les met `ux:install` — et parcourent les écrans : un lecteur refusé, un signal levé avec un simple
formulaire et depuis un script, un signal refusé, les réponses, la séance zéro et les étoiles et souhaits enregistrés et
refusés, et le meneur de jeu qui demande un point d'étape, marque des signaux comme vus et change les réglages. Ils sont
ignorés quand le répartiteur d'événements, une dépendance de développement, ou le kit PHIL UX est absent. Dans le
monodépôt, Infection maintient le bundle à 100 % des mutants couverts.

## Tester une application qui l'utilise

Configurez `store: memory` dans l'environnement de test, enregistrez un `TableAccess` que le test pilote, et une
`MockClock` comme service `clock`.
