<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20230912090617 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE client (id INT AUTO_INCREMENT NOT NULL, quartier_id INT DEFAULT NULL, nom VARCHAR(255) NOT NULL, prenom VARCHAR(255) DEFAULT NULL, adresse VARCHAR(255) DEFAULT NULL, compteur VARCHAR(255) DEFAULT NULL, code VARCHAR(255) DEFAULT NULL, INDEX IDX_C7440455DF1E57AB (quartier_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE client_bf (id INT AUTO_INCREMENT NOT NULL, quartier_id INT DEFAULT NULL, nom VARCHAR(255) DEFAULT NULL, prenom VARCHAR(255) DEFAULT NULL, INDEX IDX_1411D1C6DF1E57AB (quartier_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE quartier (id INT AUTO_INCREMENT NOT NULL, code VARCHAR(255) NOT NULL, nom VARCHAR(255) NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE releve (id INT AUTO_INCREMENT NOT NULL, client_id INT DEFAULT NULL, date_releve DATETIME NOT NULL, ancien_index VARCHAR(255) NOT NULL, nouvel_index VARCHAR(255) NOT NULL, date_ancien_index DATETIME DEFAULT NULL, mois INT NOT NULL, annee INT NOT NULL, facture_date_edition DATETIME DEFAULT NULL, pu VARCHAR(255) NOT NULL, pus VARCHAR(255) DEFAULT NULL, limite VARCHAR(255) DEFAULT NULL, INDEX IDX_DDABFF8319EB6921 (client_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE releve_bf (id INT AUTO_INCREMENT NOT NULL, client_id INT DEFAULT NULL, date_releve DATETIME NOT NULL, ancien_index VARCHAR(255) NOT NULL, nouvel_index VARCHAR(255) NOT NULL, date_ancien_index DATETIME DEFAULT NULL, mois INT NOT NULL, annee INT NOT NULL, facture_date_edition DATETIME DEFAULT NULL, facture_date_paiement DATETIME DEFAULT NULL, pu INT DEFAULT NULL, pu2 VARCHAR(255) DEFAULT NULL, limite VARCHAR(255) DEFAULT NULL, INDEX IDX_5AE85FC119EB6921 (client_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE client ADD CONSTRAINT FK_C7440455DF1E57AB FOREIGN KEY (quartier_id) REFERENCES quartier (id)');
        $this->addSql('ALTER TABLE client_bf ADD CONSTRAINT FK_1411D1C6DF1E57AB FOREIGN KEY (quartier_id) REFERENCES quartier (id)');
        $this->addSql('ALTER TABLE releve ADD CONSTRAINT FK_DDABFF8319EB6921 FOREIGN KEY (client_id) REFERENCES client (id)');
        $this->addSql('ALTER TABLE releve_bf ADD CONSTRAINT FK_5AE85FC119EB6921 FOREIGN KEY (client_id) REFERENCES client_bf (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE client DROP FOREIGN KEY FK_C7440455DF1E57AB');
        $this->addSql('ALTER TABLE client_bf DROP FOREIGN KEY FK_1411D1C6DF1E57AB');
        $this->addSql('ALTER TABLE releve DROP FOREIGN KEY FK_DDABFF8319EB6921');
        $this->addSql('ALTER TABLE releve_bf DROP FOREIGN KEY FK_5AE85FC119EB6921');
        $this->addSql('DROP TABLE client');
        $this->addSql('DROP TABLE client_bf');
        $this->addSql('DROP TABLE quartier');
        $this->addSql('DROP TABLE releve');
        $this->addSql('DROP TABLE releve_bf');
    }
}
