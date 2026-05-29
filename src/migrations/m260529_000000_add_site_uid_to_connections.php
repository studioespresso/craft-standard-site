<?php

namespace studioespresso\standardsite\migrations;

use Craft;
use craft\db\Migration;
use craft\db\Query;

/**
 * Connections are now stored per Craft site. Add a siteUid column and backfill
 * any existing connection with a site UID derived from its stored site handle
 * (falling back to the primary site) so it remains usable.
 */
class m260529_000000_add_site_uid_to_connections extends Migration
{
    public function safeUp(): bool
    {
        if (!$this->db->columnExists('{{%standardsite_connections}}', 'siteUid')) {
            $this->addColumn('{{%standardsite_connections}}', 'siteUid', $this->string(36)->null()->after('id'));
        }

        $sites = Craft::$app->getSites();
        $primaryUid = $sites->getPrimarySite()->uid;

        $connections = (new Query())
            ->select(['id', 'siteHandle'])
            ->from('{{%standardsite_connections}}')
            ->where(['siteUid' => null])
            ->all();

        foreach ($connections as $connection) {
            $siteUid = $primaryUid;
            if (!empty($connection['siteHandle'])) {
                $site = $sites->getSiteByHandle($connection['siteHandle']);
                if ($site) {
                    $siteUid = $site->uid;
                }
            }
            $this->update('{{%standardsite_connections}}', ['siteUid' => $siteUid], ['id' => $connection['id']]);
        }

        return true;
    }

    public function safeDown(): bool
    {
        $this->dropColumn('{{%standardsite_connections}}', 'siteUid');
        return true;
    }
}
