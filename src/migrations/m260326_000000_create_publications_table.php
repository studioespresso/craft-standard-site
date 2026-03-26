<?php

namespace studioespresso\standardsite\migrations;

use craft\db\Migration;

class m260326_000000_create_publications_table extends Migration
{
    public function safeUp(): bool
    {
        if ($this->db->tableExists('{{%standardsite_publications}}')) {
            return true;
        }

        $this->createTable('{{%standardsite_publications}}', [
            'id' => $this->primaryKey(),
            'siteUid' => $this->string(36)->notNull(),
            'rkey' => $this->string(255)->notNull(),
            'atUri' => $this->string(512)->notNull(),
            'cid' => $this->string(255)->null(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $this->createIndex(null, '{{%standardsite_publications}}', ['siteUid'], true);

        return true;
    }

    public function safeDown(): bool
    {
        $this->dropTableIfExists('{{%standardsite_publications}}');
        return true;
    }
}
