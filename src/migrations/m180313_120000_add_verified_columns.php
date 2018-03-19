<?php

namespace putyourlightson\campaign\migrations;

use craft\db\Migration;
use craft\helpers\MigrationHelper;

/**
 * m180313_120000_add_verified_columns migration.
 */
class m180313_120000_add_verified_columns extends Migration
{
    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        if (!$this->db->columnExists('{{%campaign_contacts}}', 'verified')) {
            $this->addColumn('{{%campaign_contacts}}', 'verified', $this->dateTime()->after('bounced'));
        }

        if ($this->db->columnExists('{{%campaign_contacts_mailinglists}}', 'confirmed')) {
            MigrationHelper::renameColumn('{{%campaign_contacts_mailinglists}}', 'confirmed', 'verified', $this);
        }
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m180313_120000_add_verified_columns cannot be reverted.\n";

        return false;
    }
}
