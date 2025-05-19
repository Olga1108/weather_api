<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250518220310 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE TABLE subscriptions (id INT AUTO_INCREMENT NOT NULL, email VARCHAR(255) NOT NULL, city VARCHAR(255) NOT NULL, frequency VARCHAR(50) NOT NULL, confirmation_token VARCHAR(255) DEFAULT NULL, unsubscribe_token VARCHAR(255) DEFAULT NULL, is_confirmed TINYINT(1) NOT NULL, created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', updated_at DATETIME DEFAULT NULL, UNIQUE INDEX UNIQ_4778A01E7927C74 (email), UNIQUE INDEX UNIQ_4778A01C05FB297 (confirmation_token), UNIQUE INDEX UNIQ_4778A01E0674361 (unsubscribe_token), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            DROP TABLE subscriptions
        SQL);
    }
}
