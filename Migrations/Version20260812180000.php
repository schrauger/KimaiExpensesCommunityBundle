<?php

declare(strict_types=1);

namespace KimaiPlugin\KimaiExpensesCommunityBundle\Migrations;

use App\Doctrine\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

final class Version20260812180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create mileage expense categories and expenses';
    }

    public function up(Schema $schema): void
    {
        /*
         * Expense categories define the default unit and price used
         * when users create expenses.
         */
        $this->addSql(
            <<<'SQL'
            CREATE TABLE kimai2_kimai_expenses_community_category (
                id INT AUTO_INCREMENT NOT NULL,
                name VARCHAR(100) NOT NULL,
                unit VARCHAR(30) NOT NULL,
                default_cost NUMERIC(12, 4) NOT NULL,
                visible TINYINT(1) NOT NULL,
                help_text LONGTEXT DEFAULT NULL,
                description LONGTEXT DEFAULT NULL,
                PRIMARY KEY(id),
                INDEX idx_kimai_expenses_community_category_name (name)
            ) DEFAULT CHARACTER SET utf8mb4
              COLLATE `utf8mb4_unicode_ci`
              ENGINE = InnoDB
            SQL
        );

        /*
         * Store all IDs using the same INT type as the corresponding
         * Kimai tables. This is important for MariaDB foreign-key
         * compatibility.
         */
        $this->addSql(
            <<<'SQL'
            CREATE TABLE kimai2_kimai_expenses_community (
                id INT AUTO_INCREMENT NOT NULL,
                date DATETIME NOT NULL,

                category_id INT NOT NULL,
                user_id INT NOT NULL,
                customer_id INT DEFAULT NULL,
                project_id INT DEFAULT NULL,
                activity_id INT DEFAULT NULL,

                quantity NUMERIC(12, 4) NOT NULL,
                cost NUMERIC(12, 4) NOT NULL,

                description LONGTEXT DEFAULT NULL,

                billable TINYINT(1) NOT NULL,
                exported TINYINT(1) NOT NULL,

                PRIMARY KEY(id),

                INDEX idx_kimai_expenses_community_date (date),
                INDEX idx_kimai_expenses_community_category (category_id),
                INDEX idx_kimai_expenses_community_user (user_id),
                INDEX idx_kimai_expenses_community_customer (customer_id),
                INDEX idx_kimai_expenses_community_project (project_id),
                INDEX idx_kimai_expenses_community_activity (activity_id),

                CONSTRAINT fk_kimai_expenses_community_category
                    FOREIGN KEY (category_id)
                    REFERENCES kimai2_kimai_expenses_community_category (id)
                    ON DELETE RESTRICT,

                CONSTRAINT fk_kimai_expenses_community_user
                    FOREIGN KEY (user_id)
                    REFERENCES kimai2_users (id)
                    ON DELETE RESTRICT,

                CONSTRAINT fk_kimai_expenses_community_customer
                    FOREIGN KEY (customer_id)
                    REFERENCES kimai2_customers (id)
                    ON DELETE SET NULL,

                CONSTRAINT fk_kimai_expenses_community_project
                    FOREIGN KEY (project_id)
                    REFERENCES kimai2_projects (id)
                    ON DELETE SET NULL,

                CONSTRAINT fk_kimai_expenses_community_activity
                    FOREIGN KEY (activity_id)
                    REFERENCES kimai2_activities (id)
                    ON DELETE SET NULL

            ) DEFAULT CHARACTER SET utf8mb4
              COLLATE `utf8mb4_unicode_ci`
              ENGINE = InnoDB
            SQL
        );

        /*
         * Create a useful default category for first-time setup.
         *
         * The category cost is intentionally just a starting value.
         * Change it to the mileage rate you actually use.
         */
        $this->addSql(
            <<<'SQL'
            INSERT INTO kimai2_kimai_expenses_community_category
                (
                    name,
                    unit,
                    default_cost,
                    visible,
                    help_text,
                    description
                )
            VALUES
                (
                    'Mileage',
                    'mile',
                    0.7000,
                    1,
                    'Enter the number of business miles driven.',
                    'Business mileage'
                )
            SQL
        );
    }

    public function down(Schema $schema): void
    {
        /*
         * Drop the expense table first because it has foreign keys
         * referencing the category table.
         */
        $this->addSql(
            'DROP TABLE IF EXISTS kimai2_kimai_expenses_community'
        );

        $this->addSql(
            'DROP TABLE IF EXISTS kimai2_kimai_expenses_community_category'
        );
    }
}
