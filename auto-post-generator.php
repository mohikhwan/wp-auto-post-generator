<?php
/**
 * Plugin Name: Auto Post Generator
 * Plugin URI: https://example.com/auto-post-generator
 * Description: Automatically creates new blog posts from RSS feeds every 30 minutes
 * Version: 1.0.0
 * Author: APG
 * Author URI: https://example.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: auto-post-generator
 */

// Prevent direct access to this file
if (!defined('ABSPATH')) {
  exit;
}

// Define plugin constants
define('APG_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('APG_PLUGIN_URL', plugin_dir_url(__FILE__));

/**
 * Main plugin class
 */
class Auto_Post_Generator
{
  private static $instance = null;

  public static function get_instance()
  {
    if (null === self::$instance) {
      self::$instance = new self();
    }
    return self::$instance;
  }

  private function __construct()
  {
    // Initialize plugin
    add_action('init', array($this, 'init'));

    // Hook for the actual post generation
    add_action('apg_generate_posts', array($this, 'generate_posts'));

    // Add admin menu
    add_action('admin_menu', array($this, 'add_admin_menu'));
  }

  /**
   * Initialize plugin
   */
  public function init()
  {
    // Load text domain for translations
    load_plugin_textdomain('auto-post-generator', false, dirname(plugin_basename(__FILE__)) . '/languages');
  }

  /**
   * Add admin menu
   */
  public function add_admin_menu()
  {
    // Add main menu
    add_menu_page(
      'Auto Post Generator', // Page title
      'Auto Post Generator', // Menu title
      'manage_options', // Capability
      'auto-post-generator', // Menu slug
      array($this, 'render_main_page'), // Callback function
      'dashicons-admin-post', // Icon
      30 // Position
    );

    // Remove the duplicate submenu
    remove_submenu_page('auto-post-generator', 'auto-post-generator');

    // Add submenu for cron status
    add_submenu_page(
      'auto-post-generator', // Parent slug
      'Cron Status', // Page title
      'Cron Status', // Menu title
      'manage_options', // Capability
      'auto-post-generator-cron', // Menu slug
      array($this, 'render_cron_status_page') // Callback function
    );

    // Add submenu for manual trigger
    add_submenu_page(
      'auto-post-generator', // Parent slug
      'Manual Trigger', // Page title
      'Manual Trigger', // Menu title
      'manage_options', // Capability
      'auto-post-generator-trigger', // Menu slug
      array($this, 'render_trigger_page') // Callback function
    );
  }

  /**
   * Render main page
   */
  public function render_main_page()
  {
    echo '<div class="wrap">';
    echo '<h1>Auto Post Generator</h1>';
    echo '<p>Welcome to Auto Post Generator:</p>';
    echo '</div>';
  }

  /**
   * Render cron status page
   */
  public function render_cron_status_page()
  {
    if (!current_user_can('manage_options')) {
      wp_die(__('You do not have sufficient permissions to access this page.'));
    }

    $next_scheduled = wp_next_scheduled('apg_generate_posts');
    $schedules = wp_get_schedules();

    echo '<div class="wrap">';
    echo '<h1>Cron Status</h1>';
    echo '<h2>Next Scheduled Run</h2>';
    echo '<p>' . ($next_scheduled ? date('Y-m-d H:i:s', $next_scheduled) : 'Not scheduled') . '</p>';

    echo '<h2>Current Time</h2>';
    echo '<p>' . date('Y-m-d H:i:s') . '</p>';

    echo '<h2>Available Schedules</h2>';
    echo '<pre>';
    print_r($schedules);
    echo '</pre>';

    echo '<h2>All Scheduled Events</h2>';
    echo '<pre>';
    print_r(_get_cron_array());
    echo '</pre>';
    echo '</div>';
  }

  /**
   * Render trigger page
   */
  public function render_trigger_page()
  {
    if (!current_user_can('manage_options')) {
      wp_die(__('You do not have sufficient permissions to access this page.'));
    }

    echo '<div class="wrap">';
    echo '<h1>Manual Trigger</h1>';

    if (isset($_GET['trigger']) && $_GET['trigger'] == '1') {
      $result = $this->test_generate_posts();
      echo '<div class="notice notice-success"><p>' . $result . '</p></div>';
    }

    $trigger_url = add_query_arg('trigger', '1', admin_url('admin.php?page=auto-post-generator-trigger'));
    echo '<p><a href="' . esc_url($trigger_url) . '" class="button button-primary">Trigger Post Generation</a></p>';
    echo '</div>';
  }

  /**
   * Check cron status
   */
  public function check_cron_status()
  {
    if (isset($_GET['check_cron']) && current_user_can('manage_options')) {
      $next_scheduled = wp_next_scheduled('apg_generate_posts');
      $schedules = wp_get_schedules();

      echo '<h2>Cron Status</h2>';
      echo '<p>Next scheduled run: ' . ($next_scheduled ? date('Y-m-d H:i:s', $next_scheduled) : 'Not scheduled') . '</p>';
      echo '<p>Current time: ' . date('Y-m-d H:i:s') . '</p>';

      echo '<h3>Available Schedules</h3>';
      echo '<pre>';
      print_r($schedules);
      echo '</pre>';

      echo '<h3>All Scheduled Events</h3>';
      echo '<pre>';
      print_r(_get_cron_array());
      echo '</pre>';

      exit;
    }
  }

  /**
   * Schedule the cron job if not already scheduled
   */
  public function schedule_cron()
  {
    error_log('Auto Post Generator: Checking cron schedule');

    // First, clear any existing schedule to ensure clean state
    wp_clear_scheduled_hook('apg_generate_posts');
    error_log('Auto Post Generator: Cleared existing cron schedule');

    // Get available schedules
    $schedules = wp_get_schedules();
    error_log('Auto Post Generator: Available schedules: ' . print_r($schedules, true));

    // Only schedule if not already scheduled
    if (!wp_next_scheduled('apg_generate_posts')) {
      // Schedule the event with the new interval
      $scheduled = wp_schedule_event(time(), 'five_minutes', 'apg_generate_posts');

      if (is_wp_error($scheduled)) {
        error_log('Auto Post Generator: Cron scheduling failed - ' . $scheduled->get_error_message());
      } else {
        $next_run = wp_next_scheduled('apg_generate_posts');
        error_log('Auto Post Generator: Cron event scheduled successfully for ' . date('Y-m-d H:i:s', $next_run));
      }
    } else {
      $next_run = wp_next_scheduled('apg_generate_posts');
      error_log('Auto Post Generator: Cron already scheduled for ' . date('Y-m-d H:i:s', $next_run));
    }
  }

  /**
   * Generate posts from RSS feed
   */
  public function generate_posts()
  {
    // RSS feed URL - CNET
    $feed_url = 'https://www.cnet.com/rss/news/';

    error_log('Auto Post Generator: Starting feed fetch from ' . $feed_url);

    // Include WordPress feed functions
    include_once(ABSPATH . WPINC . '/feed.php');

    // Get the feed
    $feed = fetch_feed($feed_url);

    if (is_wp_error($feed)) {
      error_log('Auto Post Generator Error: ' . $feed->get_error_message());
      return;
    }

    // Get total number of items in feed
    $total_items = $feed->get_item_quantity();
    error_log('Auto Post Generator: Total items in feed: ' . $total_items);

    // Start with first 5 items
    $offset = 0;
    $items_to_fetch = 5;
    $new_posts_created = 0;

    while ($new_posts_created < 5 && $offset < $total_items) {
      // Get next batch of items
      $items = $feed->get_items($offset, $items_to_fetch);
      error_log('Auto Post Generator: Fetching items ' . ($offset + 1) . ' to ' . ($offset + $items_to_fetch));

      foreach ($items as $item) {
        // Get item details
        $title = $item->get_title();
        $description = $item->get_description();
        $content = $item->get_content();
        $permalink = $item->get_permalink();

        // Log item details
        error_log('Auto Post Generator: Item details -');
        error_log('Title: ' . ($title ? $title : 'EMPTY'));
        error_log('Description: ' . ($description ? substr($description, 0, 100) . '...' : 'EMPTY'));
        error_log('Content length: ' . strlen($content));
        error_log('Permalink: ' . ($permalink ? $permalink : 'EMPTY'));

        // Use description as title if title is empty
        if (empty($title) && !empty($description)) {
          $title = strip_tags($description);
          // Truncate to a reasonable length
          $title = substr($title, 0, 100);
          error_log('Auto Post Generator: Using description as title: ' . $title);
        }

        // Skip if still no title
        if (empty($title)) {
          error_log('Auto Post Generator: Skipping item - No valid title available');
          continue;
        }

        // Check if post with this title already exists using WP_Query
        $args = array(
          'post_type' => 'post',
          'post_status' => 'any',
          'title' => $title,
          'posts_per_page' => 1
        );

        $query = new WP_Query($args);

        if (!$query->have_posts()) {
          error_log('Auto Post Generator: Creating new post - ' . $title);

          // Create new post
          $post_data = array(
            'post_title' => $title,
            'post_content' => $content . "\n\n" .
              '<p><em>' . __('Source:', 'auto-post-generator') .
              ' <a href="' . esc_url($permalink) . '">' .
              esc_html($permalink) . '</a></em></p>',
            'post_status' => 'publish',
            'post_type' => 'post',
            'post_author' => 1, // Default to admin user
          );

          // Insert the post
          $post_id = wp_insert_post($post_data);

          if (is_wp_error($post_id)) {
            error_log('Auto Post Generator Error: ' . $post_id->get_error_message());
          } else {
            error_log('Auto Post Generator: Successfully created post ID ' . $post_id);
            $new_posts_created++;

            // If we've created 5 new posts, break out of both loops
            if ($new_posts_created >= 5) {
              break 2;
            }
          }
        } else {
          error_log('Auto Post Generator: Post already exists - ' . $title);
        }

        // Reset post data
        wp_reset_postdata();
      }

      // Move to next batch
      $offset += $items_to_fetch;
    }

    error_log('Auto Post Generator: Created ' . $new_posts_created . ' new posts');
  }

  /**
   * Test function to manually generate posts
   */
  public function test_generate_posts()
  {
    error_log('Auto Post Generator: Starting manual test');
    $this->generate_posts();
    error_log('Auto Post Generator: Manual test completed');

    // Add link to check cron status
    $check_cron_url = add_query_arg('check_cron', '1', home_url());
    return 'Posts generated successfully! <a href="' . esc_url($check_cron_url) . '">Check Cron Status</a>';
  }

  /**
   * Clean up on plugin deactivation
   */
  public static function deactivate()
  {
    error_log('Auto Post Generator: Deactivating plugin and clearing cron');
    wp_clear_scheduled_hook('apg_generate_posts');
  }
}

// Add custom cron interval
add_filter('cron_schedules', function ($schedules) {
  $schedules['five_minutes'] = array(
    'interval' => 5 * 60, // 5 minutes in seconds
    'display' => __('Every 5 minutes', 'auto-post-generator')
  );
  return $schedules;
});

// Initialize the plugin
add_action('plugins_loaded', array('Auto_Post_Generator', 'get_instance'));

// Register activation hook to ensure cron is scheduled
register_activation_hook(__FILE__, function () {
  error_log('Auto Post Generator: Plugin activated, scheduling cron');
  Auto_Post_Generator::get_instance()->schedule_cron();
});

// Register deactivation hook
register_deactivation_hook(__FILE__, array('Auto_Post_Generator', 'deactivate'));

// Add test endpoint
add_action('init', function () {
  if (isset($_GET['test_apg']) && current_user_can('manage_options')) {
    $apg = Auto_Post_Generator::get_instance();
    echo $apg->test_generate_posts();
    exit;
  }
});