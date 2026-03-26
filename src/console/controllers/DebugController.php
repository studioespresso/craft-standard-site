<?php

namespace studioespresso\standardsite\console\controllers;

use Craft;
use craft\console\Controller;
use craft\elements\Entry;
use craft\helpers\Console;
use studioespresso\standardsite\StandardSite;
use studioespresso\standardsite\transformers\DocumentTransformer;
use yii\console\ExitCode;

class DebugController extends Controller
{
    public $defaultAction = 'index';

    public ?string $site = null;

    public function options($actionID): array
    {
        return array_merge(parent::options($actionID), ['site']);
    }

    public function actionIndex(int $entryId): int
    {
        $siteHandle = $this->site;

        if ($siteHandle) {
            $site = Craft::$app->getSites()->getSiteByHandle($siteHandle);
            if (!$site) {
                $this->stderr("Site \"{$siteHandle}\" not found.\n", Console::FG_RED);
                return ExitCode::UNSPECIFIED_ERROR;
            }
            $siteId = $site->id;
        } else {
            $siteId = Craft::$app->getSites()->getPrimarySite()->id;
        }

        $entry = Entry::find()
            ->id($entryId)
            ->siteId($siteId)
            ->status(null)
            ->one();

        if (!$entry) {
            $this->stderr("Entry #{$entryId} not found.\n", Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $this->stdout("Entry: {$entry->title} (#{$entry->id})\n", Console::FG_CYAN);
        $this->stdout("Site: {$entry->getSite()->name}\n", Console::FG_CYAN);
        $this->stdout("Section: {$entry->getSection()->name}\n", Console::FG_CYAN);
        $this->stdout("\n");

        $transformer = new DocumentTransformer();
        $transformer->dryRun = true;
        $record = $transformer->transform($entry);

        $this->stdout("site.standard.document record:\n", Console::FG_GREEN);
        $this->stdout(json_encode($record, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n");

        return ExitCode::OK;
    }
}
