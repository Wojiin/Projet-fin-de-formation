<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260720141101 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajout des contraintes d’unicité métier et des index de recherche ChirOrg.';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE INDEX idx_chirurgie_modele_intitule ON chirurgie_modele (intitule)');
        $this->addSql('CREATE INDEX idx_chirurgie_date ON chirurgie_planifiee (date_programmee)');
        $this->addSql('CREATE INDEX idx_chirurgie_salle ON chirurgie_planifiee (salle)');
        $this->addSql('CREATE INDEX idx_chirurgie_valide ON chirurgie_planifiee (valide)');
        $this->addSql('CREATE INDEX idx_chirurgien_nom ON chirurgien (nom)');
        $this->addSql('CREATE UNIQUE INDEX uniq_liste_chirurgien_modele ON liste_materiel (chirurgien_id, chirurgie_modele_id)');
        $this->addSql('CREATE INDEX idx_materiel_intitule ON materiel (intitule)');
        $this->addSql('CREATE INDEX idx_preparation_coche ON preparation_materiel (coche)');
        $this->addSql('CREATE UNIQUE INDEX uniq_preparation_chirurgie_materiel ON preparation_materiel (chirurgie_planifiee_id, materiel_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP INDEX idx_chirurgie_modele_intitule ON chirurgie_modele');
        $this->addSql('DROP INDEX idx_chirurgie_date ON chirurgie_planifiee');
        $this->addSql('DROP INDEX idx_chirurgie_salle ON chirurgie_planifiee');
        $this->addSql('DROP INDEX idx_chirurgie_valide ON chirurgie_planifiee');
        $this->addSql('DROP INDEX idx_chirurgien_nom ON chirurgien');
        $this->addSql('DROP INDEX uniq_liste_chirurgien_modele ON liste_materiel');
        $this->addSql('DROP INDEX idx_materiel_intitule ON materiel');
        $this->addSql('DROP INDEX idx_preparation_coche ON preparation_materiel');
        $this->addSql('DROP INDEX uniq_preparation_chirurgie_materiel ON preparation_materiel');
    }
}
