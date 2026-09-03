<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260826131334 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute la traçabilité de création et de modification des chirurgies planifiées.';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE chirurgie_planifiee ADD cree_le DATETIME DEFAULT NULL, ADD cree_par VARCHAR(180) DEFAULT NULL, ADD modifie_le DATETIME DEFAULT NULL, ADD modifie_par VARCHAR(180) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE chirurgie_planifiee DROP cree_le, DROP cree_par, DROP modifie_le, DROP modifie_par');
    }
}
