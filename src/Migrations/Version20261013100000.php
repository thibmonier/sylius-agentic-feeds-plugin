<?php

declare(strict_types=1);

namespace Tmonier\SyliusAgenticFeedsPlugin\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;
use Doctrine\Migrations\AbstractMigration;

/**
 * Written with the Schema API (not raw SQL) so that it runs on MySQL, MariaDB and PostgreSQL.
 */
final class Version20261013100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create the product feed tables (tmonier/sylius-agentic-feeds-plugin).';
    }

    public function up(Schema $schema): void
    {
        $feed = $schema->createTable('tmonier_agentic_feed');
        $feed->addColumn('id', Types::INTEGER, ['autoincrement' => true]);
        $feed->addColumn('channel_id', Types::INTEGER);
        $feed->addColumn('code', Types::STRING, ['length' => 64]);
        $feed->addColumn('name', Types::STRING, ['length' => 255]);
        $feed->addColumn('locale_code', Types::STRING, ['length' => 12]);
        $feed->addColumn('currency_code', Types::STRING, ['length' => 3]);
        $feed->addColumn('country_code', Types::STRING, ['length' => 2, 'notnull' => false]);
        $feed->addColumn('format', Types::STRING, ['length' => 32]);
        $feed->addColumn('access_token', Types::STRING, ['length' => 128, 'notnull' => false]);
        $feed->addColumn('enabled', Types::BOOLEAN);
        $feed->addColumn('last_generated_at', Types::DATETIME_MUTABLE, ['notnull' => false]);
        $feed->addColumn('last_status', Types::STRING, ['length' => 16]);
        $feed->addColumn('last_item_count', Types::INTEGER);
        $feed->addColumn('last_error_count', Types::INTEGER);
        $feed->addColumn('last_warning_count', Types::INTEGER);
        $feed->addColumn('last_duration_ms', Types::INTEGER, ['notnull' => false]);
        $feed->addColumn('last_failure', Types::TEXT, ['notnull' => false]);
        $feed->addColumn('stale_since', Types::DATETIME_MUTABLE, ['notnull' => false]);
        $feed->addColumn('created_at', Types::DATETIME_MUTABLE);
        $feed->addColumn('updated_at', Types::DATETIME_MUTABLE, ['notnull' => false]);
        $feed->setPrimaryKey(['id']);
        $feed->addUniqueIndex(['code'], 'UNIQ_2834110977153098');
        $feed->addIndex(['channel_id'], 'IDX_2834110972F5A1AA');
        $feed->addForeignKeyConstraint('sylius_channel', ['channel_id'], ['id'], ['onDelete' => 'CASCADE'], 'FK_TMONIER_AGENTIC_FEED_CHANNEL');

        $issue = $schema->createTable('tmonier_agentic_feed_issue');
        $issue->addColumn('id', Types::INTEGER, ['autoincrement' => true]);
        $issue->addColumn('feed_id', Types::INTEGER);
        $issue->addColumn('severity', Types::STRING, ['length' => 8]);
        $issue->addColumn('code', Types::STRING, ['length' => 64]);
        $issue->addColumn('field', Types::STRING, ['length' => 64, 'notnull' => false]);
        $issue->addColumn('item_id', Types::STRING, ['length' => 255, 'notnull' => false]);
        $issue->addColumn('product_code', Types::STRING, ['length' => 255, 'notnull' => false]);
        $issue->addColumn('parameters', Types::JSON);
        $issue->addColumn('created_at', Types::DATETIME_MUTABLE);
        $issue->setPrimaryKey(['id']);
        $issue->addIndex(['feed_id', 'severity'], 'idx_tmonier_agentic_feed_issue_severity');
        $issue->addIndex(['feed_id'], 'IDX_AB0D58F651A5BC03');
        $issue->addForeignKeyConstraint('tmonier_agentic_feed', ['feed_id'], ['id'], ['onDelete' => 'CASCADE'], 'FK_TMONIER_AGENTIC_FEED_ISSUE_FEED');
    }

    public function down(Schema $schema): void
    {
        $schema->dropTable('tmonier_agentic_feed_issue');
        $schema->dropTable('tmonier_agentic_feed');
    }
}
