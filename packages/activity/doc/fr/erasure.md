<!-- phil-dev source-sha256=6593e3f80ad5ed19b4ed0635cbaecacea5fd24cd254ab07729e36a9cd229b113 -->
# Effacement

```php
$erasure = $journal->erase(ResourceReference::of('member', '44'));
$erasure->deleted;   // entries of the member's own gestures, recorded or cancelled
$erasure->detached;  // entries of other members' gestures whose object was this member
```

Effacer un membre supprime toutes les entrées de ses gestes et l'oublie comme **objet** des gestes des autres membres
— un remerciement qui lui a été adressé, un duel livré contre lui — : ces entrées restent, sans objet, car elles sont
l'activité des autres membres et leurs récompenses en découlent.

Ce qu'un paquet a dérivé de l'activité du membre — points, badges, progression — est effacé par ce paquet ; le journal
ne répond que de lui-même. Les positions ne sont jamais redonnées après un effacement : le point de reprise d'une
projection reste donc valide.

`tilttek/activity-bundle` répond aux demandes d'export et d'effacement de `phil/privacy-bundle` avec cette méthode.
