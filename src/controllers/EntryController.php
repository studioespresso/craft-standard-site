<?php

namespace studioespresso\standardsite\controllers;

use Craft;
use craft\elements\Entry;
use craft\web\Controller;
use studioespresso\standardsite\StandardSite;
use yii\web\Response;

class EntryController extends Controller
{
    public function actionPublish(): Response
    {
        $this->requirePostRequest();
        $this->requireCpRequest();

        $entryId = Craft::$app->getRequest()->getRequiredBodyParam('entryId');
        $siteId = Craft::$app->getRequest()->getRequiredBodyParam('siteId');

        $entry = Entry::find()
            ->id($entryId)
            ->siteId($siteId)
            ->status(null)
            ->one();

        if (!$entry) {
            return $this->asJson(['success' => false, 'error' => 'Entry not found']);
        }

        try {
            StandardSite::getInstance()->publisher->publishEntry($entry);
            return $this->asJson(['success' => true]);
        } catch (\Throwable $e) {
            return $this->asJson(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    public function actionUnpublish(): Response
    {
        $this->requirePostRequest();
        $this->requireCpRequest();

        $entryId = Craft::$app->getRequest()->getRequiredBodyParam('entryId');
        $siteId = Craft::$app->getRequest()->getRequiredBodyParam('siteId');

        $entry = Entry::find()
            ->id($entryId)
            ->siteId($siteId)
            ->status(null)
            ->one();

        if (!$entry) {
            return $this->asJson(['success' => false, 'error' => 'Entry not found']);
        }

        try {
            StandardSite::getInstance()->publisher->unpublishEntry($entry);
            return $this->asJson(['success' => true]);
        } catch (\Throwable $e) {
            return $this->asJson(['success' => false, 'error' => $e->getMessage()]);
        }
    }
}
