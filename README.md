# Directorist Shopping Centres

Version 1.1 adds the responsive Homepage centre carousel, the paginated Shopping Centres archive, structured centre addresses, accessible Directorist search suggestions, exact-name search routing, grouped store/deal pages, and the backend Store / Location workflow.

See [docs/administrator-guide.md](docs/administrator-guide.md) for the day-to-day content workflow and media rules.

Dynamic shopping centre tiles and shopping-centre deal pages for Directorist listings.

This extension lets an admin create shopping centres, upload a centre image, assign Directorist deal listings to a centre, and display those centres on the homepage with an Elementor widget or shortcode.

## What This Extension Does

- Adds a **Shopping Centres** taxonomy to Directorist listings.
- Adds an image upload field for each shopping centre.
- Creates automatic frontend URLs like `/shopping-centre/westfield-bondi-junction/`.
- Shows all current published Directorist listings assigned to that centre.
- Sorts participating store deals alphabetically by store name.
- Adds an Elementor widget named **Shopping Centres**.
- Keeps shortcode support for non-Elementor pages.

## Screenshot Overview

Plugin guide page:

![Plugin settings](docs/screenshots/01-plugin-settings.png)

Shopping Centres admin list:

![Shopping Centres list](docs/screenshots/02-shopping-centres-list.png)

Shopping Centre image field:

![Shopping Centre image field](docs/screenshots/03-centre-image-field.png)

Assign a listing to a Shopping Centre:

![Listing edit screen](docs/screenshots/04-listing-edit-screen.png)

Elementor widget search:

![Elementor widget panel](docs/screenshots/05-elementor-widget-panel.png)

Frontend Shopping Centre page:

![Frontend centre page](docs/screenshots/06-frontend-centre-page.png)

## Admin Usage

### 1. Check The Plugin Is Active

Go to:

`Plugins > Installed Plugins`

Confirm **Directorist Shopping Centres** is active.

### 2. Create A Shopping Centre

Go to:

`Directorist > Shopping Centres`

Then:

1. Enter the centre name, for example `Westfield Bondi Junction`.
2. Leave the slug blank unless you need a custom URL.
3. Add a description if needed.
4. Click **Select Image** under **Shopping Centre Image**.
5. Upload or choose an image from the Media Library.
6. Click **Add New Shopping Centre** or **Update**.

The uploaded image is used on homepage tiles and centre pages.

### 3. Assign A Deal Listing To A Shopping Centre

Go to:

`Directorist > All Listings`

Then:

1. Open the deal listing.
2. Find the **Shopping Centres** box on the right side.
3. Tick or add the correct centre.
4. Click **Update**.

That listing will now appear on the selected shopping centre page.

### 4. Add Shopping Centre Tiles With Elementor

Edit the homepage with Elementor.

1. Search for the widget named **Shopping Centres**.
2. Drag it into the page.
3. Use the **Content** tab to set:
   - Title
   - Show or hide title
   - Desktop/tablet/mobile columns
   - Hide empty centres
   - Limit
4. Use the **Style** tab to adjust:
   - Heading style
   - Grid gap
   - Card background, border, radius, shadow, height, and padding
   - Image height and radius
   - Centre name typography and color
   - Deal count typography and color

### 5. Add Shopping Centre Tiles With Shortcode

Use this shortcode on a normal WordPress page:

```text
[directorist_shopping_centres hide_empty="1"]
```

Useful options:

```text
[directorist_shopping_centres title="Shop deals by shopping centre" columns="3" hide_empty="1" number="0"]
```

Option meanings:

| Option | Example | Meaning |
|---|---:|---|
| `title` | `Shop deals by shopping centre` | Heading above the tiles |
| `columns` | `3` | Number of columns |
| `hide_empty` | `1` | Hide centres with no deals |
| `number` | `0` | Limit count; `0` means show all |

### 6. Show Deals For One Centre With Shortcode

Use:

```text
[directorist_shopping_centre_deals centre="westfield-bondi-junction"]
```

The `centre` value can be the shopping centre slug.

## How The Dynamic Data Works

The extension does not use hardcoded demo data.

It reads real WordPress/Directorist data:

- Shopping centre names come from the **Shopping Centres** taxonomy.
- Shopping centre images come from the image field on each centre.
- Deal listings come from published Directorist listings.
- Store names are read from listing meta such as `_store_name`.
- Offer text is read from deal subtitle or summary fields when available.
- Deal page links are normal Directorist single listing URLs.

## Sync Existing Listings

If older listings already have a Directorist custom field like **Shopping Centre / Venue**, go to:

`Directorist > Shopping Centres`

Then click **Sync Existing Listings**.

The sync checks these listing meta keys:

- `_shopping_centre_venue`
- `shopping_centre_venue`
- `_shopping_centre_name`
- `shopping_centre_name`

It creates matching Shopping Centre terms and assigns listings automatically.

## Frontend URLs

Shopping centre pages are generated automatically.

Example:

```text
/shopping-centre/westfield-bondi-junction/
```

If the URL gives a 404 after activation, go to:

`Settings > Permalinks`

Then click **Save Changes** once.

## Developer Notes

Main files:

| File | Purpose |
|---|---|
| `directorist-shopping-centres.php` | Main plugin bootstrap, taxonomy, shortcodes, syncing, templates |
| `includes/class-elementor-shopping-centres-widget.php` | Elementor widget and style controls |
| `templates/taxonomy-shopping-centre.php` | Frontend taxonomy template |
| `assets/css/frontend.css` | Frontend styles |
| `assets/js/admin.js` | Admin image picker |

Important constants:

```php
Directorist_Shopping_Centres::TAXONOMY        // at_biz_dir-shopping-centre
Directorist_Shopping_Centres::TERM_IMAGE_META // _dsc_image_id
```

Useful filters:

```php
directorist_shopping_centres_meta_keys
directorist_shopping_centres_deal_ids
```

## Verification Checklist

After setup:

1. Create or edit a shopping centre.
2. Upload a centre image.
3. Assign at least one Directorist listing to that centre.
4. Open the shopping centre frontend URL.
5. Confirm the listing appears.
6. Click the listing and confirm it opens the Directorist single listing page.
7. Add the Elementor widget to the homepage and style it from Elementor.
