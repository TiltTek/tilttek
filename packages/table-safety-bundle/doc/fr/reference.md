<!-- phil-dev source-sha256=dd07d4e87908f088ffe13dfeeed7b64db5bb49c597488882f8068177d7161624 -->
# Référence

Chaque classe du bundle, avec son rôle.

| Classe | Rôle |
|---|---|
| `TilttekTableSafetyBundle` | Le bundle et sa configuration. |
| `Application\Contract\TableAccess` | Qui participe à une table et qui la mène, fourni par l'application. |
| `Application\NoTableAccess` | L'accès d'une application qui n'en a nommé aucun : personne. |
| `Application\SignalTopics` | Les sujets des signaux d'une table. |
| `Infrastructure\DependencyInjection\TableSafetyServices` | Les services que câble la configuration. |
| `Infrastructure\DependencyInjection\KitSubdirectoryPass` | Nomme les recettes du kit d'après le sous-répertoire du Toolkit. |
| `Infrastructure\Doctrine\TableSafetyTables` | Les quatre tables. |
| `Infrastructure\Doctrine\TableSafetySchemaListener` | Ajoute les tables au schéma que l'ORM génère. |
| `Infrastructure\Doctrine\DbalTableStore`, `DbalParticipationStore`, `DbalSignalStore`, `DbalFeedbackStore` | Les stockages sur Doctrine DBAL. |
| `Infrastructure\Doctrine\Rows` | Lecture et écriture des valeurs d'une ligne. |
| `Infrastructure\Form\ThemeAnswersType`, `ThemeAnswers` | Le formulaire des lignes et voiles ou de la liste, et ses conversions. |
| `Infrastructure\Form\SessionZeroType` | Le formulaire d'une séance zéro. |
| `Infrastructure\Form\StarsAndWishesType` | Le formulaire des étoiles et souhaits. |
| `Infrastructure\Form\SafetyToolsType` | Les outils d'une annonce. |
| `Infrastructure\Form\TableSettingsType` | Le formulaire des réglages du meneur de jeu. |
| `Infrastructure\Http\TableController` | L'écran de la table et ses signaux. |
| `Infrastructure\Http\AnswersController` | Les réponses d'un participant, séance zéro et étoiles et souhaits. |
| `Infrastructure\Http\GameMasterController` | L'écran du meneur de jeu. |
| `Infrastructure\Http\CardsController` | Les cartes imprimables d'une table, en page et en PDF. |
| `Infrastructure\Http\Screens` | Ce que les écrans partagent : la table, le lecteur, le jeton CSRF, le rendu. |
| `Infrastructure\Http\Views` | Les tableaux où puisent les gabarits. |
| `Infrastructure\Print\SafetyCardsPdf` | Les cartes imprimables en PDF, sur `phil/paged-media`. |
| `Infrastructure\Mercure\MercureSignalObserver` | Diffuse les signaux au hub. |
| `Infrastructure\Mercure\SignalSubscriptions` | L'abonnement d'un lecteur. |
| `Infrastructure\Privacy\TableSafetyPrivacy` | L'export et l'effacement d'un participant pour `phil/privacy-bundle`. |
| `Infrastructure\Console\PurgeSignalsCommand` | `tilttek:table-safety:purge-signals`. |
| `Infrastructure\Twig\TableSafetyExtension` | `table_safety_kit()` et `table_safety_tools()`. |
| `Infrastructure\Twig\KitComponents` | Les noms des recettes du kit. |
