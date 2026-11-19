# Upgrading

## The moment of the first answer

`tilttek_table_safety_participation` gains a column, `first_answered_at`: the moment of a participant's first answer
at the table, which `tilttek/table-safety` 0.3 keeps when they change their answers. The summaries shown to people
take the participations in its order, so that changing one's answers no longer moves a person from one group of
answers to another.

The schema listener adds the column to the schema the ORM generates. A migration adds it to an existing table, fills
it from `updated_at`, the best moment known for the answers already given, then makes it required:

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

The statements above are those of PostgreSQL; the migration Doctrine generates for another platform gives its own
type and syntax for the first and the last.
