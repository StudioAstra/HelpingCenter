<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260217201137 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE admin (id INT AUTO_INCREMENT NOT NULL, email VARCHAR(180) NOT NULL, roles JSON NOT NULL, password VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_880E0D76E7927C74 (email), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE article (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(255) NOT NULL, slug VARCHAR(255) NOT NULL, content JSON DEFAULT NULL, search_text LONGTEXT DEFAULT NULL, is_published TINYINT DEFAULT 0 NOT NULL, position INT DEFAULT 0 NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, published_at DATETIME DEFAULT NULL, subsection_id INT NOT NULL, INDEX IDX_23A0E6687B204D9 (subsection_id), FULLTEXT INDEX IDX_23A0E666091B2AE (search_text), UNIQUE INDEX UNIQ_ARTICLE_SUBSECTION_SLUG (subsection_id, slug), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE article_feedback (id INT AUTO_INCREMENT NOT NULL, is_helpful TINYINT NOT NULL, comment LONGTEXT DEFAULT NULL, ip_address VARCHAR(45) NOT NULL, created_at DATETIME NOT NULL, article_id INT NOT NULL, INDEX IDX_87C1B0337294869C (article_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE article_version (id INT AUTO_INCREMENT NOT NULL, content JSON NOT NULL, title VARCHAR(255) NOT NULL, version_number INT NOT NULL, created_by VARCHAR(180) NOT NULL, created_at DATETIME NOT NULL, article_id INT NOT NULL, INDEX IDX_52CE97747294869C (article_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE section (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(255) NOT NULL, slug VARCHAR(255) NOT NULL, description LONGTEXT DEFAULT NULL, icon VARCHAR(50) DEFAULT NULL, position INT DEFAULT 0 NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_2D737AEF989D9B62 (slug), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE subsection (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(255) NOT NULL, slug VARCHAR(255) NOT NULL, description LONGTEXT DEFAULT NULL, icon VARCHAR(50) DEFAULT NULL, position INT DEFAULT 0 NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, section_id INT NOT NULL, INDEX IDX_6611D220D823E37A (section_id), UNIQUE INDEX UNIQ_SUBSECTION_SECTION_SLUG (section_id, slug), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE article ADD CONSTRAINT FK_23A0E6687B204D9 FOREIGN KEY (subsection_id) REFERENCES subsection (id)');
        $this->addSql('ALTER TABLE article_feedback ADD CONSTRAINT FK_87C1B0337294869C FOREIGN KEY (article_id) REFERENCES article (id)');
        $this->addSql('ALTER TABLE article_version ADD CONSTRAINT FK_52CE97747294869C FOREIGN KEY (article_id) REFERENCES article (id)');
        $this->addSql('ALTER TABLE subsection ADD CONSTRAINT FK_6611D220D823E37A FOREIGN KEY (section_id) REFERENCES section (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE article DROP FOREIGN KEY FK_23A0E6687B204D9');
        $this->addSql('ALTER TABLE article_feedback DROP FOREIGN KEY FK_87C1B0337294869C');
        $this->addSql('ALTER TABLE article_version DROP FOREIGN KEY FK_52CE97747294869C');
        $this->addSql('ALTER TABLE subsection DROP FOREIGN KEY FK_6611D220D823E37A');
        $this->addSql('DROP TABLE admin');
        $this->addSql('DROP TABLE article');
        $this->addSql('DROP TABLE article_feedback');
        $this->addSql('DROP TABLE article_version');
        $this->addSql('DROP TABLE section');
        $this->addSql('DROP TABLE subsection');
    }
}
