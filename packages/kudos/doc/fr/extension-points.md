<!-- phil-dev source-sha256=99dee14bf103e5ede8885c89b4a8be887ccb3d87aed54e504afa82c4b7235184 -->
# Points d'extension

Le paquet est en `0.x` : ses points d'extension sont stables seulement à partir de sa version 1.0. D'ici là, une
version mineure peut les changer, et son CHANGELOG dit comment.

| Point | Ce que l'application fournit | Valeur par défaut |
|---|---|---|
| `Messages` | Sa liste de messages | `Messages::defaults()` |
| `Limits` | Son plafond quotidien, et sa limite par destinataire sur une période | Cinq par jour, trois par destinataire en sept jours |
| `Contract\KudosPreferences` | Le choix des membres de recevoir ou non des remerciements | `Preferences\AcceptAll` |
| `Contract\MemberTimeZones` | Le fuseau horaire dans lequel la journée de chaque membre est comptée | `TimeZone\SiteTimeZone`, UTC |
| `Contract\KudosObserver` | Ce qu'il faut faire des remerciements donnés ou annulés | Aucune |

Les messages et les limites sont des valeurs. La bibliothèque ne garde aucun état entre deux appels ; les
implémentations que fournit l'application n'en gardent pas non plus.

## Messages

La liste par défaut contient six messages, chacun avec un nom, une clé de traduction et un texte anglais :

| Nom | Clé de traduction | Texte |
|---|---|---|
| `help` | `kudos.message.help` | Thanks for your help |
| `well_played` | `kudos.message.well_played` | Well played |
| `great_idea` | `kudos.message.great_idea` | Great idea |
| `welcome` | `kudos.message.welcome` | Welcome to the table |
| `good_game` | `kudos.message.good_game` | Good game |
| `great_session` | `kudos.message.great_session` | Great session |

Une application ajoute les siens, remplace un texte ou en retire :

```php
$messages = Messages::defaults()
    ->with(new Message('nice_map', 'Nice map'))
    ->without('welcome');
```

Un nom compte de 1 à 64 lettres minuscules, chiffres et `_`, et commence par une lettre ; un texte compte de 1 à 200
octets ; une liste contient au plus 100 messages. L'application traduit chaque message avec sa clé de traduction, et se
rabat sur le texte. Les remerciements gardent le nom du message : un message retiré de la liste reste lisible sur les
remerciements donnés avec lui, et ne peut plus être donné.

## Préférences

`KudosPreferences::accepts($receiver, $giver)` dit si le destinataire accepte les remerciements de ce donneur. Une
application garde le choix des membres — un réglage pour refuser tous les remerciements, une liste de membres dont ils
les refusent — et répond à partir de lui. `Preferences\AcceptAll` accepte tous les remerciements.

## Fuseaux horaires

`MemberTimeZones::of($member)` donne le fuseau horaire dans lequel la journée du membre est comptée : celui qu'il a
choisi, sinon celui du site. `TimeZone\SiteTimeZone` donne le fuseau du site à chaque membre.

## Observateurs

`KudosObserver::given()` et `cancelled()` sont informés une seule fois, après que le journal a écrit l'entrée, jamais
pour une requête répétée sous la même clé. Un observateur qui envoie une notification ou un message sur un bus porte
les identifiants de l'événement, jamais davantage.

## Le critère de badge

Les remerciements qu'un membre a donnés sont les activités du verbe `kudos.given` (`Kudos::VERB`) dont il est le
membre, sans les remerciements retirés. Un badge de `tilttek/badges` les lit avec un décompte sur ce verbe :

```php
use Tilttek\Badges\Criterion\Count;
use Tilttek\Badges\Criterion\Scope;

new Count(new Scope([Kudos::VERB]), 25); // have thanked other members 25 times
```

Hors de `tilttek/badges`, `Kudos::givenCount()` et le `totals()` du journal donnent le même décompte. Les
remerciements reçus ne sont pas une mesure : la bibliothèque ne donne aucun moyen de les compter, si bien qu'aucun badge
ne récompense la popularité.
