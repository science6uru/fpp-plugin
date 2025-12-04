# Photolog – Fixed Point Photography Time-Lapse Plugin for WordPress

**A complete fixed-point photography system for nature preserves, parks, and conservation organizations**

WordPress Plugin Version  
License: GPLv2 or later  
PHP: greater than or equal to 7.4  
WordPress: greater than or equal to 5.8

Originally built as an Eagle Scout project for the Spring Creek Forest Preservation Society, Photolog lets visitors and hikers take and submit photos from permanent photo points using their phones, and automatically builds moderated time-lapse galleries using these crowdsourced photos.

### Demo Screenshots

#### Public Upload Pages
![Public upload form with drag-and-drop and preview](https://github.com/science6uru/fpp-plugin/blob/main/.github/uploadForm.png?raw=true)  
Clean upload with reCAPTCHA v3 protection

#### Admin Photo Moderation Dashboard
![Admin photo management table with approve/reject buttons](https://github.com/science6uru/fpp-plugin/blob/main/.github/wpListTableModeration.png?raw=true)  
WP_List_Table interface with filtering, bulk actions, and thumbnails

#### Station Management
![Stations list with photo counts and shortcodes](https://github.com/science6uru/fpp-plugin/blob/main/.github/wpListTableStations.png?raw=true)  
Easy station creation, renaming, and generated shortcodes

## Features

- Unlimited independent photo stations
- Public upload form via shortcode `[fpp_upload station="slug"]`
- Google reCAPTCHA v3 + automatic fallback to v2 checkbox challenge
- Admin moderation queue (Approve / Reject / Delete)
- Automatic thumbnail generation (200×200 px)
- Unique, unguessable filenames for security
- Carousel/gallery shortcode `[fpp_carousel station="slug"]` (WIP)
- Photos stored in `wp-content/uploads/fpp-plugin/station-XX/`
- Configurable plugin max upload size & reCAPTCHA score thresholds
- Upload pages automatically excluded from search results
- Clean admin interface under FPP Admin menu

## Installation

1. Download the latest release zip and import it as a wordpress plugin via the Plugins menu (Or upload the `src` folder to `/wp-content/plugins/` and rename it to `photolog`)
3. Activate Photolog from the Plugins menu
4. Go to **FPP Admin → Settings** and enter your Google reCAPTCHA v3 and v2 keys
5. Through the Photolog stations menu, create your stations in the plugin for each photo point
6. Create a page for each station and add the shortcode:

```
[fpp_upload station="your-station-slug"]
```

6. (WIP) Add a gallery page and embed the shortcode for each station:

```
[fpp_carousel station="your-station-slug"]
```

7. Print QR codes pointing to each upload page and place them at your photo points

## Shortcodes

| Shortcode                              | Description                                      | Required Attribute |
|----------------------------------------|--------------------------------------------------|--------------------|
| `[fpp_upload station="example-trail-head"]`    | Public photo submission form                     | `station`          |
| `[fpp_carousel station="example-trail-head"]`       | Displays approved photos (carousel/gallery)      | `station`          |

## Admin Menu Overview

- **FPP Admin → Dashboard** – Quick stats (WIP)
- **FPP Admin → Stations** – Create, rename, delete stations
- **FPP Admin → (Station Name)** – Moderate photos for that station
- **FPP Admin → Settings** – reCAPTCHA keys, thresholds, upload limits

## File Structure After Activation

```
wp-content/uploads/
└── fpp-plugin/
    ├── station-1/
    │   ├── a1b2c3d4e5-123.jpg
    │   └── a1b2c3d4e5-123-thumb.jpg
    ├── station-2/
    └── ...
```

## Database Tables

- `wp_fpp_stations` – station metadata (id, name, slug, upload_page_slug, lat/lon)
- `wp_fpp_photos` – photo records (id, station_id, ip, file_name, thumb_200, status, timestamps)

## Security & Privacy

- REST API upload endpoint with server-side reCAPTCHA verification
- Randomized filenames
- IP logging
- Transactional database handling (rollback on failure)
- Direct directory access can be blocked via `.htaccess` if needed

## Development & Contributing

The plugin is intentionally modular and easy to extend:

```
photolog/
├── plugin.php                  Main bootstrap
├── plugin-admin.php            Admin menus & settings
├── plugin-activation.php       DB setup, upgrades, cleanup
├── plugin-shortcodes.php       Shortcode routing
├── fpp_uploads.php             REST API upload + image processing
├── admin_manage.php            Photo moderation table
├── fpp-plugin-fpp_upload.php   Upload form template
├── fpp-plugin-fpp_carousel.php Carousel template (customize!)
├── js/fpp_upload.js            Frontend + reCAPTCHA logic
└── css/fpp_upload.css          Styling
```

### Local Development

Use the included VSCode Dev Container (`.devcontainer/` folder) for a full WordPress + MariaDB + WP-CLI + Xdebug environment.

## Roadmap / Planned Features

- Bulk approve/reject actions
- Full-featured carousel/image viewer
- GPS coordinates + interactive map of stations
- Alerts on new pending photos for admins

## License

Released under **GPLv2 or later**

## Credits

Created by Andrew Owen, @yevha1, @thinhcomepan, @sowen  
Eagle Scout Service Project 2024–2025  
For the Spring Creek Forest Preservation Society

**Thank you for helping document nature**

If you use Photolog in your preserve or park, I’d love to see it! Email me at andrewseagle2025@gmail.com
