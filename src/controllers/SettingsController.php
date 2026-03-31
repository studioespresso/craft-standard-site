<?php

namespace studioespresso\standardsite\controllers;

use Craft;
use craft\web\Controller;
use studioespresso\standardsite\helpers\Tid;
use studioespresso\standardsite\records\PublicationRecord;
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

        // Get site settings (from project config) for name/description
        $siteSettings = $settings->getSiteSettings($siteUid);
        $siteSettings->publicationName = $publicationName;
        $siteSettings->publicationDescription = $publicationDescription;

        // Resolve Craft site for this UID
        $site = Craft::$app->getSites()->getSiteByUid($siteUid);

        // Get existing publication record from DB
        $pubRecord = PublicationRecord::findBySiteUid($siteUid);

        $transformer = new PublicationTransformer();
        $record = $transformer->transformForSite($site, $siteSettings);

        try {
            if ($pubRecord) {
                $result = $plugin->api->putRecord(
                    $transformer->getCollection(),
                    $pubRecord->rkey,
                    $record,
                );
                $pubRecord->atUri = $result['uri'] ?? $pubRecord->atUri;
                $pubRecord->cid = $result['cid'] ?? null;
                $pubRecord->save();
            } else {
                $rkey = Tid::generate();
                $result = $plugin->api->createRecord(
                    $transformer->getCollection(),
                    $record,
                    $rkey,
                );
                $pubRecord = new PublicationRecord();
                $pubRecord->siteUid = $siteUid;
                $pubRecord->rkey = $rkey;
                $pubRecord->atUri = $result['uri'] ?? "at://{$plugin->connection->getDid()}/{$transformer->getCollection()}/{$rkey}";
                $pubRecord->cid = $result['cid'] ?? null;
                $pubRecord->save();
            }

            return $this->asJson(['success' => true]);
        } catch (\Throwable $e) {
            return $this->asJson(['success' => false, 'error' => $e->getMessage()]);
        }
    }
}
