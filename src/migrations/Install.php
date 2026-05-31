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

        if (!$this->db->tableExists('{{%standardsite_connections}}')) {
            $this->createTable('{{%standardsite_connections}}', [
                'id' => $this->primaryKey(),
                'siteUid' => $this->string(36)->null(),
                'handle' => $this->string(255)->notNull(),
                'siteHandle' => $this->string(255)->null(),
                'did' => $this->string(255)->notNull(),
                'pdsUrl' => $this->string(512)->notNull(),
                'accessToken' => $this->text()->null(),
                'refreshToken' => $this->text()->null(),
                'dpopKey' => $this->text()->null(),
                'tokenExpiresAt' => $this->integer()->null(),
                'dateCreated' => $this->dateTime()->notNull(),
                'dateUpdated' => $this->dateTime()->notNull(),
                'uid' => $this->uid(),
            ]);
        }

        if (!$this->db->tableExists('{{%standardsite_publications}}')) {
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
        }

        if (!$this->db->tableExists('{{%standardsite_publicationsettings}}')) {
            $this->createTable('{{%standardsite_publicationsettings}}', [
                'id' => $this->primaryKey(),
                'siteUid' => $this->string(36)->notNull(),
                'name' => $this->string(1000)->null(),
                'description' => $this->text()->null(),
                'iconAssetId' => $this->integer()->null(),
                'themeBackground' => $this->string(7)->null(),
                'themeForeground' => $this->string(7)->null(),
                'themeAccent' => $this->string(7)->null(),
                'themeAccentForeground' => $this->string(7)->null(),
                'showInDiscover' => $this->boolean()->defaultValue(true)->notNull(),
                'dateCreated' => $this->dateTime()->notNull(),
                'dateUpdated' => $this->dateTime()->notNull(),
                'uid' => $this->uid(),
            ]);
            $this->createIndex(null, '{{%standardsite_publicationsettings}}', ['siteUid'], true);
        }

        return true;
    }

    public function safeDown(): bool
    {
        $this->dropTableIfExists('{{%standardsite_records}}');
        $this->dropTableIfExists('{{%standardsite_connections}}');
        $this->dropTableIfExists('{{%standardsite_publications}}');
        $this->dropTableIfExists('{{%standardsite_publicationsettings}}');
        return true;
    }
}
