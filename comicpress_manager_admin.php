<?php

//harmonious @maxdb @hash @zip:rename

require_once('classes/ComicPressManager.php');
require_once('classes/ComicPressManagerAdmin.php');
//require_once('FirePHPCore/fb.php');

include('cp_configuration_options.php');

$default_comicpress_config_file_lines = array('<?' . 'php');
foreach ($comicpress_configuration_options as $option_info) {
  $default_comicpress_config_file_lines[] = "//{$option_info['name']} - {$option_info['description']} (default \"{$option_info['default']}\")";
  $default_comicpress_config_file_lines[] = "\${$option_info['id']} = \"{$option_info['default']}\";";
  $default_comicpress_config_file_lines[] = "";
}
$default_comicpress_config_file_lines[] = "?>";

$default_comicpress_config_file = implode("\n", $default_comicpress_config_file_lines);

cpm_initialize_options();

/**
 * Initialize ComicPress Manager options.
 */
function cpm_initialize_options() {
  global $comicpress_manager;

  include('cpm_configuration_options.php');

  foreach ($configuration_options as $option_info) {
    if (is_array($option_info)) {
      $result = $comicpress_manager->get_cpm_option($option_info['id']);

      if (isset($option_info['not_blank']) && empty($result)) { $result = false; }

      if ($result === false) {
        $default = (isset($option_info['default']) ? $option_info['default'] : "");
        $comicpress_manager->set_cpm_option($option_info['id'], $default);
      }
    }
  }
}


/**
 * Wrap the help text and activity content in the CPM page style.
 * @param string $help_content The content to show in the Help box.
 * @param string $activity_content The content to show in the Activity box.
 */
function cpm_wrap_content($help_content, $activity_content, $show_sidebar = true) {
  global $wp_scripts, $comicpress_manager;
  cpm_write_global_styles_scripts();

  ?>

<div class="wrap">
  <div id="cpm-container" <?php echo (!$show_sidebar || ($comicpress_manager->get_cpm_option('cpm-sidebar-type') == "none")) ? "class=\"no-sidebar\"" : "" ?>>
    <?php if (cpm_handle_warnings()) { ?>
      <!-- Header -->
      <?php cpm_show_manager_header() ?>

      <div style="overflow: hidden">
        <div id="cpm-activity-column" <?php echo (!$show_sidebar || ($comicpress_manager->get_cpm_option('cpm-sidebar-type') == "none")) ? "class=\"no-sidebar\"" : "" ?>>
          <div class="activity-box"><?php echo $activity_content ?></div>
          <![if !IE]>
            <script type="text/javascript">prepare_comicpress_manager()</script>
          <![endif]>
        </div>

        <?php if ($show_sidebar && ($comicpress_manager->get_cpm_option('cpm-sidebar-type') !== "none")) { ?>
          <div id="cpm-sidebar-column">
            <?php
              switch($comicpress_manager->get_cpm_option('cpm-sidebar-type')) {
                case "latest":
                  cpm_show_latest_posts();
                  break;
                case "standard":
                default:
                  cpm_show_comicpress_details();
                  break;
              }
            ?>
            <?php if (!is_null($help_content) && ($comicpress_manager->get_cpm_option('cpm-sidebar-type') == "standard")) { ?>
              <div id="comicpress-help">
                <h2 style="padding-right:0;"><?php _e("Help!", 'comicpress-manager') ?></h2>
                <?php echo $help_content ?>
              </div>
            <?php } ?>
          </div>
        <?php } ?>
      </div>
    <?php cpm_show_footer() ?>
  <?php } ?>
  </div>
</div>
<?php }

function cpm_manager_page_caller($page) {
  global $comicpress_manager;

  $do_first_run = false;
  if (!$comicpress_manager->get_cpm_option('cpm-did-first-run')) {
    $all_comic_folders_found = true;
    foreach (array(''. 'rss_', 'archive_') as $folder_name) {
      if (!file_exists(CPM_DOCUMENT_ROOT . '/' . $comicpress_manager->properties["${folder_name}comic_folder"])) { $all_comic_folders_found = false; break; }
    }

    $do_first_run = !$all_comic_folders_found;
    if (!$do_first_run) {
      if (!function_exists('get_site_option')) {
        update_option("comicpress-manager-cpm-did-first-run", 1);
      }
    }
  }

  if ($do_first_run) {
    include("pages/comicpress_first_run.php");
    cpm_manager_first_run(plugin_basename(__FILE__));
    if (!function_exists('get_site_option')) {
      update_option("comicpress-manager-cpm-did-first-run", 1);
    }
  } else {
    if ($comicpress_manager->did_first_run) { $page = "config"; }
    include("pages/comicpress_${page}.php");
    call_user_func("cpm_manager_${page}");
  }
}

