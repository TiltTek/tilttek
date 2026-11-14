<!-- phil-dev source-sha256=f11d304d0297638883db88adb2a9eafe92f08a8b4f6a99d3a190e70ecee86718 -->
# Le journal dans la base de données

## Les tables

`tilttek_activity_entry`, une ligne par entrée :

| Colonne | Type | Contient |
|---|---|---|
| `position` | grand entier, auto-incrément, clé primaire | La position, jamais donnée deux fois |
| `kind` | chaîne (16) | `recorded` ou `cancelled` |
| `idempotency_key` | chaîne (255) | La clé ; unique avec `kind` |
| `member` | chaîne (320) | La référence du membre, `kind:identifier` |
| `verb` | chaîne (128) | Le verbe |
| `object` | chaîne (320), peut être nulle | La référence de l'objet |
| `quantity` | entier | La quantité |
| `context` | JSON | Le contexte |
| `occurred_at` | date et heure | Le moment du geste, en UTC |
| `recorded_at` | date et heure | Le moment où l'entrée a été écrite, en UTC |

Index : `tilttek_activity_entry_key` (unique : `idempotency_key`, `kind`), `tilttek_activity_entry_member`,
`tilttek_activity_entry_object`, `tilttek_activity_entry_verb` (`verb`, `occurred_at`).

`tilttek_activity_checkpoint` : `projection` (chaîne 64, clé primaire), `position` (grand entier), `updated_at`.

## Écrire une seule fois

Une entrée est insérée par une instruction qui n'écrit rien quand sa clé et sa sorte sont déjà présentes, sans
interrompre la transaction englobante :

| Plateforme | Instruction |
|---|---|
| PostgreSQL | `INSERT … ON CONFLICT (idempotency_key, kind) DO NOTHING` |
| MySQL, MariaDB | `INSERT IGNORE …` |
| SQLite | `INSERT OR IGNORE …` |
| Autres | Un `INSERT` simple, dont la violation d'unicité compte comme « déjà présente » |

L'entrée est ensuite relue : l'appelant reçoit la ligne gardée par la base de données, et sait si cet appel l'a écrite.
Sur les « autres » plateformes, une violation à l'intérieur d'une transaction peut l'interrompre, selon ce que décide
la plateforme.

## Dates et positions

Les dates sont écrites en UTC, à la seconde près, et relues en UTC. Les positions viennent de l'auto-incrément ;
`AUTOINCREMENT` de SQLite, l'identité de PostgreSQL et InnoDB ne donnent jamais un numéro deux fois. Une position est
prise à l'insertion de la ligne, avant la validation de sa transaction : deux transactions qui valident dans l'ordre
inverse de celui où elles ont pris leurs positions laissent, un moment, une position manquante sous une entrée visible.

La lecture attend un tel trou. `read()` ne remet aucune entrée au-delà, et `lastPosition()` est la position sous le
trou, jusqu'à ce que l'entrée au-dessus du trou ait été écrite il y a `gap_wait` (dix secondes par défaut) : la
transaction qui a pris la position manquante a alors validé, ou ne validera jamais (annulée, une clé en double insérée
par deux processus au même moment, un membre effacé). Un rattrapage ne fait donc jamais passer un point de reprise
au-delà d'une entrée pas encore validée. Les entrées sont lues depuis la fin du journal tant qu'elles sont plus jeunes
que `gap_wait` ; une entrée plus ancienne arrête la recherche.

- Une entrée derrière un trou attend le prochain rattrapage après l'attente : avec `projections: live`, l'entrée
  suivante, la même requête répétée, ou `tilttek:activity:catch-up` planifiée.
- Une transaction qui garde sa position plus longtemps que `gap_wait` est tout de même dépassée : une application qui
  a de telles transactions relève `gap_wait`, ou lance une reconstruction de la période.
- L'âge se mesure sur l'horloge du lecteur par rapport à `recorded_at`, écrit par l'horloge du rédacteur, à la seconde
  près : des serveurs dont les horloges s'écartent de plus d'une seconde ou deux relèvent `gap_wait` d'autant.
- Un geste enregistré de nouveau n'est pas inséré de nouveau : l'entrée déjà présente est lue d'abord, si bien qu'un
  doublon ne prend aucune position et ne laisse aucun trou.

## Lire

Les filtres sont écrits en SQL : le membre, l'objet et la période comparent des colonnes. Un motif de verbe avec un
préfixe compare le début du verbe par le `SUBSTRING` de la plateforme, qu'un trait de soulignement dans un verbe ne peut
pas tromper comme il le ferait avec un `LIKE`. Une ligne dont le contexte ou les colonnes ne se lisent pas — écrite à la
main, ou par une version plus ancienne — lève `UnexpectedValueException` avec la colonne en faute.
