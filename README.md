# Microfix Audio Platform — Plugin Documentation
Version 2.0.0

---

## Table of Contents

1. [Overview](#overview)
2. [Requirements](#requirements)
3. [Installation](#installation)
4. [ACF Fields (Manual Setup)](#acf-fields-manual-setup)
5. [Episode Categories](#episode-categories)
6. [Content Structure](#content-structure)
7. [Drip / Unlock Dates Explained](#drip--unlock-dates-explained)
8. [Shortcodes Reference](#shortcodes-reference)
9. [Elementor Widgets](#elementor-widgets)
10. [Setting Up the Homepage Weekly Sessions Section](#setting-up-the-homepage-weekly-sessions-section)
11. [Setting Up the Member Dashboard Page](#setting-up-the-member-dashboard-page)
12. [Protecting the Dashboard with MemberPress](#protecting-the-dashboard-with-memberpress)
13. [Secure Audio / Video Streaming](#secure-audio--video-streaming)
14. [Playback Progress (Continue Listening)](#playback-progress-continue-listening)
15. [Hooks & Filters Reference](#hooks--filters-reference)
16. [Troubleshooting](#troubleshooting)

---

## Overview

The **Microfix Audio Platform** plugin turns your WordPress site into a membership-based audio and video course platform. It integrates with MemberPress for access control and ACF Pro for content fields.

Key capabilities:
- **Programs → Episodes** content hierarchy
- **Secure PHP streaming** — audio and video files are never directly accessible via URL
- **Drip content** — episodes unlock on a specific date (unlock_date)
- **Weekly Sessions** homepage widget showing this week's episodes
- **Member Dashboard** portal with hero episode, continue-listening, and episode grid
- **Episode Categories** (taxonomy) for labelling content (Communication, Self-Discovery, etc.)
- **Elementor native widgets** — drag-and-drop in the Elementor panel
- **Progress tracking** — saves playback position per user in a custom DB table

---

## Requirements

| Requirement        | Version     |
|--------------------|-------------|
| WordPress          | 6.3+        |
| PHP                | 8.0+        |
| ACF Pro            | Any         |
| Elementor Pro      | Any         |
| MemberPress        | Any         |

---

## Installation

1. Download `microfix-audio-platform.zip`
2. In WordPress admin → **Plugins → Add New → Upload Plugin**
3. Upload the zip and click **Install Now**, then **Activate**
4. The plugin registers two custom post types: **Programs** and **Episodes**
5. A progress-tracking database table is created automatically on activation
6. Go to **Settings → Permalinks** and click **Save Changes** to flush rewrite rules

### Recommended .htaccess rule

Add this to `/wp-content/uploads/.htaccess` to block direct access to media files:

```apache
<FilesMatch "\.(mp3|mp4|ogg|wav|aac|webm|m4a|m4v)$">
  Order Allow,Deny
  Deny from all
</FilesMatch>
```

For Nginx, add inside your `server {}` block:
```nginx
location ~* ^/wp-content/uploads/.*\.(mp3|mp4|ogg|wav|aac|webm|m4a|m4v)$ {
    deny all;
}
```

---

## ACF Fields (Manual Setup)

**The plugin does NOT auto-create ACF fields** (you already created them via the ACF dashboard). Below is the complete list of fields the plugin expects.

### Field Group: "Episode Details" → Post Type: `episode`

| Field Name    | Field Key (internal) | Type         | Notes                                          |
|---------------|----------------------|--------------|------------------------------------------------|
| `program`     | any                  | Post Object  | Return format: **ID**. Required.               |
| `audio_file`  | any                  | File         | Return format: **ID**. MIME: audio/*           |
| `video_file`  | any                  | File         | Return format: **ID**. MIME: video/*           |
| `video_url`   | any                  | URL          | Optional. Used when no self-hosted video exists|
| `unlock_date` | any                  | Date Picker  | Return format: **Y-m-d**. Leave blank = always available |
| `duration`    | any                  | Text         | e.g. "12:34". Displayed in cards.              |
| `is_featured` | any                  | True/False   | Toggle UI on. Used by featured episode widget. |
| `content_type`| any                  | Select       | Choices: `audio` / `video`. Default: `audio`.  |

> **Important:** Field names (the "name" slug) must match exactly as listed above. Field labels can be anything.

### Field Group: "Program Details" → Post Type: `program`

| Field Name             | Type     | Notes                        |
|------------------------|----------|------------------------------|
| `program_description`  | Textarea | Short description for listings |
| `program_order`        | Number   | Lower = shown first. Default 0 |

---

## Episode Categories

The plugin registers a custom taxonomy: **Episode Category** (`episode_category`).

- Appears in the episode edit screen as a checkbox list (like WordPress categories)
- Shows as a column in the Episodes list table
- Examples: Communication, Self-Discovery, Emotional Regulation, Relationships, Skills

### Creating categories
**Episodes → Categories** in the WordPress admin sidebar.

### Using in shortcodes / widgets
Categories are displayed automatically on all episode cards and session cards. No extra configuration needed.

---

## Content Structure

```
Program
  └── Episode (linked via ACF "program" field)
        ├── audio_file  (or video_file / video_url)
        ├── unlock_date
        ├── duration
        ├── content_type
        └── episode_category (taxonomy)
```

### Creating a Program
1. Go to **Programs → Add New**
2. Set title, featured image (used as card background), excerpt
3. Optionally fill in ACF fields: Short Description, Display Order

### Creating an Episode
1. Go to **Episodes → Add New**
2. Set title, excerpt (shown as subtitle in cards), featured image
3. Assign to a **Program** via the ACF "Program" field
4. Upload your audio or video file (ACF file field)
5. Set **unlock_date** if you want drip scheduling
6. Select a **Content Type** (audio / video)
7. Assign one or more **Categories** (right sidebar)
8. Publish

---

## Drip / Unlock Dates Explained

The `unlock_date` field controls when an episode becomes available.

| Scenario                        | unlock_date value | Result                                      |
|---------------------------------|-------------------|---------------------------------------------|
| Always available                | (blank)           | Immediately accessible to all members       |
| Available from a specific date  | `2025-06-01`      | Locked with "Available on June 1, 2025" message until that date |
| This week's episode             | This week Mon–Sun | Appears in the Weekly Sessions section      |

### "Week X" labels
The plugin automatically calculates "Week 1", "Week 2" etc. by comparing each episode's `unlock_date` to the earliest `unlock_date` within the same Program. No manual field needed.

---

## Shortcodes Reference

All shortcodes use the `mfx_` prefix.

---

### `[mfx_weekly_sessions]`
**Purpose:** Homepage "Your Weekly Sessions" section — shows episodes unlocking this Mon–Sun week.

```
[mfx_weekly_sessions title="Your Weekly Sessions" subtitle="New episodes every Tuesday at 5am EST" limit="3"]
```

| Attribute  | Default                              | Description                          |
|------------|--------------------------------------|--------------------------------------|
| `title`    | "Your Weekly Sessions"               | Section heading text                 |
| `subtitle` | "New episodes every Tuesday at 5am EST" | Subtitle below heading            |
| `limit`    | 3                                    | Max number of session cards to show  |

**Access behaviour:**
- Logged-out users → lock overlay + "Log in to access" link
- Logged-in but no membership → lock overlay + "Upgrade membership" link
- Date-locked (future unlock_date) → calendar lock icon + "Available on DATE"
- Accessible → play button

---

### `[mfx_member_dashboard]`
**Purpose:** Full member portal page.

```
[mfx_member_dashboard]
```

No attributes. Renders:
1. **Welcome header** with greeting and new-episode badge
2. **This Week's Episode** — hero card (first episode unlocking this week)
3. **Continue Listening** — last in-progress episode (from DB progress)
4. **All Episodes** — all programs with their episodes, grouped

If the user is not logged in, shows a login prompt.
If the user has no membership, shows an upgrade prompt.

---

### `[mfx_play_button]`
**Purpose:** Standalone play/lock button for a single episode.

```
[mfx_play_button episode_id="123"]
```

| Attribute    | Default           | Description       |
|--------------|-------------------|-------------------|
| `episode_id` | Current post ID   | Episode to play   |

Shows:
- Green play button with "Play Now" or "Resume" (if in-progress) for accessible episodes
- Lock icon + message for locked/gated episodes

---

### `[mfx_membership_status]`
**Purpose:** Inline membership status badge.

```
[mfx_membership_status]
```

Shows: green dot "Active Member" / red dot "No active membership" / "Not logged in" with login button.

---

### `[mfx_episodes_grid]`
**Purpose:** Full episode grid grouped by program.

```
[mfx_episodes_grid program_id="0" columns="3"]
```

| Attribute    | Default | Description                          |
|--------------|---------|--------------------------------------|
| `program_id` | 0       | 0 = all programs; or specific ID     |
| `columns`    | 3       | 1–4 columns                          |

---

### `[mfx_programs_grid]`
**Purpose:** Program catalog grid.

```
[mfx_programs_grid columns="3"]
```

---

## Elementor Widgets

After activating the plugin, a new **"Microfix Platform"** category appears in the Elementor panel (left sidebar → search or scroll down).

| Widget Name         | Equivalent Shortcode        |
|---------------------|-----------------------------|
| Weekly Sessions     | `[mfx_weekly_sessions]`     |
| Member Dashboard    | `[mfx_member_dashboard]`    |
| Episodes Grid       | `[mfx_episodes_grid]`       |
| Programs Grid       | `[mfx_programs_grid]`       |
| Membership Status   | `[mfx_membership_status]`   |

Each widget has a settings panel where you can configure the same options as the shortcode attributes. Changes preview live in the Elementor editor.

---

## Setting Up the Homepage Weekly Sessions Section

1. Edit your homepage in **Elementor**
2. In the Elementor panel, search for **"Weekly Sessions"** or scroll to the **Microfix Platform** category
3. Drag the **Weekly Sessions** widget onto your page
4. In the widget settings panel:
   - Set the **Section Title** (e.g., "Your Weekly Sessions")
   - Set the **Subtitle**
   - Set **Max Cards** (default 3)
5. Click **Update** / Publish

The section automatically shows episodes whose `unlock_date` falls within the current Monday–Sunday week. If no episodes are scheduled for the current week, the section shows a "Check back soon!" message.

---

## Setting Up the Member Dashboard Page

1. Create a new page: **Pages → Add New**
2. Set title: "My Dashboard" (or "Portal", "My Account" etc.)
3. Click **Edit with Elementor**
4. In the Elementor panel, search for **"Member Dashboard"**
5. Drag it onto the page canvas — it takes the full width
6. Click **Update** / Publish
7. Note the page URL — you will need it in the next step

---

## Protecting the Dashboard with MemberPress

1. Go to **MemberPress → Rules**
2. Click **Add New**
3. Set **Protected Content** → select your dashboard page
4. Under **Access Conditions**, select your membership level(s)
5. Under **Unauthorized Access**, choose where to redirect non-members (e.g., your membership/pricing page)
6. Save the rule

MemberPress will now redirect non-members away from the dashboard automatically.

---

## Secure Audio / Video Streaming

All media files are served through a PHP endpoint, **never directly from `/wp-content/uploads/`**.

### How it works
1. When the play button is clicked, the plugin builds a URL:
   `/?microfix_secure_stream=FILE_ID&episode=POST_ID&type=audio&token=HMAC_TOKEN`
2. The token is an **HMAC-SHA256** hash signed with WordPress's secret key, valid for a 2-hour rolling window
3. The PHP endpoint validates: token → login → membership + unlock_date → file ownership → streams bytes

### Range requests
The streamer supports HTTP Range headers so the HTML5 player can seek within files without downloading them entirely.

### Adding `subtitle` ACF field (optional)
Some cards fall back to `get_the_excerpt()` for the subtitle line. If you want explicit subtitles, add a **Text** ACF field named `subtitle` to the Episode field group.

---

## Playback Progress (Continue Listening)

Progress is saved in a custom database table (`{prefix}microfix_progress`) every 5 seconds while audio is playing, and also on page unload.

- Only logged-in users have progress saved
- Episodes are considered "complete" at 95% — they won't appear in Continue Listening after that
- The Continue Listening section on the dashboard shows the most recently played incomplete episode

### Database table structure

| Column       | Type              | Description                       |
|--------------|-------------------|-----------------------------------|
| `id`         | BIGINT UNSIGNED   | Auto-increment primary key        |
| `user_id`    | BIGINT UNSIGNED   | WordPress user ID                 |
| `episode_id` | BIGINT UNSIGNED   | Episode post ID                   |
| `position`   | FLOAT             | Playback position in seconds      |
| `duration`   | FLOAT             | Total duration in seconds         |
| `updated_at` | DATETIME          | Last updated timestamp            |

---

## Hooks & Filters Reference

### Filter: `microfix_membership_page_url`
Override the URL users are sent to when they need to upgrade.

```php
add_filter( 'microfix_membership_page_url', function( $url, $episode_id ) {
    return home_url( '/join/' );
}, 10, 2 );
```

### Filter: `microfix_has_active_membership`
Override membership check result when all built-in strategies fail.

```php
add_filter( 'microfix_has_active_membership', function( $has_access, $user_id ) {
    // Your custom check here.
    return false;
}, 10, 2 );
```

### Action: `wp_ajax_microfix_save_progress`
The AJAX handler for saving progress — fires when the JS POSTs every 5 seconds.

---

## Troubleshooting

### Episodes not showing in Weekly Sessions
- Make sure the episode's `unlock_date` is set to a date within the current Monday–Sunday week
- Check that the episode is Published (not Draft)

### "No media" shown instead of play button
- The episode's `audio_file` (or `video_file`) ACF field is empty — upload the file and save

### Lock icon showing for admin
- Admins bypass MemberPress checks and should always see play buttons. If you see a lock, check that the episode's `unlock_date` is not in the future, or that the `audio_file` field is set.

### Continue Listening not showing
- Progress is only saved for logged-in users
- You must play at least a few seconds for the first save to trigger
- Check that the `{prefix}microfix_progress` table exists in your database (visible in phpMyAdmin)

### Elementor widgets not appearing
- Make sure Elementor is activated before the Microfix plugin
- Try going to **Elementor → Tools → Regenerate Files**

### Stream returns 403 Forbidden
- User may not be logged in, or their membership has expired
- The HMAC token may have expired (valid for 2 hours) — refreshing the page generates a new one
- Check that the `audio_file` field contains the correct attachment ID

### Audio file still accessible directly
- Ensure the `.htaccess` rule has been added to `/wp-content/uploads/.htaccess`
- If on Nginx, add the `deny all` location block to your server config
