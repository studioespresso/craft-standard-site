<?php

namespace studioespresso\standardsite\controllers;

use Craft;
use craft\web\Controller;
use studioespresso\standardsite\helpers\Tid;
use studioespresso\standardsite\models\SiteSettings;
use studioespresso\standardsite\StandardSite;
use studioespresso\standardsite\transformers\PublicationTransformer;
use yii\web\Response;

class SettingsController extends Controller
{
    /**
     * Create or update the site.standard.publication record on the PDS.
     */
    public function actionSavePublication(): Response
    {
        $this->requireCpRequest();
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        $plugin = StandardSite::getInstance();
        $settings = $plugin->getSettings();

        $request = Craft::$app->getRequest();
        $siteUid = $request->getRequiredBodyParam('siteUid');
        $publicationName = $request->getBodyParam('publicationName', '');
        $publicationDescription = $request->getBodyParam('publicationDescription', '');

        // Get or create site settings
        $siteSettings = $settings->getSiteSettings($siteUid);
        $siteSettings->publicationName = $publicationName;
        $siteSettings->publicationDescription = $publicationDescription;

        // Resolve Craft site for this UID
        $site = null;
        foreach (Craft::$app->getSites()->getAllSites() as $s) {
            if ($s->uid === $siteUid) {
                $site = $s;
                break;
            }
        }

        if (!$site) {
            return $this->asJson(['success' => false, 'error' => 'Site not found']);
        }

        $transformer = new PublicationTransformer();
        $record = $transformer->transformForSite($site, $siteSettings);

        try {
            if ($siteSettings->publicationRkey) {
                $result = $plugin->api->putRecord(
                    $transformer->getCollection(),
                    $siteSettings->publicationRkey,
                    $record,
                );
            } else {
                $rkey = Tid::generate();
                $result = $plugin->api->createRecord(
                    $transformer->getCollection(),
                    $record,
                    $rkey,
                );
                $siteSettings->publicationRkey = $rkey;
            }

            $siteSettings->publicationAtUri = $result['uri'] ?? null;
            $siteSettings->publicationCid = $result['cid'] ?? null;

            // Save back to plugin settings
            $settings->setSiteSettings($siteUid, $siteSettings);
            Craft::$app->getPlugins()->savePluginSettings($plugin, $settings->toArray());

            return $this->asJson(['success' => true]);
        } catch (\Throwable $e) {
            return $this->asJson(['success' => false, 'error' => $e->getMessage()]);
        }
    }

}
