<!-- phil-dev source-sha256=85b35eab50a5fea0e6759f836c4a72d41b79f6660a7a56c9754f22ce50706737 -->
# Mettre à niveau

## Le moment de la première réponse

`tilttek_table_safety_participation` gagne une colonne, `first_answered_at` : le moment de la première réponse d'un
participant à la table, que `tilttek/table-safety` 0.3 garde quand il change ses réponses. Les sommaires montrés aux
gens prennent les participations dans son ordre, pour que changer ses réponses ne fasse plus passer une personne d'un
groupe de réponses à un autre.

L'écouteur du schéma ajoute la colonne au schéma que l'ORM génère. Une migration l'ajoute à une table existante, la
remplit depuis `updated_at`, le meilleur moment connu pour les réponses déjà données, puis la rend obligatoire :

```sh
bin/console doctrine:migrations:diff
```

```php
// In the generated migration, the column is added nullable, filled, then made required.
public function up(Schema $schema): void
{
    $this->addSql('ALTER TABLE tilttek_table_safety_participation ADD first_answered_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
    $this->addSql('UPDATE tilttek_table_safety_participation SET first_answered_at = updated_at');
    $this->addSql('ALTER TABLE tilttek_table_safety_participation ALTER first_answered_at SET NOT NULL');
}
```

Ces instructions sont celles de PostgreSQL ; la migration que Doctrine génère pour une autre plateforme donne son propre
type et sa propre syntaxe pour la première et la dernière.