/**
 * Wrappers around page calls to reduce the amount of code in _admin.php.
 */
function cpm_manager_index_caller() { cpm_manager_page_caller("index"); }
function cpm_manager_status_caller() { cpm_manager_page_caller("status"); }
function cpm_manager_dates_caller() { cpm_manager_page_caller("dates"); }
function cpm_manager_import_caller() { cpm_manager_page_caller("import"); }
function cpm_manager_config_caller() { cpm_manager_page_caller("config"); }
function cpm_manager_cpm_config_caller() { cpm_manager_page_caller("cpm_config"); }
function cpm_manager_storyline_caller() { cpm_manager_page_caller("storyline"); }

function cpm_manager_write_comic_caller() {
  include("pages/write_comic_post.php");
  cpm_manager_write_comic(plugin_basename(__FILE__));
}

/**
 * Show the header.
 */
function cpm_show_manager_header() {
  global $comicpress_manager; ?>
  <div id="icon-admin" class="icon32"><br /></div>
  <h2>
  <?php if (!is_null($comicpress_manager->category_info['comiccat'])) { ?>
    <?php printf(__("Managing &#8216;%s&#8217;", 'comicpress-manager'), $comicpress_manager->category_info['comiccat']['name']) ?>
  <?php } else { ?>
    <?php _e("Managing ComicPress", 'comicpress-manager') ?>
  <?php } ?>
 </h2>
<?php }

/**
 * Find all the thumbnails for a particular image root.
 */
function cpm_find_thumbnails($date_root) {
  global $comicpress_manager;

  $thumbnails_found = array();
  foreach (array('rss', 'archive') as $type) {
    if ($comicpress_manager->separate_thumbs_folder_defined[$type]) {
      $path = CPM_DOCUMENT_ROOT . '/' . $comicpress_manager->properties[$type . "_comic_folder"];
      if (($subdir = $comicpress_manager->get_subcomic_directory()) !== false) {
        $path .= '/' . $subdir;
      }
      $files = glob($path . '/' . $date_root . "*");
      if ($files === false) { $files = array(); }

      if (count($files) > 0) {
        $thumbnails_found[$type] = substr(realpath(array_shift($files)), CPM_STRLEN_REALPATH_DOCUMENT_ROOT);
      }
    }
  }

  return $thumbnails_found;
}

function cpm_display_storyline_checkboxes($category_tree, $post_categories, $prefix = null, $root_name = "in-comic-category") {
  foreach ($category_tree as $node) {
    $parts = explode("/", $node);
    $category_id = end($parts);
    $name = (empty($prefix) ? "" : "${prefix}-") . $root_name;
    ?>
    <div style="margin-left: <?php echo (count($parts) - 2) * 20 ?>px; white-space: nowrap">
      <label>
        <input type="checkbox"
               name="<?php echo $name ?>[]"
               value="<?php echo $category_id ?>" id="<?php echo $name ?>-<?php echo $category_id ?>"
               <?php echo in_array($category_id, $post_categories) ? "checked" : "" ?> />
        <?php echo get_cat_name($category_id) ?>
      </label>
    </div>
  <?php }
}

/**
 * Generate &lt;option&gt; elements for all comic categories.
 */
function generate_comic_categories_options($form_name, $label = true) {
  global $comicpress_manager;

  $number_of_categories = 0;
  $first_category = null;

  extract($comicpress_manager->normalize_storyline_structure());
  if ($comicpress_manager->get_subcomic_directory() !== false) {
    $default_category = get_option('comicpress-manager-manage-subcomic');
  } else {
    if ($comicpress_manager->get_cpm_option('cpm-default-comic-category-is-last-storyline') == 1) {
      $default_category = end(explode("/", end($category_tree)));
    } else {
      $default_category = $comicpress_manager->properties['comiccat'];
    }
  }

  ob_start();
  foreach (get_all_category_ids() as $cat_id) {
    $ok = ($cat_id == $default_category);

    if ($ok) {
      $number_of_categories++;
      $category = get_category($cat_id);
      if (is_null($first_category)) { $first_category = $category; }
      ?>
      <option
        value="<?php echo $category->cat_ID ?>"
        <?php if (!is_null($comicpress_manager->category_info['comiccat'])) {
          echo ($default_category == $cat_id) ? " selected" : "";
        } ?>
        ><?php echo $category->cat_name; ?></option>
    <?php }
  }
  $output = ob_get_clean();

  if ($number_of_categories == 0) {
    return false;
  } else {
    if ($number_of_categories == 1) {
      $output = "<input type=\"hidden\" name=\"${form_name}\" value=\"{$first_category->cat_ID}\" />";
      if ($label) { $output .= $first_category->cat_name; }
      return $output;
    } else {
      return "<select name=\"${form_name}\">" . $output . "</select>";
    }
  }
}

