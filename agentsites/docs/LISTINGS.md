# LISTINGS.md — manual listings, CSV import and feeds

Spec §14. Every listing belongs to one tenant (`listings.tenant_id`, `TenantScope`), has a
unique `ref` within the tenant, a `source` (`manual`, `csv`, `feed`, `demo`) and a `status`
(`available`, `sold`, `rented`, `hidden`). Only `available` listings appear on the site; `sold`
and `rented` stay in the agent's list for their history; `hidden` is the agent's own switch.

## Three ways in, one validation

`ListingCsv::normalize()` is the single validator: the form, the CSV importer and every feed
adapter hand it the same column set and get back either attributes or per-field errors, so a
rule (AED price ≥ 0, one of the twelve property types, bedrooms 0–30, …) lives once.

| Column | Required | Rule |
| --- | --- | --- |
| `ref` | yes | ≤ 80 chars, unique per site (a second row with the same ref **updates**) |
| `title_en`, `title_ar` | `title_en` | ≤ 200 chars |
| `offering` | yes | `sale` or `rent` |
| `property_type` | yes | `apartment`, `villa`, `townhouse`, `penthouse`, `studio`, `land`, `office`, `duplex`, `compound`, `retail`, `warehouse`, `other` |
| `price` | yes | number ≥ 0; `currency` defaults to `AED`; rentals are per year |
| `bedrooms`, `bathrooms` | | 0–30 (`0` bedrooms renders as *Studio*) |
| `area_sqft` | | number |
| `community`, `city` | | free text; the form autocompletes from `config/communities.php`; `city` defaults to Dubai |
| `status` | | `available` (default), `sold`, `rented`, `hidden` |
| `featured` | | `true`/`1`/`yes` → shown on the home page |
| `description_en`, `description_ar` | | free text |
| `media` | | photo URLs separated by `;` (cached into the site's media directory unless told otherwise) |

## Manual (the agent, on the phone)

`app.{base}/listings` (Livewire, `app/Livewire/Listings`): list with search, featured / hide /
delete per row and the plan usage; `listings/new` and `listings/{id}` with the form above,
**multi-photo upload** (drag to reorder, first is the cover), the community datalist and a map
pin as a latitude/longitude pair pasted from Google Maps. Saving purges the site's page cache,
so the site shows the change on the next request (a visitor's browser may hold a page for up to
60 s — `Cache-Control: max-age=60`).

Photos go through `ListingPhotos` → `ImageProcessor::variants()`: **thumb 400 / card 800 / hero
1600**, WebP, EXIF dropped, stored under `{tenant_id}/listings/` on the `media` disk and served by
`MediaController` (own directory only). A listing's `media` column holds either a plain URL
(feeds without caching, demo listings) or `{path, variants: {thumb, card, hero}, width, height,
source}`; `Listing::imageSets()` gives the views the three sizes either way.

## Sample listings

A new site shows six sample listings (`DemoSeeder`, `source = demo`, refs `DEMO-1…6`) so it never
looks empty. They disappear from the site as soon as the agent has **any** real listing (hidden
or sold ones count — DECISIONS #55), they never carry structured data and never reach the
sitemap. `listings.show_demo_until_real: false` in the tenant config switches them off outright.

## CSV import

`app.{base}/listings/import`: download the template (`/listings/template.csv`, header + one
sample row), upload, read the preview (rows / new / updates / rows with problems, listed per row),
then import. From the CLI:

```sh
php artisan platform:listings:import ahmed-al-falasi listings.csv --dry-run   # validate only
php artisan platform:listings:import ahmed-al-falasi listings.csv             # import; errors → listings.errors.csv
php artisan platform:listings:import ahmed-al-falasi listings.csv --no-media  # keep photo URLs, do not cache
```

Rules: dedupe by `ref` (update, never duplicate), the plan's listing limit is enforced on new
rows (`PlanLimits`, `config/plans.php`), a bad row never blocks the good ones, every rejected row
goes to `<file>.errors.csv` with the reason, and the site is purged once at the end.

## Feeds

`app.{base}/listings/feeds`: connect a feed by URL (basic-auth credentials are stored encrypted),
pick the provider, set the per-agent filters, sync now, pause, remove. Every active feed is synced
**every 30 minutes** (`SyncDueFeeds`, `routes/console.php`) and on demand:

```sh
php artisan platform:feeds:sync --feed=12          # one feed
php artisan platform:feeds:sync --tenant=ahmed-al-falasi   # every active feed of one site
php artisan platform:feeds:sync --due              # what the scheduler does
```

Per-agent filters (`listing_feeds.filters`): `offering` (`sale`|`rent`), `min_price`,
`max_price`, `property_types` (list), `communities` (list, case-insensitive). A listing that
leaves the feed (or stops matching the filters) is **hidden, not deleted** (DECISIONS #50), so
its photos and URL survive a broken export. Feed listings carry `source = feed` and `feed_ref`;
the agent can still feature or hide them from the list.

### Provider `generic_xml`

The platform's own format — anything a CRM can export:

```xml
<listings>
  <listing>
    <ref>REF-1001</ref>
    <title_en>2BR with Burj view in Downtown</title_en>
    <title_ar>شقة غرفتين بإطلالة على برج خليفة</title_ar>
    <offering>sale</offering>
    <property_type>apartment</property_type>
    <price>2450000</price>
    <bedrooms>2</bedrooms><bathrooms>2</bathrooms><area_sqft>1350</area_sqft>
    <community>Downtown</community><city>Dubai</city>
    <featured>true</featured>
    <description_en>Bright two-bedroom apartment with a full Burj Khalifa view.</description_en>
    <images><image>https://example.com/photos/1001-1.jpg</image><image>https://example.com/photos/1001-2.jpg</image></images>
  </listing>
</listings>
```

One child element per CSV column; images as `<images><image>` or a `;`-separated `<media>`.

### Provider `propertyfinder`

The Property Finder XML export (`<list><property>`): `reference_number` → `ref`,
`title_en/ar`, `description_en/ar`, `offering_type` `RS`/`CS` → sale and `RR`/`CR` → rent,
`property_type` codes (`AP` apartment, `VH` villa, `TH` townhouse, `PH` penthouse, `DX` duplex,
`CD` compound, `LP` land, `OF` office, `RE` retail, `WH` warehouse, `ST` studio), `price` (or
`<price><yearly>` for rentals), `bedroom`, `bathroom`, `size`, `community`, `city`,
`<photo><url>`. Fields the export does not carry stay empty.

## Plan limits

`config/plans.php` is the source of truth (`PlanLimits::limit($account, 'listings')`): trial 100,
starter 25, pro 100, brokerage 1000. Only real listings count. The form, the importer and the
feed sync all stop at the limit with the same message; the agent sees "n of limit" on the list.

## On the site

Available listings show on `/listings` (filters: offering, type, beds; paginated), on
`/listings/{ref}` (gallery from the variants, WhatsApp enquiry with the ref pre-filled, related
listings from the same community), featured ones on the home page, and community matches on
`/areas/{area}`. Real listings render a schema.org `Offer` and appear in `sitemap.xml`; a
listing's cover is its Open Graph image.
