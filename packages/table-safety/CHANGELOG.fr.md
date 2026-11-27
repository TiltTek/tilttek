<!-- phil-dev source-sha256=5f8683dd3ccb39134eb86d684bc4c9303d3786432adb8c154a27517afc1046d0 -->
# Journal des modifications

[English](CHANGELOG.md)

Tous les changements notables de `tilttek/table-safety` sont consignés dans ce fichier.

Les mises à jour suivent les principes de [Keep a CHANGELOG](https://keepachangelog.com/).

## 0.3.0-alpha - 2026-11-15

Ce que le pacte et l'entente montrent aux personnes change.

### Modifié

- `SessionZero\Pact` ne retient les réponses que par groupes entiers d'un seuil de participants, 3 par défaut, pris dans
  l'ordre où elles ont été données : sous le seuil, chaque décompte est à 0, chaque liste de textes est vide,
  `respondents` vaut 0 et `hasEnoughAnswers()` est faux ; au-delà, les réponses d'un groupe qui n'est pas plein
  attendent que le groupe se remplisse. `Pact::of()` prend le seuil ; un seuil sous 1 est une `InvalidAnswer`.
- `TableAgreement::$respondents` compte les réponses du résumé montré aux personnes, par groupes entiers du même seuil,
  plutôt que chaque participant qui a répondu. `lines()`, `veils()`, `boundary()`, `consent()`, `checklist()`,
  `themesAt()` et `preferences()` appliquent encore chaque réponse, sous le seuil aussi, pour qu'une limite ou un voile
  protège la table dès la première réponse. `TableAgreement::of()` prend le seuil.
- `TableSafety` prend le seuil de l'entente et du pacte comme `answersThreshold`, et leur donne les participations de la
  table dans l'ordre de leur première réponse, pour qu'un nouveau participant attende son groupe et qu'un participant
  qui change ses réponses garde sa place.
- `TableSafety::export()` donne le `first_answered_at` de chaque participation.
- `FeedbackSummary::DEFAULT_THRESHOLD` et `FeedbackSummary::assertThreshold()` sont ceux de `RespondentThreshold`.

### Ajouté

- `AgreementSummary` : l'entente montrée aux personnes, à partir de `TableAgreement::shown()`.
- `TableAgreement::$threshold`, `TableAgreement::shown()` et `TableAgreement::hasEnoughAnswers()`.
- `Pact::$threshold` et `Pact::hasEnoughAnswers()`.
- `RespondentThreshold` : le seuil par défaut de 3, sa vérification et le compte des groupes entiers.
- `Participation::$firstAnsweredAt` : le moment de la première réponse du participant à la table, gardé quand il change
  ses réponses, et `Participation::inOrderOfFirstAnswer()`. Le constructeur le prend en dernier ; quand il n'est pas
  donné, c'est celui de `$updatedAt`. Un `ParticipationStore` le garde et le rend.
- La documentation du seuil de l'entente et du pacte, avec ce qui protège la table dès chaque réponse et ce que le seuil
  ne cache pas.

## 0.2.0-alpha - 2026-11-14

### Modifié

- `SignalStore` reçoit `lastCheckIn()` : le dernier point de contrôle de la table suivi de chaque réponse levée après
  lui, sans limite. `TableSafety::checkIn()` compte par lui, pour qu'un point de contrôle suivi de plus de 100 signaux
  soit encore compté ; un stockage écrit pour 0.1 l'implémente. `recent()` et sa limite de 100 restent pour les écrans.
- `FeedbackSummary` ne retient les réponses que par groupes entiers d'un seuil de participants, 3 par défaut, pris dans
  l'ordre où elles ont été données : sous le seuil, il ne retient rien, `respondents` vaut 0 et `hasEnoughAnswers()` est
  faux ; au-delà, les réponses d'un groupe qui n'est pas plein attendent que le groupe se remplisse.
  `FeedbackSummary::of()` prend le seuil, et `TableSafety` le prend comme `feedbackThreshold` ; un seuil sous 1 est une
  `InvalidAnswer`.

### Ajouté

- `FeedbackSummary::$threshold`, `FeedbackSummary::hasEnoughAnswers()`, `FeedbackSummary::assertThreshold()` et
  `FeedbackSummary::DEFAULT_THRESHOLD`.
- La documentation sur la vie privée du résumé des étoiles et des souhaits : le seuil et ce qu'il ne cache pas.

## 0.1.0-alpha - 2026-11-13

### Ajouté

- `LinesAndVeils` et `ConsentChecklist` : les limites et les voiles d'un participant et les niveaux vert, jaune et rouge
  par thème, sur les thèmes de `phil/content-warnings`, immuables et bornés, enregistrés comme tableaux, et les
  préférences d'avertissement qu'ils représentent.
- `TableThemes` : les thèmes d'un catalogue et ceux qu'une table ajoute, avec leurs libellés.
- `TableAgreement` : la limite et le niveau les plus stricts de chaque thème d'une table, sans dire qui les a donnés, et
  les préférences d'avertissement de la table.
- La séance zéro : `Question` de trois types, `Template` avec les propres questions d'un meneur de jeu, les quatre
  `Templates` intégrés — partie unique, campagne, convention, en ligne —, `Answers` et le `Pact` anonyme.
- Les signaux : `SignalKind` pour la carte X, le Script Change, la porte ouverte et les points de contrôle, `Audience`,
  `Signal`, anonyme par défaut et vu par la table ou par le meneur de jeu seul, et `CheckInTally`.
- `StarsAndWishes`, `Feedback` et le `FeedbackSummary` anonyme d'une séance.
- `TableSettings` et `SafetyTool`, avec les personnes à qui chaque outil est attribué.
- Les contrats `TableStore`, `ParticipationStore`, `SignalStore`, `FeedbackStore` et `SignalObserver`, et les stockages
  en mémoire.
- `TableSafety` : le service des outils, avec l'exportation et l'effacement d'un participant.
- Les exceptions `TableSafetyException`, `InvalidAnswer`, `ToolNotEnabled` et `NotGameMaster`.
- La documentation dans `doc/`, un fichier par sujet, avec les crédits des outils, leurs sources et les licences de
  leurs textes, indexée par le README.
- `CONTRIBUTING.md`, `SECURITY.md` et `CODE_OF_CONDUCT.md`.
