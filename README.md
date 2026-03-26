# Standard Site

Standard.site integration for Craft CMS

## Requirements

This plugin requires Craft CMS 5.9.0 or later, and PHP 8.4 or later.

## Installation

You can install this plugin from the Plugin Store or with Composer.

#### From the Plugin Store

Go to the Plugin Store in your project’s Control Panel and search for “Standard Site”. Then press “Install”.

#### With Composer

Open your terminal and run the following commands:

```bash
# go to the project directory
cd /path/to/my-project.test

# tell Composer to load the plugin
composer require studioespresso/craft-standard-site

# tell Craft to install the plugin
./craft plugin/install standard-site
```

## Backfilling Existing Entries

If you have existing published entries that you want to sync to AT Protocol, you can use the backfill console command.

### Prerequisites

Before running a backfill, make sure you have:

1. **Connected to AT Protocol** — Visit the Standard.site page in the CP sidebar and connect your account.
2. **Created a publication record** — In the plugin settings, fill in your publication name and description, then click "Create Publication Record".
3. **Enabled at least one section** — In the plugin settings under "Sync Settings", check the sections you want to sync.

### Usage

```bash
./craft standard-site/backfill <siteHandle>
```

For example, to backfill the default site:

```bash
./craft standard-site/backfill default
```

### What happens

- All live entries in enabled sections for the given site are processed.
- Entries that have already been synced to AT Protocol are automatically skipped.
- Each entry is transformed into a `site.standard.document` record and published to your PDS.
- Progress is printed to the terminal as each entry is processed.
- If an individual entry fails, the error is shown and the backfill continues with the remaining entries.

### Multi-site

Backfill runs per site. Pass the handle of the site you want to backfill. Run the command once per site if you have multiple sites configured.
