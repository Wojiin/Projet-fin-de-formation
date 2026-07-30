<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260723140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute la spécialité de repli « Sans spécialité ».';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("INSERT INTO specialite (intitule)
            SELECT 'Sans spécialité'
            WHERE NOT EXISTS (SELECT 1 FROM specialite WHERE intitule = 'Sans spécialité')");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DELETE s FROM specialite s
            WHERE s.intitule = 'Sans spécialité'
              AND NOT EXISTS (SELECT 1 FROM chirurgien c WHERE c.specialite_id = s.id)
              AND NOT EXISTS (SELECT 1 FROM materiel m WHERE m.specialite_id = s.id)
              AND NOT EXISTS (SELECT 1 FROM chirurgie_modele cm WHERE cm.specialite_id = s.id)");
    }
}
