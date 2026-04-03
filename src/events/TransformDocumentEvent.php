<?php

namespace studioespresso\standardsite\events;

use craft\elements\Entry;
use yii\base\Event;

/**
 * Event fired during document transformation, allowing listeners to override extracted content.
 */
class TransformDocumentEvent extends Event
{
    public Entry $entry;

    public ?string $textContent = null;
    public ?string $htmlContent = null;
    public ?string $description = null;
    /** @var string[]|null */
    public ?array $tags = null;
    /** @var array<string, mixed>|null */
    public ?array $coverImage = null;
}
