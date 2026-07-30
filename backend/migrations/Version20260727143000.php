<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260727143000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Remplace la date et heure programmée des chirurgies par une date sans horaire.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE chirurgie_planifiee MODIFY date_programmee DATE NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE chirurgie_planifiee MODIFY date_programmee DATETIME NOT NULL');
    }
}
