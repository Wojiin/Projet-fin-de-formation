<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260723131419 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Crée le référentiel des spécialités et relie chirurgiens, matériels et modèles de chirurgie.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE specialite (id INT AUTO_INCREMENT NOT NULL, intitule VARCHAR(100) NOT NULL, UNIQUE INDEX uniq_specialite_intitule (intitule), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql("INSERT INTO specialite (intitule) VALUES ('Orthopédie'), ('Chirurgie viscérale et digestive'), ('Chirurgie générale'), ('Traumatologie'), ('Urologie')");

        $this->addSql('ALTER TABLE chirurgie_modele ADD specialite_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE chirurgien ADD specialite_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE materiel ADD specialite_id INT DEFAULT NULL');

        $this->addSql("UPDATE chirurgien SET specialite_id = CASE
            WHEN specialite = 'Orthopedie' THEN (SELECT id FROM specialite WHERE intitule = 'Orthopédie')
            WHEN specialite = 'Chirurgie viscerale' THEN (SELECT id FROM specialite WHERE intitule = 'Chirurgie viscérale et digestive')
            WHEN specialite = 'Chirurgie generale' THEN (SELECT id FROM specialite WHERE intitule = 'Chirurgie générale')
            WHEN specialite = 'Traumatologie' THEN (SELECT id FROM specialite WHERE intitule = 'Traumatologie')
            ELSE (SELECT id FROM specialite WHERE intitule = 'Urologie')
        END");
        $this->addSql("UPDATE chirurgie_modele SET specialite_id = CASE
            WHEN intitule IN ('Prothese totale de genou', 'Prothese totale de hanche', 'Arthroscopie epaule', 'Arthroscopie genou', 'Prothèse totale de genou', 'Prothèse totale de hanche', 'Arthroscopie de l’épaule', 'Arthroscopie du genou') THEN (SELECT id FROM specialite WHERE intitule = 'Orthopédie')
            WHEN intitule IN ('Appendicectomie', 'Cholecystectomie coelioscopique', 'Hernie inguinale', 'Hemicolectomie droite', 'Appendicectomie', 'Cholécystectomie cœlioscopique', 'Hémicolectomie droite') THEN (SELECT id FROM specialite WHERE intitule = 'Chirurgie viscérale et digestive')
            WHEN intitule IN ('Ligamentoplastie croise anterieur', 'Osteosynthese cheville', 'Osteosynthese poignet', 'Suture tendon achille', 'Ligamentoplastie du croisé antérieur', 'Ostéosynthèse de cheville', 'Ostéosynthèse de poignet', 'Suture du tendon d’Achille') THEN (SELECT id FROM specialite WHERE intitule = 'Traumatologie')
            WHEN intitule IN ('Résection transurétrale de prostate', 'Urétéroscopie', 'Néphrectomie', 'Prostatectomie') THEN (SELECT id FROM specialite WHERE intitule = 'Urologie')
            ELSE (SELECT id FROM specialite WHERE intitule = 'Chirurgie générale')
        END");
        $this->addSql("UPDATE materiel SET specialite_id = CASE MOD(id - 1, 5)
            WHEN 0 THEN (SELECT id FROM specialite WHERE intitule = 'Orthopédie')
            WHEN 1 THEN (SELECT id FROM specialite WHERE intitule = 'Chirurgie viscérale et digestive')
            WHEN 2 THEN (SELECT id FROM specialite WHERE intitule = 'Chirurgie générale')
            WHEN 3 THEN (SELECT id FROM specialite WHERE intitule = 'Traumatologie')
            ELSE (SELECT id FROM specialite WHERE intitule = 'Urologie')
        END");

        $this->addSql('ALTER TABLE chirurgie_modele MODIFY specialite_id INT NOT NULL');
        $this->addSql('ALTER TABLE chirurgien MODIFY specialite_id INT NOT NULL, DROP specialite');
        $this->addSql('ALTER TABLE materiel MODIFY specialite_id INT NOT NULL');

        $this->addSql('ALTER TABLE chirurgie_modele ADD CONSTRAINT FK_BCAC64B82195E0F0 FOREIGN KEY (specialite_id) REFERENCES specialite (id)');
        $this->addSql('CREATE INDEX IDX_BCAC64B82195E0F0 ON chirurgie_modele (specialite_id)');
        $this->addSql('ALTER TABLE chirurgien ADD CONSTRAINT FK_1384D5E2195E0F0 FOREIGN KEY (specialite_id) REFERENCES specialite (id)');
        $this->addSql('CREATE INDEX IDX_1384D5E2195E0F0 ON chirurgien (specialite_id)');
        $this->addSql('ALTER TABLE materiel ADD CONSTRAINT FK_18D2B0912195E0F0 FOREIGN KEY (specialite_id) REFERENCES specialite (id)');
        $this->addSql('CREATE INDEX IDX_18D2B0912195E0F0 ON materiel (specialite_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE chirurgien ADD specialite VARCHAR(150) DEFAULT NULL');
        $this->addSql('UPDATE chirurgien c INNER JOIN specialite s ON s.id = c.specialite_id SET c.specialite = s.intitule');
        $this->addSql('ALTER TABLE chirurgie_modele DROP FOREIGN KEY FK_BCAC64B82195E0F0');
        $this->addSql('DROP INDEX IDX_BCAC64B82195E0F0 ON chirurgie_modele');
        $this->addSql('ALTER TABLE chirurgie_modele DROP specialite_id');
        $this->addSql('ALTER TABLE chirurgien DROP FOREIGN KEY FK_1384D5E2195E0F0');
        $this->addSql('DROP INDEX IDX_1384D5E2195E0F0 ON chirurgien');
        $this->addSql('ALTER TABLE chirurgien DROP specialite_id');
        $this->addSql('ALTER TABLE materiel DROP FOREIGN KEY FK_18D2B0912195E0F0');
        $this->addSql('DROP INDEX IDX_18D2B0912195E0F0 ON materiel');
        $this->addSql('ALTER TABLE materiel DROP specialite_id');
        $this->addSql('DROP TABLE specialite');
    }
}