/**
 * Write the current ComicPress Config to disk.
 */
function write_comicpress_config_functions_php($filepath, $just_show_config = false, $use_default_file = false) {
  global $comicpress_manager, $default_comicpress_config_file;

  $file_lines = array();

  if ($use_default_file) {
    $file_lines = $default_comicpress_config_file;
  } else {
    foreach (file($filepath) as $line) {
      $file_lines[] = rtrim($line, "\r\n");
    }
  }

  include('cp_configuration_options.php');

  $properties_written = array();

  $closing_line = null;

  for ($i = 0; $i < count($file_lines); $i++) {
    foreach (array_keys($comicpress_manager->properties) as $variable) {
      if (!in_array($variable, $properties_written)) {
        if (preg_match("#\\$${variable}\ *\=\ *([^\;]*)\;#", $file_lines[$i], $matches) > 0) {
          $value = $comicpress_manager->properties[$variable];
          $file_lines[$i] = '$' . $variable . ' = "' . $value . '";';
          $properties_written[] = $variable;
        }
      }
    }
    if (strpos($file_lines[$i], "?>") !== false) { $closing_line = $i; }
  }

  foreach (array_keys($comicpress_manager->properties) as $variable) {
    if (!in_array($variable, $properties_written)) {
      foreach ($comicpress_configuration_options as $option_info) {
        if ($option_info['id'] == $variable) {
          $comicpress_lines = array();
          $comicpress_lines[] = "//{$option_info['name']} - {$option_info['description']} (default \"{$option_info['default']}\")";
          $comicpress_lines[] = "\${$option_info['id']} = \"{$comicpress_manager->properties[$variable]}\";";
          $comicpress_lines[] = "";
          array_splice($file_lines, $closing_line, 0, $comicpress_lines);
          break;
        }
      }
    }
  }

  $file_output = implode("\n", $file_lines);

  if (!$just_show_config) {
    if ($comicpress_manager->can_write_config) {
      $target_filepath = $filepath . '.' . time();
      $temp_filepath = $target_filepath . '-tmp';
      if ($comicpress_manager->_f->file_write_contents($temp_filepath, $file_output) !== false) {
        if (file_exists($temp_filepath)) {
          @chmod($temp_filepath, CPM_FILE_UPLOAD_CHMOD);
          if (@rename($filepath, $target_filepath)) {
            if (@rename($temp_filepath, $filepath)) {
              return array($target_filepath);
            } else {
              @unlink($temp_filepath);
              @rename($target_filepath, $filepath);
            }
          } else {
            @unlink($temp_filepath);
          }
        }
      }
    }
  }

  return $file_output;
}

/**
 * Generate links to view or edit a particular post.
 * @param array $post_info The post information to use.
 * @return string The view & edit post links for the post.
 */
function generate_view_edit_post_links($post_info) {
  $view_post_link = sprintf("<a href=\"{$post_info['guid']}\">%s</a>", __("View post", 'comicpress-manager'));
  $edit_post_link = sprintf("<a href=\"post.php?action=edit&amp;post={$post_info['ID']}\">%s</a>", __("Edit post", 'comicpress-manager'));

  return $view_post_link . ' | ' . $edit_post_link;
}

/**
 * Show the Post Body Template.
 * @param integer $width The width of the editor in pixels.
 */
function cpm_show_post_body_template($width = 435) {
  global $comicpress_manager; ?>


  <?php
}

/**
 * Sort backup files by timestamp.
 */
function cpm_available_backup_files_sort($a, $b) {
  if ($a[1] == $b[1]) return 0;
  return ($a[1] > $b[1]) ? -1 : 1;
}


/**
 * Show the Latest Posts in the Sidebar.
 */
