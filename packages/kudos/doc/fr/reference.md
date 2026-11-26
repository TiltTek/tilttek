<!-- phil-dev source-sha256=2cbbe68f53a5387f93db431c164273ce7d308829354d35f7589f630a1990edba -->
# Référence

Chaque type public du paquet, avec son rôle.

| Type | Rôle |
|---|---|
| `Kudos` | Donne, refuse, retire et annule des remerciements ; lit ce qu'un membre a reçu et donné ; compte ce qu'il a donné |
| `Message` | Un message fixe : nom, clé de traduction et texte anglais |
| `Messages` | La liste fermée des messages, ses valeurs par défaut et celle de l'application |
| `Limits` | Le plafond quotidien, et la limite par destinataire sur une période |
| `Refusal` | Pourquoi un remerciement est refusé : soi-même, refusé, plafond quotidien, limite par destinataire |
| `Given` | Les remerciements donnés, et si cet appel les a donnés |
| `Thanks` | Des remerciements tels que leur donneur ou leur destinataire les lit |
| `ThanksPage` | Une page de remerciements, et la position après laquelle lire la suivante |
| `KudosGiven` | L'événement d'un remerciement donné, identifiants seulement |
| `KudosCancelled` | L'événement d'un remerciement retiré ou annulé, identifiants seulement |
| `Contract\KudosPreferences` | Si un destinataire accepte les remerciements d'un donneur |
| `Contract\MemberTimeZones` | Le fuseau horaire dans lequel la journée d'un membre est comptée |
| `Contract\KudosObserver` | Informé une seule fois des remerciements donnés et annulés |
| `Preferences\AcceptAll` | Chaque membre accepte les remerciements de tout le monde |
| `TimeZone\SiteTimeZone` | La journée de chaque membre dans le fuseau horaire du site |
| `Exception\KudosException` et ses trois exceptions | [Exceptions](exceptions.md) |
