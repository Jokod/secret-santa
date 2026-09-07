<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260907213511 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE assignment (id INT AUTO_INCREMENT NOT NULL, santa_id INT NOT NULL, target_id INT NOT NULL, UNIQUE INDEX uniq_assignment_santa (santa_id), UNIQUE INDEX uniq_assignment_target (target_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE edition_settings (id INT NOT NULL, budget_max DOUBLE PRECISION NOT NULL, event_date DATE DEFAULT NULL, welcome_email_template LONGTEXT NOT NULL, result_email_template LONGTEXT NOT NULL, reminder_email_template LONGTEXT NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE exclusion (id INT AUTO_INCREMENT NOT NULL, source_id INT NOT NULL, target_id INT NOT NULL, UNIQUE INDEX uniq_exclusion_pair (source_id, target_id), INDEX IDX_DF1686C953C1C61 (source_id), INDEX IDX_DF1686C158E0B66 (target_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE message (id INT AUTO_INCREMENT NOT NULL, type VARCHAR(255) NOT NULL, body LONGTEXT NOT NULL, is_read TINYINT NOT NULL, created_at DATETIME NOT NULL, santa_id INT NOT NULL, target_id INT NOT NULL, INDEX IDX_B6BD307F4E0AAA2A (santa_id), INDEX IDX_B6BD307F158E0B66 (target_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE participant (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(120) NOT NULL, email VARCHAR(180) NOT NULL, token_secret VARCHAR(64) NOT NULL, UNIQUE INDEX uniq_participant_email (email), UNIQUE INDEX uniq_participant_name (name), UNIQUE INDEX uniq_participant_token (token_secret), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE `user` (id INT AUTO_INCREMENT NOT NULL, email VARCHAR(180) NOT NULL, roles JSON NOT NULL, password VARCHAR(255) NOT NULL, UNIQUE INDEX uniq_user_email (email), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE wish (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(180) NOT NULL, description LONGTEXT DEFAULT NULL, url VARCHAR(500) DEFAULT NULL, estimated_price DOUBLE PRECISION NOT NULL, preference_order INT NOT NULL, participant_id INT NOT NULL, INDEX IDX_D7D174C99D1C3019 (participant_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE messenger_messages (id BIGINT AUTO_INCREMENT NOT NULL, body LONGTEXT NOT NULL, headers LONGTEXT NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL, available_at DATETIME NOT NULL, delivered_at DATETIME DEFAULT NULL, INDEX IDX_75EA56E0FB7336F0E3BD61CE16BA31DBBF396750 (queue_name, available_at, delivered_at, id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE assignment ADD CONSTRAINT FK_30C544BA4E0AAA2A FOREIGN KEY (santa_id) REFERENCES participant (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE assignment ADD CONSTRAINT FK_30C544BA158E0B66 FOREIGN KEY (target_id) REFERENCES participant (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE exclusion ADD CONSTRAINT FK_DF1686C953C1C61 FOREIGN KEY (source_id) REFERENCES participant (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE exclusion ADD CONSTRAINT FK_DF1686C158E0B66 FOREIGN KEY (target_id) REFERENCES participant (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE message ADD CONSTRAINT FK_B6BD307F4E0AAA2A FOREIGN KEY (santa_id) REFERENCES participant (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE message ADD CONSTRAINT FK_B6BD307F158E0B66 FOREIGN KEY (target_id) REFERENCES participant (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE wish ADD CONSTRAINT FK_D7D174C99D1C3019 FOREIGN KEY (participant_id) REFERENCES participant (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE assignment DROP FOREIGN KEY FK_30C544BA4E0AAA2A');
        $this->addSql('ALTER TABLE assignment DROP FOREIGN KEY FK_30C544BA158E0B66');
        $this->addSql('ALTER TABLE exclusion DROP FOREIGN KEY FK_DF1686C953C1C61');
        $this->addSql('ALTER TABLE exclusion DROP FOREIGN KEY FK_DF1686C158E0B66');
        $this->addSql('ALTER TABLE message DROP FOREIGN KEY FK_B6BD307F4E0AAA2A');
        $this->addSql('ALTER TABLE message DROP FOREIGN KEY FK_B6BD307F158E0B66');
        $this->addSql('ALTER TABLE wish DROP FOREIGN KEY FK_D7D174C99D1C3019');
        $this->addSql('DROP TABLE assignment');
        $this->addSql('DROP TABLE edition_settings');
        $this->addSql('DROP TABLE exclusion');
        $this->addSql('DROP TABLE message');
        $this->addSql('DROP TABLE participant');
        $this->addSql('DROP TABLE `user`');
        $this->addSql('DROP TABLE wish');
        $this->addSql('DROP TABLE messenger_messages');
    }
}
