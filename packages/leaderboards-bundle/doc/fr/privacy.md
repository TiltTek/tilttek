<!-- phil-dev source-sha256=d24cf5c502dab9467b171d68810165255283e88746bed3a69e40b32d5037f714 -->
# Vie privée

Quand `phil/privacy-bundle` est installé, le bundle enregistre `LeaderboardsPrivacy`, module `tilttek_leaderboards`.
La personne concernée est un membre, référencé sous la forme `<member_kind>:<subject>`, soit `member:44` par défaut.

**L'exportation** contient le choix d'apparence du membre, ses scores par classement et par période, ses lignes dans
les instantanés, ses paliers et ses appartenances dans les ligues, et les premières places qu'il a prises ; elle est
vide pour un membre caché qui n'a rien.

**L'effacement** supprime les contributions et les scores du membre, ses lignes dans chaque instantané, son choix, ses
paliers, ses appartenances et ses premières places. Le rapport nomme les sept tables, chacune `Deleted`. Les autres membres gardent leurs
rangs dans les instantanés et leurs divisions ; les classements en cours remontent.

Personne ne paraît dans un classement sans l'avoir choisi (le `doc/appearance.md` de la bibliothèque dit ce que voit
chaque spectateur) : l'application demande à ses membres, dans leur profil, comment ils veulent paraître. Le bundle respecte ce choix partout où
il montre un membre : les gabarits écrivent « Un membre » pour un membre caché, `tilttek:leaderboards:show` un tiret, la
célébration ne parle qu'au membre lui-même, et `TopReached` porte le choix du membre pour qu'un écouteur qui le dit aux
autres ne le nomme que comme il l'a choisi ([Événements](events.md)).
