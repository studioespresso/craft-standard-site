<?php

namespace studioespresso\standardsite\events;

use craft\elements\Entry;
use yii\base\Event;

class TransformDocumentEvent extends Event
{
    public Entry $entry;

    public ?string $textContent = null;
    public ?string $htmlContent = null;
    public ?string $description = null;
    public ?array $tags = null;
    public ?array $coverImage = null;
}
