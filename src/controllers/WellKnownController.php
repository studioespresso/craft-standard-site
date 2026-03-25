<?php

namespace studioespresso\standardsite\controllers;

use Craft;
use craft\web\Controller;
use studioespresso\standardsite\StandardSite;
use yii\web\Response;

/**
 * Serves /.well-known/site.standard.publication for Standard.site verification.
 */
class WellKnownController extends Controller
{
    protected array|int|bool $allowAnonymous = true;

    public function actionPublication(): Response
    {
        $settings = StandardSite::getInstance()->getSettings();
        $currentSite = Craft::$app->getSites()->getCurrentSite();
        $siteSettings = $settings->getSiteSettings($currentSite->uid);

        $atUri = $siteSettings->publicationAtUri ?? '';

        $response = Craft::$app->getResponse();
        $response->format = Response::FORMAT_RAW;
        $response->getHeaders()->set('Content-Type', 'text/plain; charset=utf-8');
        $response->data = $atUri;

        return $response;
    }
}
