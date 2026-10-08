<?php

declare(strict_types=1);

namespace KimaiPlugin\KimaiExpensesCommunityBundle\Migrations;

use App\Doctrine\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

final class Version20261008120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add receipt columns to expenses and a colour to expense categories';
    }

    public function up(Schema $schema): void
    {
        /*
         * The receipt file itself lives on disk (var/data/kimai-expenses-community/receipts);
         * the database only keeps the random stored name, the original name shown to
         * users, and the detected content type.
         */
        $this->addSql(
            <<<'SQL'
            ALTER TABLE kimai2_kimai_expenses_community
                ADD receipt_filename VARCHAR(64) DEFAULT NULL,
                ADD receipt_original_name VARCHAR(255) DEFAULT NULL,
                ADD receipt_mime_type VARCHAR(100) DEFAULT NULL
            SQL
        );

        /* Optional "#rrggbb" colour shown as a dot next to the category in lists. */
        $this->addSql(
            'ALTER TABLE kimai2_kimai_expenses_community_category ADD color VARCHAR(7) DEFAULT NULL'
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE kimai2_kimai_expenses_community_category DROP COLUMN color');

        $this->addSql(
            <<<'SQL'
            ALTER TABLE kimai2_kimai_expenses_community
                DROP COLUMN receipt_filename,
                DROP COLUMN receipt_original_name,
                DROP COLUMN receipt_mime_type
            SQL
        );
    }
}
