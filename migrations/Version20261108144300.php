<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261108144300 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add assignment.gift_purchased_at for Santa gift purchased status';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE assignment ADD gift_purchased_at DATETIME DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE assignment DROP gift_purchased_at');
    }
}
