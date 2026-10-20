<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Delta after 20260907 + 20260908 (prod already applied those).
 * Easter egg / elf game + chat email digest column.
 */
final class Version20261020124600 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Easter egg, elf game columns, and message.email_notified_at';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE edition_settings ADD easter_egg_enabled TINYINT(1) DEFAULT 1 NOT NULL');
        $this->addSql('ALTER TABLE participant ADD elf_high_score INT DEFAULT 0 NOT NULL, ADD elf_high_score_at DATETIME DEFAULT NULL, ADD elf_game_session_token VARCHAR(64) DEFAULT NULL, ADD elf_game_started_at DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE message ADD email_notified_at DATETIME DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE edition_settings DROP easter_egg_enabled');
        $this->addSql('ALTER TABLE participant DROP elf_high_score, DROP elf_high_score_at, DROP elf_game_session_token, DROP elf_game_started_at');
        $this->addSql('ALTER TABLE message DROP email_notified_at');
    }
}
