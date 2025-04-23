# Auto Post Generator

A WordPress plugin that automatically creates blog posts from RSS feeds at regular intervals.

## Features

- Automatically fetches and creates posts from RSS feeds
- Configurable update interval (default: every 5 minutes)
- Smart post creation that skips duplicate content
- Admin interface for monitoring and manual control
- Detailed logging for troubleshooting

## Installation

1. Download the plugin files
2. Upload the `auto-post-generator` folder to the `/wp-content/plugins/` directory
3. Activate the plugin through the 'Plugins' menu in WordPress

## Configuration

### Changing the RSS Feed URL

To change the RSS feed URL, edit the `auto-post-generator.php` file:

1. Open `wp-content/plugins/auto-post-generator/auto-post-generator.php`
2. Find the line with the RSS feed URL (around line 118):
   ```php
   $feed_url = 'https://www.cnet.com/rss/news/';
   ```
3. Replace the URL with your desired RSS feed
4. Save the file

### Changing the Update Interval

To change how often the plugin checks for new posts:

1. Open `wp-content/plugins/auto-post-generator/auto-post-generator.php`
2. Find the cron interval definition (around line 365):
   ```php
   $schedules['five_minutes'] = array(
       'interval' => 5 * 60, // 5 minutes in seconds
       'display' => __('Every 5 minutes', 'auto-post-generator')
   );
   ```
3. Modify the interval value (in seconds) and the display name
4. Save the file
5. Deactivate and reactivate the plugin to apply changes

## Usage

### Admin Interface

The plugin adds a menu item "Auto Post Generator" to your WordPress admin sidebar with two submenus:

1. **Cron Status**
   - View the next scheduled run time
   - Check current time
   - View available schedules
   - Monitor all scheduled events

2. **Manual Trigger**
   - Manually trigger post generation
   - View generation results

### Manual Testing

You can test the plugin manually by:

1. Visiting `http://your-site.com/?test_apg=1`
2. Or using the "Manual Trigger" button in the admin interface

## Troubleshooting

If posts are not being generated:

1. Check the WordPress debug log for error messages
2. Verify the RSS feed URL is accessible
3. Ensure the cron job is properly scheduled
4. Check if the feed contains valid content

## Requirements

- WordPress 5.0 or higher
- PHP 7.0 or higher
- WordPress cron enabled
- Access to RSS feed

## Support

For support, please:
1. Check the WordPress debug log for error messages
2. Verify your RSS feed is accessible
3. Ensure your WordPress cron is working properly

## License

This plugin is licensed under the GPL v2 or later. 