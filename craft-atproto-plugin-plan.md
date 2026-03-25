# Craft CMS ATProto / Standard.site Plugin — Research & Architecture Plan

> Reference implementation: [wordpress-atmosphere](https://github.com/Automattic/wordpress-atmosphere)  
> Protocol: [AT Protocol](https://atproto.com) · [Standard.site](https://standard.site)

---

## 1. What Is This Ecosystem?

### AT Protocol (ATProto)
The Authenticated Transfer Protocol is a federated, open protocol for social applications. Key concepts:

- **PDS (Personal Data Server)** — your account home, stores your data as signed records
- **DID (Decentralized Identifier)** — your portable, cryptographic identity (`did:plc:abc123`)
- **Repository** — a collection of typed records stored on your PDS
- **Lexicon** — JSON schema definitions for record types (like `app.bsky.feed.post`)
- **NSID** — namespaced schema IDs, e.g. `site.standard.document`
- **AT-URI** — record addresses: `at://did:plc:abc123/site.standard.document/rkey`

### Standard.site
A set of Lexicons for publishing long-form content on ATProto — not tied to Bluesky. Three core lexicons:

| Lexicon | Purpose |
|---|---|
| `site.standard.publication` | Represents your site/blog as a publication |
| `site.standard.document` | Represents individual posts/pages |
| `site.standard.graph.subscription` | Follower relationships between users and publications |

Records live on **your** PDS. Standard.site is just the schema — anyone can index it.

---

## 2. What the WordPress Plugin (ATmosphere) Does

The WP plugin is the best reference. Here's what it implements:

### Features
- **Native OAuth 2.1** with PKCE + DPoP (no proxy, no intermediary — tokens stay on your server)
- Creates a `site.standard.publication` record for your site
- On post publish: creates both a `site.standard.document` record and an `app.bsky.feed.post` (Bluesky cross-post)
- **Facet detection** (links, mentions, hashtags)
- **Per-post control** via meta box
- **Backfill** — bulk-sync existing posts
- Uses **atomic `applyWrites`** to create both record types in one request

### Architecture (WP)
```
includes/
├── oauth/
│   ├── class-client.php       # OAuth 2.1 with PKCE + DPoP
│   ├── class-dpop.php         # DPoP proof generation
│   ├── class-encryption.php   # Token storage encryption
│   ├── class-nonce-storage.php
│   └── class-resolver.php     # PDS / DID resolution
├── transformer/
│   ├── class-base.php
│   ├── class-document.php     # Entry → site.standard.document
│   ├── class-facet.php        # Link/mention/hashtag detection
│   ├── class-post.php         # Entry → app.bsky.feed.post
│   ├── class-publication.php  # Site → site.standard.publication
│   └── class-tid.php          # TID (timestamp-based record key) generation
├── wp-admin/                  # Settings UI, meta box, REST endpoint
├── class-api.php              # DPoP-authenticated PDS requests
├── class-atmosphere.php       # Plugin bootstrap
├── class-backfill.php         # Bulk sync
└── class-publisher.php        # Orchestrates atomic applyWrites
```

---

## 3. Craft CMS Plugin Architecture

### Plugin Name Suggestion: `craft-atmosphere` or `craft-atproto`

### Craft-Specific Considerations
- Craft uses **Yii2** framework — services, components, modules, events
- Settings stored via `craft\base\Model` + plugin settings
- Entry lifecycle hooks via **Events** (`Entry::EVENT_AFTER_SAVE`, etc.)
- Control Panel UI via **Twig templates** + custom CP routes
- Field customization via **Field Layouts** — no need for a separate meta box pattern
- Background tasks → **Queue** (`craft\queue\Queue`)
- Secrets/tokens → store encrypted in project config or DB

---

### Directory Structure

```
src/
├── AtProto.php                  # Main plugin class (extends craft\base\Plugin)
├── models/
│   ├── Settings.php             # Plugin settings model (PDS URL, tokens, publication AT-URI)
│   └── PublicationRecord.php    # site.standard.publication data model
├── services/
│   ├── OAuthService.php         # ATProto OAuth 2.1 PKCE + DPoP flow
│   ├── ApiService.php           # Authenticated PDS API calls
│   ├── PublisherService.php     # Orchestrates record creation
│   ├── BackfillService.php      # Bulk sync existing entries
│   └── ResolverService.php      # DID/PDS/handle resolution
├── transformers/
│   ├── DocumentTransformer.php  # Entry → site.standard.document
│   ├── PublicationTransformer.php # Site → site.standard.publication
│   ├── FacetTransformer.php     # Facet detection for Bluesky cross-posts
│   └── PostTransformer.php      # Entry → app.bsky.feed.post (optional)
├── jobs/
│   ├── PublishEntryJob.php      # Queue job: publish single entry
│   └── BackfillJob.php          # Queue job: bulk backfill
├── controllers/
│   ├── OAuthController.php      # OAuth callback + initiation CP routes
│   └── SettingsController.php   # CP settings actions
├── web/
│   └── WellKnownController.php  # Serves /.well-known/site.standard.publication
├── fields/
│   └── AtProtoStatusField.php   # Optional: custom field showing sync status + AT-URI
├── templates/
│   ├── settings/
│   │   ├── index.twig           # Main settings page
│   │   └── publication.twig     # Publication metadata
│   └── _entry-sidebar.twig      # Entry detail sidebar widget (sync toggle + status)
├── migrations/
│   └── Install.php              # DB table for entry↔record mapping
└── resources/
    └── js/
        └── settings.js          # OAuth connect button + CP JS
```

---

### Database Table

```sql
CREATE TABLE {{%atproto_entry_records}} (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    entryId     INT UNSIGNED NOT NULL,
    siteId      INT UNSIGNED NOT NULL,
    atUri       VARCHAR(512),          -- at://did.../site.standard.document/rkey
    bskyUri     VARCHAR(512),          -- at://did.../app.bsky.feed.post/rkey (if cross-posting)
    cid         VARCHAR(256),          -- Content identifier for the record
    publishedAt DATETIME,
    updatedAt   DATETIME,
    INDEX (entryId),
    INDEX (siteId)
);
```

---

### Settings Model

```php
// src/models/Settings.php
class Settings extends Model
{
    public string $pdsUrl = 'https://bsky.social';
    public string $handle = '';
    public string $did = '';
    public ?string $accessToken = null;     // encrypted
    public ?string $refreshToken = null;    // encrypted
    public ?string $publicationAtUri = null; // at://did.../site.standard.publication/rkey
    public bool $crossPostToBluesky = false;
    public bool $publishOnSave = true;
    public bool $showInDiscover = true;
    public array $enabledSections = [];     // Section UIDs to sync
    // Publication metadata
    public string $publicationName = '';
    public string $publicationDescription = '';
    public ?string $publicationIconAssetId = null;
}
```

---

## 4. ATProto OAuth 2.1 Implementation (PKCE + DPoP)

This is the most complex part. The WP plugin's `oauth/` folder is the best PHP reference.

### Flow Overview
```
1. User clicks "Connect" in CP
   └─> Generate PKCE code_verifier + code_challenge
   └─> Generate DPoP key pair (ES256)
   └─> Resolve PDS from handle (DNS + HTTP)
   └─> Fetch OAuth server metadata from PDS /.well-known/oauth-authorization-server
   └─> Send PAR (Pushed Authorization Request) to PDS
   └─> Redirect user to PDS authorization URL

2. User authorizes on PDS
   └─> PDS redirects back to our callback URL

3. Callback handler
   └─> Exchange code for tokens (with DPoP proof)
   └─> Store encrypted access_token + refresh_token
   └─> Store DID + PDS URL

4. Each API call
   └─> Attach DPoP proof header
   └─> On nonce error → retry with new nonce
   └─> On token expiry → refresh token flow
```

### Key PHP Implementation Notes

```php
// DPoP proof is a JWT signed with your ES256 private key
// Header: { "typ": "dpop+jwt", "alg": "ES256", "jwk": <public_key> }
// Payload: { "jti": uuid, "htm": "POST", "htu": endpoint_url, "iat": timestamp, "ath": token_hash }

// PKCE
$codeVerifier = bin2hex(random_bytes(32));
$codeChallenge = rtrim(strtr(base64_encode(hash('sha256', $codeVerifier, true)), '+/', '-_'), '=');

// PDS Resolution: handle → DID → PDS
// 1. GET https://bsky.social/xrpc/com.atproto.identity.resolveHandle?handle=yourhandle.bsky.social
// 2. GET https://plc.directory/{did} → get service endpoint (PDS URL)
// OR: GET {handle}/.well-known/atproto-did for custom domains
```

### PHP Libraries to Use
- **`firebase/php-jwt`** — for DPoP JWT generation
- **`web-token/jwt-library`** or **`spomky-labs/jose`** — for ES256 key generation
- **`guzzlehttp/guzzle`** — HTTP client (already available in Craft)
- No dedicated ATProto PHP SDK exists yet — you build on HTTP directly

---

## 5. Record Operations

### Creating Records (com.atproto.repo.createRecord)

```php
POST https://{pds}/xrpc/com.atproto.repo.createRecord
Authorization: DPoP {access_token}
DPoP: {dpop_proof}

{
  "repo": "did:plc:abc123",
  "collection": "site.standard.document",
  "record": {
    "$type": "site.standard.document",
    "site": "at://did:plc:abc123/site.standard.publication/rkey",
    "title": "My Post Title",
    "path": "/blog/my-post",
    "description": "Excerpt here...",
    "textContent": "Full plain text...",
    "publishedAt": "2026-03-20T12:00:00.000Z",
    "tags": ["craft", "web"],
    "coverImage": { "$type": "blob", ... }
  }
}
```

### Updating Records (com.atproto.repo.putRecord)
Same endpoint with `rkey` from the AT-URI — needed when an entry is updated after publish.

### Atomic Creation of Both Record Types (applyWrites)
```php
POST https://{pds}/xrpc/com.atproto.repo.applyWrites
{
  "repo": "did:plc:abc123",
  "writes": [
    {
      "$type": "com.atproto.repo.applyWrites#create",
      "collection": "site.standard.document",
      "value": { ... document record ... }
    },
    {
      "$type": "com.atproto.repo.applyWrites#create",
      "collection": "app.bsky.feed.post",
      "value": { ... bluesky post ... }
    }
  ]
}
```

### Uploading Blobs (for cover images)
```php
POST https://{pds}/xrpc/com.atproto.repo.uploadBlob
Content-Type: image/jpeg
[binary image data]
→ Returns { "blob": { "$type": "blob", "ref": {...}, "mimeType": "...", "size": ... } }
```

---

## 6. Entry → Document Transformer

```php
// src/transformers/DocumentTransformer.php
class DocumentTransformer
{
    public function transform(Entry $entry, string $publicationAtUri): array
    {
        $record = [
            '$type' => 'site.standard.document',
            'site' => $publicationAtUri,
            'title' => $entry->title,
            'publishedAt' => $entry->postDate->format(\DateTime::ATOM),
        ];

        // Path: use entry URL relative to site base URL
        $entryUrl = $entry->getUrl();
        $siteUrl = Craft::$app->getSites()->getSiteById($entry->siteId)->getBaseUrl();
        $record['path'] = '/' . ltrim(str_replace($siteUrl, '', $entryUrl), '/');

        // Description: meta description field, or truncated body
        if ($entry->metaDescription ?? null) {
            $record['description'] = $entry->metaDescription;
        }

        // textContent: strip HTML from body field
        if ($entry->body ?? null) {
            $record['textContent'] = strip_tags($entry->body);
        }

        // Tags: from categories or tags field
        // ...

        // updatedAt: if entry was modified after publish
        if ($entry->dateUpdated > $entry->postDate) {
            $record['updatedAt'] = $entry->dateUpdated->format(\DateTime::ATOM);
        }

        // coverImage: if blob was uploaded
        // ...

        return $record;
    }
}
```

---

## 7. Event Hooks in Craft

```php
// In AtProto.php::init()
Event::on(
    Entry::class,
    Entry::EVENT_AFTER_SAVE,
    function (ModelEvent $event) {
        $entry = $event->sender;
        if (!$entry->enabled || $entry->propagating) return;
        
        // Check if this section is enabled for sync
        $settings = AtProto::getInstance()->getSettings();
        if (!in_array($entry->section->uid, $settings->enabledSections)) return;
        
        // Push to queue (don't block request)
        Craft::$app->getQueue()->push(new PublishEntryJob([
            'entryId' => $entry->id,
            'siteId' => $entry->siteId,
        ]));
    }
);

Event::on(
    Entry::class,
    Entry::EVENT_AFTER_DELETE,
    function (Event $event) {
        // Delete the ATProto record via com.atproto.repo.deleteRecord
    }
);
```

---

## 8. Well-Known Routes (Verification)

Two verification mechanisms required by Standard.site:

### Publication: `/.well-known/site.standard.publication`
Register a URL rule returning the publication AT-URI as plain text.

```php
// In AtProto.php
Event::on(
    UrlManager::class,
    UrlManager::EVENT_REGISTER_SITE_URL_RULES,
    function (RegisterUrlRulesEvent $event) {
        $event->rules['.well-known/site.standard.publication'] = 'atproto/well-known/publication';
    }
);
```

```php
// src/web/WellKnownController.php
public function actionPublication(): Response
{
    $settings = AtProto::getInstance()->getSettings();
    return $this->asRaw($settings->publicationAtUri ?? '');
}
```

### Document: `<link>` tag in HTML `<head>`
Inject via a Twig extension or hook into Craft's `<head>` rendering:

```twig
{# In site layout, automatically injected by plugin #}
{% if entry is defined %}
  {% set atUri = craft.atproto.getDocumentUri(entry) %}
  {% if atUri %}
    <link rel="site.standard.document" href="{{ atUri }}">
  {% endif %}
{% endif %}
```

Or hook via `craft\web\View::EVENT_END_PAGE` to inject automatically.

---

## 9. Control Panel UI

### Settings Page (`/admin/settings/plugins/atproto`)

**Tab 1 — Connection**
- Handle input field
- "Connect with AT Protocol" button → initiates OAuth
- Connection status + DID display
- "Disconnect" button

**Tab 2 — Publication**
- Publication name, description
- Icon (asset field)
- Show in Discover toggle
- "Create / Update Publication Record" button
- Publication AT-URI display (read-only after creation)

**Tab 3 — Sections**
- Multi-select of enabled Entry sections/entry types
- Toggle: cross-post to Bluesky
- Toggle: publish on save vs. manual

### Entry Sidebar Widget
A small Twig template injected into the entry editor sidebar:
- Status badge: Not synced / Synced / Error
- AT-URI link (to pdsls.dev or bsky.app)
- "Publish to ATProto" / "Update Record" button
- "Unpublish" button

---

## 10. Craft Plugin: `composer.json`

```json
{
  "name": "yourname/craft-atproto",
  "description": "Publish Craft CMS entries to AT Protocol and Standard.site",
  "type": "craft-plugin",
  "license": "MIT",
  "require": {
    "craftcms/cms": "^5.0.0",
    "firebase/php-jwt": "^6.0",
    "guzzlehttp/guzzle": "^7.0"
  },
  "autoload": {
    "psr-4": {
      "yourname\\atproto\\": "src/"
    }
  },
  "extra": {
    "handle": "atproto",
    "name": "ATProto for Craft",
    "schemaVersion": "1.0.0",
    "hasCpSettings": true,
    "hasCpSection": false
  }
}
```

---

## 11. Implementation Phases

### Phase 1 — Foundation
- [ ] Plugin scaffolding (composer.json, main class, settings model)
- [ ] PDS/DID resolver (`ResolverService`)
- [ ] OAuth 2.1 PKCE + DPoP flow (`OAuthService`)
- [ ] Basic API service with token refresh + DPoP nonce retry (`ApiService`)

### Phase 2 — Publication
- [ ] Create/update `site.standard.publication` record
- [ ] `.well-known/site.standard.publication` route
- [ ] Settings CP page with OAuth connect button

### Phase 3 — Entry Sync
- [ ] `DocumentTransformer` (Entry → site.standard.document)
- [ ] `PublisherService` (create/update/delete records)
- [ ] Entry `EVENT_AFTER_SAVE` hook → queue job
- [ ] `<link rel="site.standard.document">` injection
- [ ] Entry sidebar widget with status + AT-URI

### Phase 4 — Polish
- [ ] Cover image blob upload
- [ ] Bluesky cross-posting (optional toggle)
- [ ] Backfill queue job for existing entries
- [ ] Error handling + retry logic
- [ ] Per-entry manual publish control

---

## 12. Key Differences vs. WordPress Plugin

| Aspect | WordPress (ATmosphere) | Craft CMS |
|---|---|---|
| Entry lifecycle | `wp_insert_post` action | `Entry::EVENT_AFTER_SAVE` |
| Settings storage | `wp_options` | Plugin settings model + DB |
| Background jobs | WP-Cron / custom | Craft Queue (Yii2) |
| Meta box | WordPress meta box API | Entry sidebar template + field |
| HTTP client | WordPress `wp_remote_*` | Guzzle (already in Craft) |
| URL rules | `add_rewrite_rule` | UrlManager event |
| Twig templates | Not applicable | Native CP + front-end twig |
| DB tables | `$wpdb` | Craft migrations + `Craft::$app->db` |
| Multisite | WP Multisite | Craft Sites (first-class) |

---

## 13. Resources

- [Standard.site docs](https://standard.site/docs/introduction/)
- [ATProto OAuth spec](https://atproto.com/specs/oauth)
- [ATProto XRPC API](https://atproto.com/specs/xrpc)
- [pdsls.dev](https://pdsls.dev) — browse PDS records
- [wordpress-atmosphere source](https://github.com/Automattic/wordpress-atmosphere) — best PHP reference
- [Wireservice WP plugin](https://wordpress.wireservice.net/) — another implementation angle
- [Craft plugin dev docs](https://craftcms.com/docs/5.x/extend/plugin-guide.html)
- [markpub.at](https://markpub.at/) — Markdown lexicon for content field
