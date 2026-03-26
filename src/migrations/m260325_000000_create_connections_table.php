<?php

namespace studioespresso\standardsite\migrations;

use craft\db\Migration;

class m260325_000000_create_connections_table extends Migration
{
    public function safeUp(): bool
    {
        if ($this->db->tableExists('{{%standardsite_connections}}')) {
            return true;
        }

        $this->createTable('{{%standardsite_connections}}', [
            'id' => $this->primaryKey(),
            'handle' => $this->string(255)->notNull(),
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

        return true;
    }

    public function safeDown(): bool
    {
        $this->dropTableIfExists('{{%standardsite_connections}}');
        return true;
    }
}
