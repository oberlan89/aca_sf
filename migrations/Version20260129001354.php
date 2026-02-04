<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260129001354 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE unit ADD parent_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE unit ALTER team_id DROP NOT NULL');
        $this->addSql('ALTER TABLE unit ADD CONSTRAINT FK_DCBB0C53727ACA70 FOREIGN KEY (parent_id) REFERENCES unit (id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_DCBB0C5377153098 ON unit (code)');
        $this->addSql('CREATE INDEX IDX_DCBB0C53727ACA70 ON unit (parent_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE unit DROP CONSTRAINT FK_DCBB0C53727ACA70');
        $this->addSql('DROP INDEX UNIQ_DCBB0C5377153098');
        $this->addSql('DROP INDEX IDX_DCBB0C53727ACA70');
        $this->addSql('ALTER TABLE unit DROP parent_id');
        $this->addSql('ALTER TABLE unit ALTER team_id SET NOT NULL');
    }
}
