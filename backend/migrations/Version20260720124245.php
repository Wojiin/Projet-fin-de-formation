<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260720124245 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Création des entités métier et relations Doctrine de ChirOrg.';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE chirurgie_modele (id INT AUTO_INCREMENT NOT NULL, intitule VARCHAR(150) NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE chirurgie_planifiee (id INT AUTO_INCREMENT NOT NULL, date_programmee DATETIME NOT NULL, salle VARCHAR(50) NOT NULL, ordre INT DEFAULT NULL, valide TINYINT DEFAULT 0 NOT NULL, valide_le DATETIME DEFAULT NULL, chirurgien_id INT NOT NULL, chirurgie_modele_id INT NOT NULL, valide_par_id INT DEFAULT NULL, INDEX IDX_B90390C36DB64F5D (chirurgien_id), INDEX IDX_B90390C373FD2B44 (chirurgie_modele_id), INDEX IDX_B90390C36AF12ED9 (valide_par_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE chirurgien (id INT AUTO_INCREMENT NOT NULL, prenom VARCHAR(100) NOT NULL, nom VARCHAR(100) NOT NULL, specialite VARCHAR(150) DEFAULT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE fiche_technique (id INT AUTO_INCREMENT NOT NULL, titre VARCHAR(150) NOT NULL, description LONGTEXT NOT NULL, lien_image VARCHAR(255) DEFAULT NULL, ordre INT NOT NULL, chirurgie_modele_id INT NOT NULL, INDEX IDX_505525A973FD2B44 (chirurgie_modele_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE liste_materiel (id INT AUTO_INCREMENT NOT NULL, intitule VARCHAR(150) NOT NULL, chirurgien_id INT NOT NULL, chirurgie_modele_id INT NOT NULL, INDEX IDX_40FEB6B6DB64F5D (chirurgien_id), INDEX IDX_40FEB6B73FD2B44 (chirurgie_modele_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE liste_materiel_materiel (liste_materiel_id INT NOT NULL, materiel_id INT NOT NULL, INDEX IDX_5B47E140FF6D643 (liste_materiel_id), INDEX IDX_5B47E14016880AAF (materiel_id), PRIMARY KEY (liste_materiel_id, materiel_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE materiel (id INT AUTO_INCREMENT NOT NULL, intitule VARCHAR(150) NOT NULL, adresse VARCHAR(150) DEFAULT NULL, type_materiel VARCHAR(100) DEFAULT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE preparation_materiel (id INT AUTO_INCREMENT NOT NULL, coche TINYINT DEFAULT 0 NOT NULL, coche_le DATETIME DEFAULT NULL, chirurgie_planifiee_id INT NOT NULL, materiel_id INT NOT NULL, coche_par_id INT DEFAULT NULL, INDEX IDX_9066EE2B1DE03630 (chirurgie_planifiee_id), INDEX IDX_9066EE2B16880AAF (materiel_id), INDEX IDX_9066EE2B808E962E (coche_par_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE chirurgie_planifiee ADD CONSTRAINT FK_B90390C36DB64F5D FOREIGN KEY (chirurgien_id) REFERENCES chirurgien (id)');
        $this->addSql('ALTER TABLE chirurgie_planifiee ADD CONSTRAINT FK_B90390C373FD2B44 FOREIGN KEY (chirurgie_modele_id) REFERENCES chirurgie_modele (id)');
        $this->addSql('ALTER TABLE chirurgie_planifiee ADD CONSTRAINT FK_B90390C36AF12ED9 FOREIGN KEY (valide_par_id) REFERENCES user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE fiche_technique ADD CONSTRAINT FK_505525A973FD2B44 FOREIGN KEY (chirurgie_modele_id) REFERENCES chirurgie_modele (id)');
        $this->addSql('ALTER TABLE liste_materiel ADD CONSTRAINT FK_40FEB6B6DB64F5D FOREIGN KEY (chirurgien_id) REFERENCES chirurgien (id)');
        $this->addSql('ALTER TABLE liste_materiel ADD CONSTRAINT FK_40FEB6B73FD2B44 FOREIGN KEY (chirurgie_modele_id) REFERENCES chirurgie_modele (id)');
        $this->addSql('ALTER TABLE liste_materiel_materiel ADD CONSTRAINT FK_5B47E140FF6D643 FOREIGN KEY (liste_materiel_id) REFERENCES liste_materiel (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE liste_materiel_materiel ADD CONSTRAINT FK_5B47E14016880AAF FOREIGN KEY (materiel_id) REFERENCES materiel (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE preparation_materiel ADD CONSTRAINT FK_9066EE2B1DE03630 FOREIGN KEY (chirurgie_planifiee_id) REFERENCES chirurgie_planifiee (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE preparation_materiel ADD CONSTRAINT FK_9066EE2B16880AAF FOREIGN KEY (materiel_id) REFERENCES materiel (id)');
        $this->addSql('ALTER TABLE preparation_materiel ADD CONSTRAINT FK_9066EE2B808E962E FOREIGN KEY (coche_par_id) REFERENCES user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE refresh_token ADD user_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE refresh_token ADD CONSTRAINT FK_C74F2195A76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('CREATE INDEX IDX_C74F2195A76ED395 ON refresh_token (user_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE chirurgie_planifiee DROP FOREIGN KEY FK_B90390C36DB64F5D');
        $this->addSql('ALTER TABLE chirurgie_planifiee DROP FOREIGN KEY FK_B90390C373FD2B44');
        $this->addSql('ALTER TABLE chirurgie_planifiee DROP FOREIGN KEY FK_B90390C36AF12ED9');
        $this->addSql('ALTER TABLE fiche_technique DROP FOREIGN KEY FK_505525A973FD2B44');
        $this->addSql('ALTER TABLE liste_materiel DROP FOREIGN KEY FK_40FEB6B6DB64F5D');
        $this->addSql('ALTER TABLE liste_materiel DROP FOREIGN KEY FK_40FEB6B73FD2B44');
        $this->addSql('ALTER TABLE liste_materiel_materiel DROP FOREIGN KEY FK_5B47E140FF6D643');
        $this->addSql('ALTER TABLE liste_materiel_materiel DROP FOREIGN KEY FK_5B47E14016880AAF');
        $this->addSql('ALTER TABLE preparation_materiel DROP FOREIGN KEY FK_9066EE2B1DE03630');
        $this->addSql('ALTER TABLE preparation_materiel DROP FOREIGN KEY FK_9066EE2B16880AAF');
        $this->addSql('ALTER TABLE preparation_materiel DROP FOREIGN KEY FK_9066EE2B808E962E');
        $this->addSql('DROP TABLE chirurgie_modele');
        $this->addSql('DROP TABLE chirurgie_planifiee');
        $this->addSql('DROP TABLE chirurgien');
        $this->addSql('DROP TABLE fiche_technique');
        $this->addSql('DROP TABLE liste_materiel');
        $this->addSql('DROP TABLE liste_materiel_materiel');
        $this->addSql('DROP TABLE materiel');
        $this->addSql('DROP TABLE preparation_materiel');
        $this->addSql('ALTER TABLE refresh_token DROP FOREIGN KEY FK_C74F2195A76ED395');
        $this->addSql('DROP INDEX IDX_C74F2195A76ED395 ON refresh_token');
        $this->addSql('ALTER TABLE refresh_token DROP user_id');
    }
}
