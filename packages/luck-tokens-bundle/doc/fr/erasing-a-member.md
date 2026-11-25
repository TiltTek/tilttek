<!-- phil-dev source-sha256=6e7f1c99acd2349ce2ba646068d1c6160b056eea7cd65816bb7c3e0fb901d5c4 -->
# Effacer un membre

Un registre ne fait que grandir, pour que la vérification s'additionne ; un membre qui part a pourtant le droit d'être
oublié. L'effacement décide colonne par colonne :

| Colonne | Effet | Pourquoi |
|---|---|---|
| `tilttek_luck_tokens_entry.holder` | Pseudonymisée | Les entrées d'une table sont numérotées l'une après l'autre, et une opération peut relier deux détenteurs, comme quand un jeton est passé. Supprimer les entrées du membre laisserait des trous dans les positions et des demi-opérations dans le registre des autres, dont la vérification ne s'additionnerait plus. |
| `tilttek_luck_tokens_entry.actor` | Pseudonymisée | Une entrée que le membre a écrite comme meneur de jeu ou comme joueur appartient au registre d'un autre détenteur, qui en garde la vérification. |
| `tilttek_luck_tokens_entry.reason` | Supprimée, sur les entrées que le membre détient | Une raison est écrite au sujet du détenteur, et la vérification des soldes n'a besoin que des changements. La raison d'une entrée que le membre a écrite pour un autre détenteur reste : elle concerne ce détenteur. |
| `tilttek_luck_tokens_rules.game_masters` | Supprimée | Avec `rules: doctrine`, le membre est retiré des meneurs de jeu de chaque table : un pseudonyme ne donnerait rien à personne, et le reste des règles de chaque table demeure. |

Le pseudonyme est `erased:` et 32 chiffres hexadécimaux aléatoires, tirés pour cet effacement seul : rien ne le
rattache au compte, ni à un autre membre effacé. Les lignes restent des données personnelles malgré tout — les
identifiants d'opération, les sessions et les moments peuvent être recoupés avec les propres dossiers de l'application
—, si bien que le rapport dit `pseudonymised`, jamais `anonymised`. `LuckTokens::eraseHolder()` laisse les positions, les
changements, les soldes et l'ordre tels qu'ils étaient, et répartit `HolderErased` avec le pseudonyme et le nombre
d'entrées réécrites. Un effacement envoyé de nouveau ne trouve aucune entrée qui nomme le membre et ne fait rien.

Dans la base de données, `DbalTokenLedger` l'exécute dans une seule transaction : un compte des entrées qui nomment le
membre, une mise à jour de la colonne du détenteur avec la raison, une mise à jour de la colonne de l'acteur, chacune
par son index. `DbalTokenRules::removeGameMaster()` lit les règles des tables par pages de 500 et réécrit celles qui
nomment le membre.

Ce qui reste : les raisons que le membre a écrites pour d'autres, telles qu'elles ont été saisies — évitez-y les noms,
ou offrez des raisons à choisir dans une liste —, et, avec `rules: configuration`, les meneurs de jeu nommés dans la
configuration, que l'application change elle-même.

## Par `phil/privacy-bundle`

Quand le bundle de vie privée est installé, le bundle enregistre `LuckTokensPrivacy`, étiqueté comme effaceur du module
`tilttek_luck_tokens`. Une demande d'effacement du sujet de données `44` efface le membre `member:44`, ou de la sorte que
nomme `privacy.member_kind`, sous le verrou du membre quand les verrous sont activés. Le rapport nomme
`tilttek_luck_tokens_entry.holder` et `tilttek_luck_tokens_entry.actor` comme pseudonymisées,
`tilttek_luck_tokens_entry.reason` comme supprimée et, avec `rules: doctrine`, `tilttek_luck_tokens_rules.game_masters`
comme supprimée.

## Par le flux propre de l'application

Une application qui supprime ses membres à sa façon appelle l'effacement depuis un écouteur de son événement :

```php
use Phil\Resource\ResourceReference;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Tilttek\LuckTokens\LuckTokens;

#[AsEventListener]
final readonly class EraseTheTokensOfADeletedMember
{
    public function __construct(private LuckTokens $tokens) {}

    public function __invoke(MemberDeleted $deleted): void
    {
        $this->tokens->eraseHolder(ResourceReference::of('member', (string) $deleted->memberId));
    }
}
```

Avec les verrous activés, injectez plutôt `LockedLuckTokens`, dont `eraseHolder()` prend le verrou du membre, de sorte
qu'aucune de ses opérations ne soit à mi-chemin quand il est effacé. Effectuez l'effacement une fois le compte fermé :
une opération du membre qui commence après lui écrit de nouveau sa référence.
