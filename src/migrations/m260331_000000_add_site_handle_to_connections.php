<?php

namespace studioespresso\standardsite\migrations;

use craft\db\Migration;

class m260331_000000_add_site_handle_to_connections extends Migration
{
    public function safeUp(): bool
    {
        if (!$this->db->columnExists('{{%standardsite_connections}}', 'siteHandle')) {
            $this->addColumn('{{%standardsite_connections}}', 'siteHandle', $this->string(255)->null()->after('handle'));
        }

        return true;
    }

    public function safeDown(): bool
    {
        $this->dropColumn('{{%standardsite_connections}}', 'siteHandle');
        return true;
    }
}
