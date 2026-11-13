<!-- phil-dev source-sha256=3ba301f1d269f4cfbe00a7bbf58fc6c20a69de90ebaf79991e6b06200d9eac8a -->
# Les cartons et leur germe

## Comment un carton est tiré

`CardDealer` détient le secret du serveur, d'au moins 32 octets. Le germe du carton `n` d'un jeu est

```text
HMAC-SHA256(secret, "tilttek-bingo" NUL game NUL n)
```

et il alimente le moteur `Xoshiro256StarStar` de PHP, avec lequel un `Random\Randomizer` choisit autant de cases que le
carton en contient et les mélange ; la case libre du centre est remise au milieu. Un moteur à germe et ces méthodes
donnent les mêmes résultats sur toutes les versions de PHP : le même secret, le même jeu et le même numéro de série
tirent donc toujours la même disposition.

Aucun germe ne vient du navigateur : sans le secret, personne ne peut deviner le carton d'un autre joueur ni choisir le
sien, et le serveur n'a besoin que du numéro de série pour tirer un carton de nouveau.

## Des cartons uniques

L'empreinte d'une disposition est le SHA-256 de ses cases dans l'ordre. Le dépôt refuse un carton dont l'empreinte est
déjà celle d'un autre carton du jeu ; `Bingo::deal()` saute alors ce numéro de série et tire le suivant, jusqu'à
32 fois. Une réserve à peine plus grande qu'un carton laisse peu de dispositions : un carton de 3 × 3 avec une case
libre au centre, tiré de 8 cases, en a 40 320.

## Vérifier un carton

Un carton enregistré garde sa disposition, pour la page qui le montre. Une réclamation et une vérification tirent la
disposition de nouveau à partir du germe et refusent un carton dont la disposition enregistrée diffère : un carton
modifié dans le dépôt, ou falsifié, ne gagne jamais. Un carton imprimé montre son
étiquette, son numéro de série et un code de vérification que le secret tire ; l'organisateur le vérifie avec
`Bingo::verifyLabel()`, qui refuse un code qui n'est pas celui du numéro de série avant la vérification de
`Bingo::verify($game, $serial)` ; voir [Cartons imprimés](printing.md).

Changer le secret change tous les cartons tirés ensuite, et tous les codes de vérification : gardez-le pendant toute la vie des jeux dont les cartons sont
vérifiés.
