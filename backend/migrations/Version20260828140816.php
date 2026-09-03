<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260828140816 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Autorise une consigne technique composée uniquement d’une image.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE fiche_technique CHANGE description description LONGTEXT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql("UPDATE fiche_technique SET description = '' WHERE description IS NULL");
        $this->addSql('ALTER TABLE fiche_technique CHANGE description description LONGTEXT NOT NULL');
    }
}
