<?php

declare(strict_types=1);

namespace KimaiPlugin\KimaiExpensesCommunityBundle\Migrations;

use App\Doctrine\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

final class Version20261009120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Expense categories: optional unit label, "ask for a quantity" and "price entered per expense"';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            <<<'SQL'
            ALTER TABLE kimai2_kimai_expenses_community_category
                MODIFY unit VARCHAR(30) DEFAULT NULL,
                ADD ask_quantity TINYINT(1) NOT NULL DEFAULT 0,
                ADD price_entered TINYINT(1) NOT NULL DEFAULT 0
            SQL
        );

        /*
         * Until now every category showed the quantity field and used a fixed rate.
         * Keep that behaviour for the categories that already exist (price_entered
         * stays 0); change them in Expenses > Categories where it does not fit,
         * for example a receipt category that needs no quantity.
         */
        $this->addSql('UPDATE kimai2_kimai_expenses_community_category SET ask_quantity = 1');
    }

    public function down(Schema $schema): void
    {
        $this->addSql("UPDATE kimai2_kimai_expenses_community_category SET unit = 'item' WHERE unit IS NULL");

        $this->addSql(
            <<<'SQL'
            ALTER TABLE kimai2_kimai_expenses_community_category
                MODIFY unit VARCHAR(30) NOT NULL,
                DROP COLUMN ask_quantity,
                DROP COLUMN price_entered
            SQL
        );
    }
}
