<!-- phil-dev source-sha256=e2dceb1c756f0e87f71a9f1d2ca904f4fa42ecd886a0238d4fa86b5dcc9e102f -->
# Écrans

## Les routes

| Route | Méthode et chemin | Qui |
|---|---|---|
| `tilttek_table_safety_table` | `GET /table-safety/{table}` | un participant |
| `tilttek_table_safety_raise` | `POST /table-safety/{table}/signals` | un participant |
| `tilttek_table_safety_signals` | `GET /table-safety/{table}/signals?after=…` | un participant |
| `tilttek_table_safety_acknowledge` | `POST /table-safety/{table}/signals/{id}/acknowledge` | le meneur de jeu |
| `tilttek_table_safety_answers` | `GET` et `POST /table-safety/{table}/answers` | un participant |
| `tilttek_table_safety_session_zero` | `GET` et `POST /table-safety/{table}/session-zero` | un participant |
| `tilttek_table_safety_feedback` | `GET` et `POST /table-safety/{table}/feedback/{session}` | un participant |
| `tilttek_table_safety_cards` | `GET /table-safety/{table}/cards` | un participant |
| `tilttek_table_safety_cards_pdf` | `GET /table-safety/{table}/cards.pdf` | un participant, avec `phil/paged-media` |
| `tilttek_table_safety_game_master` | `GET` et `POST /table-safety/{table}/game-master` | le meneur de jeu |

`{table}` est une référence de ressource comme `table:7`. Un lecteur que le `TableAccess` de l'application ne place pas
à la table est refusé avec 403 ; chaque réponse est privée et jamais mise en cache. Les envois portent un jeton CSRF ;
un formulaire refusé par la bibliothèque est montré de nouveau avec sa raison et le statut 422.

## La table pendant la partie

`table.html.twig` montre un bouton par signal que le lecteur peut lever — le point d'étape pour le meneur de jeu seul,
ses réponses tant qu'un point d'étape est ouvert —, chacun avec une phrase qui dit ce qu'il fait ; les signaux que voit
toute la table ; et ce que la table a convenu. Un signal levé depuis la page y revient avec 303 ; depuis un script qui
demande du JSON, il répond 201 et le signal.

Ce que la table a convenu, dans `_agreement.html.twig`, ne dit jamais qui a donné quelle réponse, et dépend du lecteur.
Le meneur de jeu voit la liste protectrice : chaque ligne, chaque voile et chaque niveau de la liste dès la première
réponse, pour qu'il les garde hors de la partie tout de suite. Les joueurs voient le sommaire montré aux gens,
`TableAgreement::shown()`, par groupes entiers du seuil de réponses de la bibliothèque, 3 participants par défaut : en
dessous, ils lisent seulement qu'il n'y a pas encore assez de réponses, et que le meneur voit déjà chaque ligne et
chaque voile.

## Les réponses d'un participant

`answers.html.twig` contient les lignes et voiles et la liste de consentement, un groupe de boutons radio par thème,
groupés par catégorie, puis les thèmes propres à la table. `session_zero.html.twig` montre les questions de la table,
avec une phrase et un lien qui renvoient un besoin de sécurité vers les lignes et voiles et la liste de consentement, que
le meneur voit dès la première réponse, alors que les réponses de la séance zéro attendent un groupe de participants ;
`feedback.html.twig`, deux zones de texte, une étoile ou un souhait par ligne. Chaque page dit qui voit les réponses.

## L'écran du meneur de jeu

`game_master.html.twig` montre chaque signal — les discrets marqués comme tels —, chacun avec un bouton pour le marquer
comme vu ; les réponses au dernier point d'étape, avec un avertissement quand quelqu'un a demandé de ralentir ou
d'arrêter ; ce que la table a convenu ; le pacte ; les étoiles et souhaits d'une séance ; et le formulaire des réglages —
outils, signaux nominatifs, qui voit les signaux, thèmes de la table, gabarit de la séance zéro et questions propres au
meneur de jeu.

