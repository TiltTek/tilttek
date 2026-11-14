<!-- phil-dev source-sha256=fbe04cb616abcab196cd6ba433805599c46a5e4ecf1dd284c0207193aab15ec0 -->
# Célébrations

La fin d'une partie est un moment à célébrer : des confettis pour un jeu-questionnaire terminé, des feux d'artifice
pour un score parfait. La bibliothèque dit quand ; la page le joue, avec le composant de célébration du kit PHIL UX ou
tout autre.

## Les événements

| Événement | Quand | Porte |
|---|---|---|
| `Event\QuizCompleted` | Une partie est terminée, ou une session en direct est terminée, pour chacun de ses joueurs | `quiz`, `run`, `player`, `perfect`, `practice` |
| `Event\PerfectScore` | Après `QuizCompleted`, pour une partie où toutes les réponses sont bonnes et dans le temps ; à son rythme, une fois par jeu-questionnaire et par membre par défaut | `quiz`, `run`, `player`, `practice` |
| `Event\LiveQuizFinished` | Une session en direct est terminée, avant les événements de ses joueurs | `quiz`, `session` |
| `Event\TeamWon` | Une session en direct jouée par équipes est terminée, pour chaque équipe au premier rang avec un score supérieur à 0 | `quiz`, `session`, `team` |
| `Event\GuestInvited` | Une session en direct est terminée, pour chaque invité | `session`, `guest` |

`run` est la partie ou la session en direct. `practice` est vrai pour une partie à son rythme qui ne compte pas, le
membre ayant déjà joué le jeu-questionnaire (voir
[Les parties qui comptent, et l'entraînement](playing.md#les-parties-qui-comptent-et-lentraînement)) ; il est toujours
faux en direct. Un écouteur qui attribue des points ou un insigne laisse de côté l'entraînement. Chaque événement ne
porte que des identifiants, jamais un pseudonyme : un
écouteur qui envoie un message de Messenger, attribue des points ou pousse une mise à jour lit le reste dans les
dépôts. Les gestes d'une session en direct — `PlayerJoined`, `QuestionShown`, `QuestionClosed` — sont décrits dans
[Jouer](playing.md#en-direct).

## Le gabarit du résultat

`templates/result.html.twig` montre le résultat d'un joueur à partir d'un `View\ResultView` : le score sur le maximum
que donne le jeu-questionnaire, le rang d'une partie en direct et, dans une session jouée par équipes, le rang de
l'équipe du joueur. `SelfPacedQuizzes::result()` donne la vue de la partie d'un membre une fois qu'elle peut être
montrée ; `ResultView::ofPlay()` montre une partie telle qu'elle est, quelle que soit la règle, et sert aux écrans
propres à l'application. Enregistrez le dossier sous un espace de noms de
l'application :

```yaml
# config/packages/twig.yaml
twig:
    paths:
        '%kernel.project_dir%/vendor/tilttek/quiz/templates': TilttekQuiz
```

```php
$view = $selfPaced->result('play-7', 'member:44');   // or ResultView::ofLive($session, $quiz, $player)
```

Le bloc `summary` contient le score, et le bloc `celebration`, vide par défaut, n'est dessiné que pour une partie
terminée. Avec la célébration du kit :

```twig
{% embed '@TilttekQuiz/result.html.twig' with {result: view} %}
    {% block celebration %}
        <twig:Celebration message="{{ 'quiz.completed'|trans }}" :effect="result.perfect ? 'fireworks' : 'confetti'" :autoplay="true" />
    {% endblock %}
{% endembed %}
```

## Le contrôleur Stimulus

Le gabarit porte le contrôleur `tilttek--quiz--result`, tiré du `assets/package.json` du paquet. Une fois la partie
terminée, il envoie l'événement du DOM `quiz:completed` sur la fenêtre, puis `quiz:perfect` pour une partie parfaite, puis
`quiz:team-won` quand l'équipe du joueur est parmi celles que nomme `TeamWon` ; leur détail contient `quiz`, `run`,
`perfect`, `team`, vide sans équipes, et `practice`, vrai pour une partie d'entraînement. Il ne les envoie qu'une fois par partie dans un onglet du navigateur, pour que
le retour sur la page ne les rejoue pas ; là où le navigateur refuse le stockage, il les envoie chaque fois. La
recette du kit joue sur eux :

```twig
<twig:Celebration message="{{ 'quiz.perfect'|trans }}" effect="fireworks" data-action="quiz:perfect@window->celebration#play" />
<twig:Celebration message="{{ 'quiz.team_won'|trans }}" effect="confetti" data-action="quiz:team-won@window->celebration#play" />
```

La recette est celle du kit, employée telle quelle : la bibliothèque n'ajoute aucun composant à elle.


## Accessibilité

La célébration du kit dit son message dans une région dynamique polie, ne dessine rien là où le mouvement est réduit,
ne joue aucun son et dure quelques secondes au plus. Le score est du texte, dans des éléments `data` qu'un script peut
lire ; les mots qui l'entourent sont ceux de l'application, dans sa langue.
