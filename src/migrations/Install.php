<?php

namespace studioespresso\standardsite\migrations;

use craft\db\Migration;

class Install extends Migration
{
    public function safeUp(): bool
    {
        $this->createTable('{{%standardsite_records}}', [
            'id' => $this->primaryKey(),
            'entryId' => $this->integer()->notNull(),
            'siteId' => $this->integer()->notNull(),
            'collection' => $this->string(255)->notNull(),
            'rkey' => $this->string(255)->notNull(),
            'atUri' => $this->string(512)->notNull(),
            'cid' => $this->string(255)->null(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $this->createIndex(null, '{{%standardsite_records}}', ['entryId', 'siteId']);
        $this->createIndex(null, '{{%standardsite_records}}', ['entryId']);
        $this->createIndex(null, '{{%standardsite_records}}', ['collection']);

        $this->addForeignKey(null, '{{%standardsite_records}}', ['entryId'], '{{%elements}}', ['id'], 'CASCADE');
        $this->addForeignKey(null, '{{%standardsite_records}}', ['siteId'], '{{%sites}}', ['id'], 'CASCADE');

        return true;
    }

    public function safeDown(): bool
    {
        $this->dropTableIfExists('{{%standardsite_records}}');
        return true;
    }
}
