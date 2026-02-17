<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260217213000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Allow articles to be attached directly to a section and make subsection optional.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE article ADD section_id INT DEFAULT NULL, CHANGE subsection_id subsection_id INT DEFAULT NULL');
        $this->addSql('UPDATE article a INNER JOIN subsection s ON a.subsection_id = s.id SET a.section_id = s.section_id WHERE a.section_id IS NULL');
        $this->addSql('ALTER TABLE article ADD CONSTRAINT FK_23A0E66A9F3A9A6 FOREIGN KEY (section_id) REFERENCES section (id)');
        $this->addSql('CREATE INDEX IDX_23A0E66A9F3A9A6 ON article (section_id)');
        $this->addSql('DROP INDEX UNIQ_ARTICLE_SUBSECTION_SLUG ON article');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_ARTICLE_LOCATION_SLUG ON article (section_id, subsection_id, slug)');
        $this->addSql('ALTER TABLE article CHANGE section_id section_id INT NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX UNIQ_ARTICLE_LOCATION_SLUG ON article');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_ARTICLE_SUBSECTION_SLUG ON article (subsection_id, slug)');
        $this->addSql('ALTER TABLE article DROP FOREIGN KEY FK_23A0E66A9F3A9A6');
        $this->addSql('DROP INDEX IDX_23A0E66A9F3A9A6 ON article');
        $this->addSql('ALTER TABLE article DROP section_id, CHANGE subsection_id subsection_id INT NOT NULL');
    }
}
