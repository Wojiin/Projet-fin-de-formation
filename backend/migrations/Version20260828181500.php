<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260828181500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Retire des listes les matériels qui ne correspondent pas à la spécialité de leur chirurgien.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            DELETE lmm
            FROM liste_materiel_materiel lmm
            INNER JOIN liste_materiel lm ON lm.id = lmm.liste_materiel_id
            INNER JOIN chirurgien c ON c.id = lm.chirurgien_id
            INNER JOIN materiel m ON m.id = lmm.materiel_id
            WHERE c.specialite_id <> m.specialite_id
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->write('Les associations incompatibles supprimées ne peuvent pas être restaurées automatiquement.');
    }
}