function cpm_show_latest_posts() {
  global $comicpress_manager;

  $is_current = false;
  $is_previous = false;
  $current_timestamp = time();
  foreach ($comicpress_manager->query_posts() as $comic_post) {
    $timestamp = strtotime($comic_post->post_date);

    if ($timestamp < $current_timestamp) {
      $is_current = true;
    }

    if ($is_current) {
      if ($is_previous) {
        $previous_post = $comic_post;
        break;
      }
      $current_post = $comic_post;
      $is_previous = true;
    } else {
      $upcoming_post = $comic_post;
    }
  }

  $found_posts = compact('previous_post', 'current_post', 'upcoming_post');
  $post_titles = array('previous_post' => __("Last Post", 'comicpress-manager'),
                       'current_post' => __("Current Post", 'comicpress-manager'),
                       'upcoming_post' => __("Upcoming Post", 'comicpress-manager'));
  ?>

  <div id="comicpress-latest-posts">
    <?php if (!empty($found_posts)) { ?>
      <?php foreach ($post_titles as $key => $title) {
        if (!empty($found_posts[$key])) {
          $timestamp = strtotime($found_posts[$key]->post_date);
          $post_date = date(CPM_DATE_FORMAT, $timestamp);

          $comic_file = null;
          foreach ($comicpress_manager->comic_files as $file) {
            if (($result = $comicpress_manager->breakdown_comic_filename(pathinfo($file, PATHINFO_BASENAME))) !== false) {
              if ($result['date'] == $post_date) { $comic_file = $file; break; }
            }
          }

          ?>
          <div class="<?php echo (!empty($comic_file)) ? "comic-found" : "comic-not-found" ?>">
            <h3><?php echo $title ?> &mdash; <?php echo $post_date ?></h3>

            <h4><?php echo $found_posts[$key]->post_title ?> [<?php echo generate_view_edit_post_links((array)$found_posts[$key]) ?>]</h4>

            <?php if (!empty($comic_file)) { ?>
              <img alt="<?php echo $found_posts[$key]->post_title ?>" src="<?php echo $comicpress_manager->build_comic_uri($file, CPM_DOCUMENT_ROOT) ?>" width="320" />
            <?php } else { ?>
              <div class="alert">Comic file not found!</div>
            <?php } ?>
          </div>
        <?php }
      }
    } else { ?>
      <p>You don't have any comic posts!</p>
    <?php } ?>
  </div>

  <?php
}


/**
 * Show the footer.
 */
function cpm_show_footer() {
  $version_string = "";
  foreach (array('/', '/../') as $pathing) {
    if (($path = realpath(dirname(__FILE__) . $pathing . 'comicpress-manager.php')) !== false) {
      if (file_exists($path)) {
        $info = get_plugin_data($path);
        $version_string = sprintf(__("Version %s |", 'comicpress-manager'), $info['Version']);
        break;
      }
    }
  }

  ?>
  <div id="cpm-footer">
    <div id="cpm-footer-paypal">
      <form action="https://www.paypal.com/cgi-bin/webscr" method="post">
      <input type="hidden" name="cmd" value="_s-xclick">
      <input type="hidden" name="encrypted" value="-----BEGIN PKCS7-----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-----END PKCS7-----
      ">
      <input type="image" src="https://www.paypal.com/en_US/i/btn/btn_donate_SM.gif" border="0" name="submit" alt="">
      <img alt="" border="0" src="https://www.paypal.com/en_US/i/scr/pixel.gif" width="1" height="1">
      </form>
    </div>
    <div id="cpm-footer-text">
      <?php _e('<a href="http://wordpress.org/extend/plugins/comicpress-manager/" target="_new">ComicPress Manager</a> is built for the <a href="http://www.mindfaucet.com/comicpress/" target="_new">ComicPress</a> theme', 'comicpress-manager') ?> |
      <?php _e('Copyright 2008-2009 <a href="mailto:john@coswellproductions.com?Subject=ComicPress Manager Comments">John Bintz</a>', 'comicpress-manager') ?> |
      <?php _e('Released under the GNU GPL', 'comicpress-manager') ?> |
      <?php echo $version_string ?>
      <?php _e('<a href="http://bugs.comicpress.org/index.php?project=2">Report a Bug</a>', 'comicpress-manager') ?> |
      <?php _e('Uses the <a target="_new" href="http://www.dynarch.com/projects/calendar/">Dynarch DHTML Calendar Widget</a>', 'comicpress-manager') ?>
    </div>
  </div>
<?php }

?>