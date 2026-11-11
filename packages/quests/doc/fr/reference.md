<!-- phil-dev source-sha256=bf14d4fc970f5c2a6826a70dddbf5c7442e071db05b02814fdf1fa16a0e4ed7a -->
# Référence

| Type | Rôle |
|---|---|
| `Quests` | Acceptation, abandon, état, tableau, journal des quêtes accomplies, exportation, effacement |
| `QuestProjection` | L'activité transformée en progression, quêtes accomplies, enchaînements, duels départagés, annulations, reconstructions |
| `QuestObserver`, `QuestCompleted` | Averti des quêtes accomplies avec leurs récompenses, des duels gagnés et expirés |
| `Evaluator` | Le catalogue, les magasins, et comment la progression d'un sujet est lue |
| `Quest`, `QuestKind`, `Admission` | Une quête ; son genre ; comment un membre en vient à la suivre |
| `Step`, `Scope`, `StepMeasure` | Une étape ; les activités qu'elle lit ; ce qu'elle compte |
| `Reward` | Une récompense déclarée |
| `QuestReader` | Une quête à partir de valeurs simples |
| `Catalog`, `WritableCatalog`, `InMemoryCatalog`, `Chains` | Les quêtes d'une application ; leurs enchaînements vérifiés |
| `Contribution`, `Enrolment`, `Completion` | Ce qu'une activité a apporté ; la décision d'un membre ; une quête accomplie |
| `QuestState`, `QuestStatus` | Où en est une quête pour un membre |
| `Window` | Un intervalle de temps |
| `Erasure` | Ce qu'effacer un membre a retiré |
| `Names` | Les codes des quêtes, des étapes et des genres de récompenses |
| `Schedule\Schedule`, `Schedule\Occurrence` | Quand une quête est ouverte ; une de ses ouvertures |
| `Schedule\Permanent`, `Schedule\Dated` | Ouverte en tout temps ; ouverte entre deux dates |
| `Schedule\ScheduleReader` | Un calendrier propre à l'application, lu dans une définition |
| `Progress\ProgressCalculator`, `Progress\QuestProgress`, `Progress\StepProgress`, `Progress\Finish` | Lire la progression ; celle d'une quête ; celle d'une étape ; quand elle a été accomplie |
| `Duel\Duels`, `Duel\Duel`, `Duel\DuelStatus`, `Duel\DuelStanding` | Les défis entre membres ; un duel ; où il en est ; où en est chaque côté |
| `Store\ContributionStore`, `Store\InMemoryContributionStore` | Où les contributions sont gardées |
| `Store\EnrolmentStore`, `Store\InMemoryEnrolmentStore` | Où les inscriptions sont gardées |
| `Store\CompletionStore`, `Store\InMemoryCompletionStore` | Où le journal des quêtes accomplies est gardé |
| `Store\DuelStore`, `Store\InMemoryDuelStore` | Où les duels sont gardés |
| `Exception\QuestsException` et ses quatre exceptions | [Exceptions](exceptions.md) |
