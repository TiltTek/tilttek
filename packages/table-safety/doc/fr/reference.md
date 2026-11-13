<!-- phil-dev source-sha256=e8789575b23ea1af6c6c4ab246158e91bc90c3fdfca416fea60985d4fc621f71 -->
# Référence

Chaque type public du paquet, avec son rôle.

| Type | Rôle |
|---|---|
| `TableSafety` | Le service : réglages, réponses, entente, pacte, signaux, points d'étape, étoiles et souhaits, export et effacement. |
| `TableSettings` | Ce qu'une table a choisi : outils, signaux nominatifs, qui voit les signaux, thèmes ajoutés, séance zéro. |
| `TableThemes` | Les thèmes sur lesquels une table répond : ceux d'un catalogue et les siens. |
| `SafetyTool` | Les outils, avec leurs crédits et leurs signaux. |
| `Boundary` | Une ligne ou un voile. |
| `LinesAndVeils` | Les limites d'un participant, par thème. |
| `ConsentLevel` | Vert, jaune ou rouge. |
| `ConsentChecklist` | Les niveaux d'un participant, par thème. |
| `Participation` | Les réponses d'un participant à une table. |
| `TableAgreement` | La réponse la plus stricte de chaque thème, anonyme, et ses préférences d'avertissement. |
| `SessionZero\QuestionKind` | Texte, une option, plusieurs options. |
| `SessionZero\Question` | Une question, traduite ou écrite par le meneur de jeu. |
| `SessionZero\Template` | Les questions d'une séance zéro. |
| `SessionZero\Templates` | Les gabarits intégrés. |
| `SessionZero\Answers` | Les réponses d'un participant à une séance zéro. |
| `SessionZero\Pact` | Les réponses d'une table, rassemblées sans leurs auteurs. |
| `Signal\SignalKind` | Les signaux de la partie. |
| `Signal\Audience` | La table, ou le meneur de jeu seul. |
| `Signal\Signal` | Un signal levé à une table. |
| `Signal\CheckInTally` | Les réponses au dernier point d'étape, comptées. |
| `Feedback\StarsAndWishes` | Les étoiles et les souhaits d'un participant. |
| `Feedback\Feedback` | Les étoiles et les souhaits d'un participant pour une séance. |
| `Feedback\FeedbackSummary` | Les étoiles et les souhaits d'une séance, sans leurs auteurs. |
| `Contract\TableStore` | Où les réglages sont gardés. |
| `Contract\ParticipationStore` | Où les réponses sont gardées. |
| `Contract\SignalStore` | Où les signaux sont gardés. |
| `Contract\FeedbackStore` | Où les étoiles et les souhaits sont gardés. |
| `Contract\SignalObserver` | Informé de chaque signal levé ou vu. |
| `Store\InMemoryTableStore`, `InMemoryParticipationStore`, `InMemorySignalStore`, `InMemoryFeedbackStore` | Les stockages en mémoire. |
| `Exception\TableSafetyException` | Toute exception de la bibliothèque. |
| `Exception\InvalidAnswer` | Une valeur que la bibliothèque refuse. |
| `Exception\ToolNotEnabled` | Un outil que la table n'utilise pas. |
| `Exception\NotGameMaster` | Un point d'étape demandé par quelqu'un d'autre que le meneur de jeu. |
