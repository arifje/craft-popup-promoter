# Popup Promoter

Popup Promoter is a Craft CMS 4 and Craft CMS 5 plugin for showing promotional modal popups from entries.

It can use an existing section and field setup, or create a default `Popups` section with fields for a show switch, description, image, promoted label, call to action URL, call to action label, and cancel button label.

## Requirements

- Craft CMS `^4.0 || ^5.0`
- PHP `^8.0.2`

## Installation

Install the plugin with Composer:

```bash
composer require arifje/craft-popup-promoter
php craft plugin/install craft-popup-promoter
```

For local development, add a path repository to the Craft project:

```json
{
  "repositories": [
    {
      "type": "path",
      "url": "../craft-popup-promoter"
    }
  ]
}
```

Then require it:

```bash
composer require arifje/craft-popup-promoter:@dev
```

## Setup

Open the plugin settings in the control panel and choose the entry section and field mappings. Only live entries are eligible, so disabled, pending, and expired entries are skipped automatically.

Field mapping dropdowns show the custom fields available on the selected popup section. Use the test modal button to preview a random live entry with the current mappings directly from the settings page.

The show switch, promoted label, call to action label, and cancel button text can be mapped from entry fields, with fallback text configured in plugin settings where relevant. Button colors can be configured independently for the primary call to action and cancel button.

Use the **Create default section + fields** button to create:

- `popups` section
- `popupShow` lightswitch field
- `popupDescription` plain text field
- `popupImage` assets field
- `popupPromotedLabel` plain text field
- `popupCtaUrl` plain text field
- `popupCtaLabel` plain text field
- `popupCancelLabel` plain text field

The same setup can be run from the command line:

```bash
php craft craft-popup-promoter/setup/install-defaults
```

## Entry Selection

On each frontend page request, the plugin endpoint:

1. Queries and shuffles live entry IDs from the configured section and current site.
2. Loads candidates in batches of at most 50 entries.
3. Skips entries where the mapped show-popup field is off or the current visitor has an active dismissal cookie.
4. Returns the first eligible entry in the shuffled order.

If nothing is eligible, no popup is rendered.
Only IDs are loaded for the entire section; entry hydration is bounded. When all candidates are ineligible, all batches still need to be checked.

CTA URLs allow HTTP, HTTPS, `mailto:`, `tel:`, and relative links. Unsafe schemes, control characters, backslashes, and malformed HTTP(S) URLs omit the CTA from the payload, including for custom frontends.

## Frontend

The frontend is a Vue 3 component powered by `vue-final-modal`. It supports these variants:

- Centered modal
- Full page modal
- Top banner
- Bottom banner
- Left drawer
- Right drawer
- Corner modal

You can either choose one default variant or enable randomized variants to mix the display style automatically on each popup render.

The frontend integration is registered automatically by default. To control placement yourself, disable automatic injection and add this to your layout:

```twig
{{ craft.popupPromoter.register() }}
```

If you use your own Vite/Vue frontend component, disable **Load default Vue component** in the plugin settings. The plugin will then skip its bundled Vue/CSS asset bundle. You can still expose the popup endpoint to your frontend with:

```twig
{{ craft.popupPromoter.registerConfig() }}
```

That outputs `window.CraftPopupPromoterConfig.endpoint`. You can also read the endpoint directly in Twig:

```twig
{{ craft.popupPromoter.endpointUrl() }}
```

The endpoint returns the CTA text as `popup.cta.label`, `popup.cta.text`, `popup.cta.title`, and `popup.cta.buttonText`, with the same value also available as top-level aliases like `popup.ctaLabel`, `popup.ctaText`, `popup.ctaButtonLabel`, `popup.ctaButtonText`, and `popup.buttonText` for custom frontends.

The promoted/kicker label is available as `popup.promotedLabel`, `popup.promotedText`, `popup.kickerLabel`, and `popup.eyebrowLabel`.

When a popup is closed, the component sets a per-entry cookie. The cookie duration is configurable in plugin settings; use `0` for a session cookie.

The popup can also be delayed by a configurable number of seconds so it does not open immediately on page load.

## Development

Install frontend dependencies and build the browser asset:

```bash
npm install
npm run build
```

The build writes:

- `src/web/assets/dist/popup-promoter.iife.js`
- `src/web/assets/dist/popup-promoter.css`

Run the PHP regression checks against an existing Craft installation's dependencies:

```bash
php tests/run.php /path/to/craft/vendor
```

For Docker development, run that command inside the existing PHP container with paths visible there. Run it against both Craft 4 and 5. The checks use real framework models and cookie handling with in-memory persistence/query doubles; they do not bootstrap a site or write to a database. They cover URL rejection, dismissal cookies, bounded entry selection, default field/layout setup, and setup retries. A full installation and CP smoke test still requires a disposable Craft site.

## Upgrade notes — 1.0.1

- Rejects unsafe CTA URLs and recognizes browser-written dismissal cookies with cookie validation enabled or disabled.
- Avoids loading every entry into memory when selecting a popup.
- Fixes default setup for Craft 4 field groups and Craft 5 entry types, and uses the native custom-field layout constructor on both versions.
- Setup reports settings-save failures and missing entry types. If setup fails partway, successfully created fields/types are preserved and reused on retry; it is not an atomic operation.

No schema migration is required. Existing settings and content are retained. Review any CTA links using unsupported schemes. If default setup previously failed, rerun **Create default section + fields** in development and deploy the resulting project config normally. The CP setup and preview actions require the `settings` permission and CSRF-protected POST requests.

## Events

The frontend dispatches browser events for analytics hooks:

- `craft-popup-promoter:shown`
- `craft-popup-promoter:dismissed`
- `craft-popup-promoter:error`
