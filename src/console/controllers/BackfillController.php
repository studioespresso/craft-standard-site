<?php

namespace studioespresso\standardsite\console\controllers;

use Craft;
use craft\console\Controller;
use craft\elements\Entry;
use craft\helpers\Console;
use studioespresso\standardsite\records\PublicationRecord;
use studioespresso\standardsite\StandardSite;
use yii\console\ExitCode;

class BackfillController extends Controller
{
    public $defaultAction = 'index';

    public function actionIndex(string $siteHandle): int
    {
        $plugin = StandardSite::getInstance();

        // Resolve site handle
        $site = Craft::$app->getSites()->getSiteByHandle($siteHandle);
        if (!$site) {
            $this->stderr("Site \"{$siteHandle}\" not found.\n", Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }

        // Check connection
        $plugin->connection->setActiveSiteUid($site->uid);
        if (!$plugin->connection->isConnected($site->uid)) {
            $this->stderr("Not connected to AT Protocol for site \"{$siteHandle}\". Connect first via the CP.\n", Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $settings = $plugin->getSettings();
        $siteSettings = $settings->getSiteSettings($site->uid);

        // Check publication record
        if (!PublicationRecord::getAtUri($site->uid)) {
            $this->stderr("No publication record for site \"{$siteHandle}\". Create one from the Standard.site CP page first.\n", Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }

        // Check enabled sections
        if (empty($siteSettings->enabledSections)) {
            $this->stderr("No sections enabled for site \"{$siteHandle}\". Enable sections in plugin settings first.\n", Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }

        // Get section IDs from UIDs
        $sectionIds = [];
        foreach ($siteSettings->enabledSections as $sectionUid) {
            $section = Craft::$app->getEntries()->getSectionByUid($sectionUid);
            if ($section) {
                $sectionIds[] = $section->id;
            }
        }

        if (empty($sectionIds)) {
            $this->stderr("None of the enabled sections could be resolved.\n", Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }

        // Query entries
        $entries = Entry::find()
            ->siteId($site->id)
            ->sectionId($sectionIds)
            ->status('live')
            ->orderBy('postDate ASC')
            ->all();

        $total = count($entries);
        $this->stdout("Found {$total} entries to process for site \"{$siteHandle}\".\n", Console::FG_CYAN);

        if ($total === 0) {
            $this->stdout("Nothing to do.\n", Console::FG_GREEN);
            return ExitCode::OK;
        }

        $synced = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($entries as $i => $entry) {
            $num = $i + 1;

            // Skip already published
            if ($plugin->publisher->isPublished($entry->id, $entry->siteId)) {
                $skipped++;
                $this->stdout("[{$num}/{$total}] Skipped (already synced): {$entry->title}\n", Console::FG_YELLOW);
                continue;
            }

            try {
                $plugin->publisher->publishEntry($entry);
                $synced++;
                $this->stdout("[{$num}/{$total}] Synced: {$entry->title}\n", Console::FG_GREEN);
            } catch (\Throwable $e) {
                $failed++;
                $this->stderr("[{$num}/{$total}] Failed: {$entry->title} — {$e->getMessage()}\n", Console::FG_RED);
            }
        }

        $this->stdout("\nBackfill complete: {$synced} synced, {$skipped} skipped, {$failed} failed.\n", Console::FG_CYAN);

        return $failed > 0 ? ExitCode::UNSPECIFIED_ERROR : ExitCode::OK;
    }
}
