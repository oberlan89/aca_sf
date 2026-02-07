<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260206233932 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("CREATE COLLATION IF NOT EXISTS und_ai_ci (PROVIDER = icu, LOCALE = 'und-u-ks-level1')");
        $this->addSql('ALTER TABLE unit ALTER COLUMN code TYPE text COLLATE "und_ai_ci" USING code::text');
        $this->addSql('ALTER TABLE unit ALTER COLUMN name TYPE text COLLATE "und_ai_ci" USING name::text');
        $this->addSql('ALTER TABLE subfondo ALTER COLUMN name TYPE text COLLATE "und_ai_ci" USING name::text');
        $this->addSql('ALTER TABLE "user" ALTER COLUMN email TYPE text COLLATE "und_ai_ci" USING email::text');
        $this->addSql('ALTER TABLE servant ALTER COLUMN first_name TYPE text COLLATE "und_ai_ci" USING first_name::text');
        $this->addSql('ALTER TABLE servant ALTER COLUMN last_name1 TYPE text COLLATE "und_ai_ci" USING last_name1::text');
        $this->addSql('ALTER TABLE servant ALTER COLUMN last_name2 TYPE text COLLATE "und_ai_ci" USING last_name2::text');
    }


    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE reset_password_request DROP CONSTRAINT FK_7CE748AA76ED395');
        $this->addSql('DROP TABLE reset_password_request');
    }
}