## Les cartes imprimables

Une table jouée en personne préfère parfois des cartes sur papier. `cards.html.twig` montre, pour les outils que la
table utilise, la carte X — un grand X avec une phrase qui dit ce qu'elle fait —, la feuille des lignes et voiles — une
case pour une ligne et une pour un voile à côté de chaque thème — et la feuille de la liste de consentement — une case
verte, une jaune et une rouge à côté de chaque thème. Les thèmes sont ceux de la table : ceux du catalogue par catégorie,
puis ceux que la table a ajoutés. Les feuilles sont vierges, pour qu'un participant les remplisse à la main ; rien des
réponses de personne n'est imprimé. La page n'a pas besoin de JavaScript ; chaque carte est une section de classe
`tilttek-table-safety__card`, de sorte que la feuille de style d'impression de l'application commence chacune sur une
nouvelle page :

```css
@media print {
    .tilttek-table-safety--cards .tilttek-table-safety__card { break-after: page; }
    .tilttek-table-safety--cards nav, .tilttek-table-safety__print-help { display: none; }
}
```

Avec `phil/paged-media` installé, `cards.pdf` donne les mêmes cartes en PDF, sur des pages Letter, en Helvetica, avec les
libellés dans la langue du lecteur ; la page y renvoie. Une table qui n'utilise aucun des trois outils n'a aucune carte,
et son PDF est introuvable.

## Les formulaires

| Type de formulaire | Données |
|---|---|
| `ThemeAnswersType` | la réponse de chaque thème, par catégorie ; `ThemeAnswers` la convertit en `LinesAndVeils` ou en `ConsentChecklist` |
| `SessionZeroType` | les réponses par clé de question, pour `Template::answer()` |
| `StarsAndWishesType` | deux textes, un élément par ligne |
| `SafetyToolsType` | une liste de `SafetyTool`, jamais obligatoire : pour l'annonce d'une table |
| `TableSettingsType` | les réglages ; `TableSettingsType::settings()` les convertit en `TableSettings` |

Une application utilise `SafetyToolsType` dans son propre formulaire d'annonce d'une table : nommer les outils y est
recommandé, jamais obligatoire.

## Les recettes du kit

Les gabarits dessinent avec `Card`, `Badge`, `Button` et `Alert` ; `table_safety_kit('Card:Title')` nomme une recette
telle que l'application l'a installée. `table_safety_tools()` énumère les outils pour une légende ou une annonce.

## Le contrôleur Stimulus

`tilttek--table-safety--signals` se pose sur la liste des signaux. Il envoie en arrière-plan les formulaires marqués
`data-tilttek-table-safety-signal`, de sorte que lever un signal ne recharge pas la page et ne fait pas perdre sa place ;
s'abonne au hub quand il y en a un ; lit dans le fil les signaux manqués pendant que la connexion était perdue ; ajoute
chaque nouveau signal une seule fois, et met à jour celui qui est marqué comme vu. Sans lui, chaque formulaire
fonctionne comme un simple formulaire.

## Accessibilité

- Chaque signal est un bouton natif, atteint et activé au clavier, avec une phrase qui dit ce qu'il fait.
- Chaque nouveau signal est annoncé dans une région vivante polie, par son genre et jamais par qui l'a levé.
- Chaque section a un titre ; les réponses sont des boutons radio et des cases à cocher avec leurs étiquettes.
- Sur les cartes imprimables, chaque feuille est un tableau avec une légende par catégorie et des en-têtes pour ses
  thèmes et ses cases ; chaque case est nommée par sa réponse.
- La confirmation d'un formulaire enregistré est annoncée comme un statut.

## Traductions

Les textes sont dans le domaine `tilttek_table_safety`, en anglais et en français, comme messages ICU ; les questions
intégrées de la séance zéro sont `session_zero.questions.<key>` et leurs options `session_zero.options.<question>.<option>`.
