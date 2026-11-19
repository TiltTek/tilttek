<!-- phil-dev source-sha256=265858b6177a90820aec4bdeb52703b6d2ef97d7bd4e136323ee8fdb34079b54 -->
# Effacer un membre

Un registre ne fait que grandir, pour que la vérification s'additionne ; un membre qui part a pourtant le droit d'être
oublié. La bibliothèque concilie les deux par la pseudonymisation : `LuckTokens::eraseHolder()` écrit un pseudonyme à la
place du membre, comme détenteur et comme acteur, dans chaque entrée de chaque table, et laisse les positions, les
changements, les soldes et l'ordre tels qu'ils étaient. Le pseudonyme est `erased:` et 32 chiffres hexadécimaux
aléatoires, tirés pour cet effacement seul : rien ne le rattache au compte, ni à un autre membre effacé. `HolderErased`
est réparti avec le pseudonyme et le nombre d'entrées réécrites. Un effacement envoyé de nouveau ne trouve aucune entrée
qui nomme le membre et ne fait rien.

Dans la base de données, `DbalTokenLedger` l'exécute dans une seule transaction : un compte des entrées qui nomment le
membre, une mise à jour de la colonne du détenteur, une mise à jour de la colonne de l'acteur, chacune par son index.

Ce qui reste : les raisons, telles qu'elles ont été saisies — évitez-y les noms, ou offrez des raisons à choisir dans
une liste —, et les meneurs de jeu nommés dans les règles d'une table, que l'application change avec le reste des
réglages de la table.

## Par `phil/privacy-bundle`

Quand le bundle de vie privée est installé, le bundle enregistre `LuckTokensPrivacy`, étiqueté comme effaceur du module
`tilttek_luck_tokens`. Une demande d'effacement du sujet de données `44` efface le membre `member:44`, ou de la sorte que
nomme `privacy.member_kind`, sous le verrou du membre quand les verrous sont activés. Le rapport nomme
`tilttek_luck_tokens_entry.holder` et `tilttek_luck_tokens_entry.actor` comme pseudonymisées.

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
