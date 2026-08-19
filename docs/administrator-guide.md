# InStoreOnly administrator guide

## Homepage banners

- Open the Homepage in Elementor.
- Select **Homepage Banner Carousel — edit images only**.
- Add, remove, or reorder images in the carousel gallery. Do not edit section height or background settings.
- Use landscape 16:9 artwork: minimum 1600×900, preferred 1920×1080.
- The carousel shows one complete image at a time, changes every five seconds, loops, pauses for interaction, and supports arrows and swipe.

## Shopping Centres

- Go to **Listings → All Shopping Centres**.
- Add the centre name, description, dedicated landscape 16:9 image, address line, suburb, state, and postcode.
- The public centre URL remains `/shopping-centre/{slug}/`.
- The complete archive is `/shopping-centres/`.
- Configure the archive under **Listings → Shopping Centre Tools → Archive display settings**. You can show/hide empty centres, choose 6/12/24/all items per page, enable search, select the sort order, and show/hide deal counts.
- Empty centres are shown by default to match the complete staging archive. The homepage keeps its separate Elementor **Hide Empty Centres** and **Limit** controls.
- The per-centre **Show this Shopping Centre publicly** checkbox always takes priority; hidden centres never appear publicly.
- On a listing, use the **Store / Location** panel. Choose **Yes**, select the managed centre, enter the required Shop / Unit Number, and optionally enter a Level / Precinct.
- Choose **No** for a standalone location and complete the normal Directorist address/location fields.
- Existing assigned listings without a unit number appear under **Listings → Shopping Centre Tools** and must be completed on their next edit.

## Listing details

- **Standard Deal** appears before **Flash Deal**; saved deal values are unchanged.
- **Operating / Open Hours** replaces the Business Hours label for store/deal directory types. Existing hour values are unchanged.
- The optional Booking Page URL remains in the upper business/contact portion of the listing form. A saved button is shown near the top of the public listing.
- Contact Listing Owner and Claim are disabled publicly. Live Chat and non-interactive owner/store information remain available.
- A radio field allows one selection. The blue circle is its selection control.

## Daily Slider Images

- Use portrait 1080×1920 artwork. This rule is separate from Homepage and Shopping Centre banners.
- The global master toggle is under Directorist settings.
- Each listing has its own toggle and an explicit Enabled/Disabled badge.
- Both toggles must be enabled for weekday images to rotate.
