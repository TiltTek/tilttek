<!-- phil-dev source-sha256=88d451be99b536197e442e9c8b4dc4afe4d31c677ef387fa672784d36656d864 -->
# Mises à jour en direct

Les écrans d'une session en direct — celui de l'animateur, l'écran partagé, le téléphone de chaque joueur — suivent ses
gestes à mesure qu'ils se produisent. `Mercure\LiveSessionPublisher` les pousse par Mercure, sous forme de mises à jour
privées qui nomment la session et la position d'une question, jamais un joueur, un pseudonyme ni une réponse : chaque
écran qui reçoit une mise à jour lit auprès de l'application ce qu'il peut montrer, avec ses propres droits. C'est
facultatif, et cela exige `symfony/mercure` :

```sh
composer require symfony/mercure
```

## Brancher le publicateur

```php
use Tilttek\Quiz\Mercure\LiveSessionPublisher;

$publisher = new LiveSessionPublisher(
    $hub,                                   // Symfony\Component\Mercure\HubInterface
    'https://example.com/quiz/live/',       // the topic prefix; the session's identifier follows it
);
```

Chaque méthode écoute un événement de la bibliothèque :

| Méthode | Événement | Données de la mise à jour |
|---|---|---|
| `playerJoined()` | `PlayerJoined` | `{"type": "player-joined", "session": …}` |
| `questionShown()` | `QuestionShown` | `{"type": "question-shown", "session": …, "position": …}` |
| `questionClosed()` | `QuestionClosed` | `{"type": "question-closed", "session": …, "position": …}` |
| `liveQuizFinished()` | `LiveQuizFinished` | `{"type": "finished", "session": …}` |

Dans une application Symfony, enregistrez-les sur le répartiteur d'événements :

```yaml
# config/services.yaml
Tilttek\Quiz\Mercure\LiveSessionPublisher:
    arguments: ['@mercure.hub.default', '%env(QUIZ_TOPIC_PREFIX)%']
    tags:
        - { name: kernel.event_listener, event: Tilttek\Quiz\Event\PlayerJoined, method: playerJoined }
        - { name: kernel.event_listener, event: Tilttek\Quiz\Event\QuestionShown, method: questionShown }
        - { name: kernel.event_listener, event: Tilttek\Quiz\Event\QuestionClosed, method: questionClosed }
        - { name: kernel.event_listener, event: Tilttek\Quiz\Event\LiveQuizFinished, method: liveQuizFinished }
```

## S'abonner

`topic($session)` donne le sujet d'une session : le préfixe suivi de l'identifiant de la session, encodé pour une URL.
Les mises à jour sont privées : l'application donne à chaque écran un jeton d'abonné pour le sujet de sa session
seulement, par exemple avec la fonction `mercure()` de l'extension Twig de Symfony, une fois qu'elle a vérifié que le
membre ou l'invité s'est joint à cette session. Un écran qui reçoit `question-shown` va chercher la question auprès de
l'application et démarre sa minuterie à partir de l'échéance que donne l'application, jamais à partir du moment où la
mise à jour est arrivée.

Le publicateur ne garde rien entre deux appels : une seule instance sert toutes les requêtes d'un *worker*.
