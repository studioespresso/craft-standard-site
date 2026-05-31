<?php

namespace studioespresso\standardsite\migrations;

use Craft;
use craft\db\Migration;
use studioespresso\standardsite\models\Settings;
use studioespresso\standardsite\StandardSite;

/**
 * Publication appearance (name, description, icon, theme, discovery) moves from
 * plugin settings (project config) into the database, so it can be edited on
 * production and the icon asset is referenced per-environment.
 */
class m260531_000000_create_publication_settings extends Migration
{
    public function safeUp(): bool
    {
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

        $this->seedFromPluginSettings();

        return true;
    }

    /**
     * Copy existing publication appearance out of plugin settings into the DB.
     * The icon is intentionally not copied: its asset ID is environment-specific
     * and should be re-selected on each environment's publication screen.
     */
    private function seedFromPluginSettings(): void
    {
        /** @var Settings $settings */
        $settings = StandardSite::getInstance()->getSettings();

        foreach (Craft::$app->getSites()->getAllSites() as $site) {
            $siteSettings = $settings->getSiteSettings($site->uid);

            $hasData = $siteSettings->publicationName
                || $siteSettings->publicationDescription
                || $siteSettings->themeBackground
                || $siteSettings->themeForeground
                || $siteSettings->themeAccent
                || $siteSettings->themeAccentForeground;

            if (!$hasData) {
                continue;
            }

            $exists = (new \craft\db\Query())
                ->from('{{%standardsite_publicationsettings}}')
                ->where(['siteUid' => $site->uid])
                ->exists();

            if ($exists) {
                continue;
            }

            $this->insert('{{%standardsite_publicationsettings}}', [
                'siteUid' => $site->uid,
                'name' => $siteSettings->publicationName ?: null,
                'description' => $siteSettings->publicationDescription ?: null,
                'themeBackground' => $siteSettings->themeBackground ?: null,
                'themeForeground' => $siteSettings->themeForeground ?: null,
                'themeAccent' => $siteSettings->themeAccent ?: null,
                'themeAccentForeground' => $siteSettings->themeAccentForeground ?: null,
                'showInDiscover' => $siteSettings->showInDiscover,
            ]);
        }
    }

    public function safeDown(): bool
    {
        $this->dropTableIfExists('{{%standardsite_publicationsettings}}');
        return true;
    }
}
