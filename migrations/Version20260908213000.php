<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260908213000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add informational draw_date on edition_settings';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE edition_settings ADD draw_date DATE DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE edition_settings DROP draw_date');
    }
}
