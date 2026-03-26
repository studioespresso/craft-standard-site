<?php

namespace studioespresso\standardsite\controllers;

use Craft;
use craft\elements\Entry;
use craft\helpers\UrlHelper;
use craft\web\Controller;
use studioespresso\standardsite\StandardSite;
use yii\web\Response;

class EntryController extends Controller
{
    public function actionPublish(): Response
    {
        $this->requireCpRequest();

        $entryId = Craft::$app->getRequest()->getRequiredParam('entryId');
        $siteId = Craft::$app->getRequest()->getRequiredParam('siteId');

        $entry = Entry::find()
            ->id($entryId)
            ->siteId($siteId)
            ->status(null)
            ->one();

        if (!$entry) {
            Craft::$app->getSession()->setError('Entry not found');
            return $this->redirect(Craft::$app->getRequest()->getReferrer());
        }

        try {
            StandardSite::getInstance()->publisher->publishEntry($entry);
            Craft::$app->getSession()->setNotice('Published to AT Protocol');
        } catch (\Throwable $e) {
            Craft::$app->getSession()->setError("Publish failed: {$e->getMessage()}");
        }

        return $this->redirect($entry->getCpEditUrl());
    }

    public function actionUnpublish(): Response
    {
        $this->requireCpRequest();

        $entryId = Craft::$app->getRequest()->getRequiredParam('entryId');
        $siteId = Craft::$app->getRequest()->getRequiredParam('siteId');

        $entry = Entry::find()
            ->id($entryId)
            ->siteId($siteId)
            ->status(null)
            ->one();

        if (!$entry) {
            Craft::$app->getSession()->setError('Entry not found');
            return $this->redirect(Craft::$app->getRequest()->getReferrer());
        }

        try {
            StandardSite::getInstance()->publisher->unpublishEntry($entry);
            Craft::$app->getSession()->setNotice('Unpublished from AT Protocol');
        } catch (\Throwable $e) {
            Craft::$app->getSession()->setError("Unpublish failed: {$e->getMessage()}");
        }

        return $this->redirect($entry->getCpEditUrl());
    }
}
