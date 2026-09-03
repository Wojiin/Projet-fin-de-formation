<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260827120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute le signalement du matériel absent dans les préparations.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE preparation_materiel ADD absent TINYINT(1) DEFAULT 0 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE preparation_materiel DROP absent');
    }
}
