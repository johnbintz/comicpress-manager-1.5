<?php

/**
 * The generate thumbnails dialog.
 */
function cpm_manager_thumbnails() {
  global $comicpress_manager;

  $help_content = __("<p><strong>Generate thumbnails</strong> lets you regenerate thumbnails for comic files.  This is useful if an import is not functioning because it is taking too long, or if you've changed your size or quality settings for thumbnails.</p>", 'comicpress-manager');

  ob_start(); ?>
  
  <h2 style="padding-right:0;"><?php _e("Generate Thumbnails", 'comicpress-manager') ?></h2>

  <?php
    $ok_to_generate_thumbs = false;
    $is_generating = array();

    if ($comicpress_manager->get_scale_method() != CPM_SCALE_NONE) {
      foreach ($comicpress_manager->thumbs_folder_writable as $type => $value) {
        if ($value) {
          if ($comicpress_manager->separate_thumbs_folder_defined[$type] !== false) {
            if ($comicpress_manager->get_cpm_option("cpm-${type}-generate-thumbnails") == 1) {
              $ok_to_generate_thumbs = true;
              $is_generating[] = sprintf(__('<strong>%1$s thumbnails</strong> that are <strong>%2$s</strong> pixels wide', 'comicpress-manager'), $type, $comicpress_manager->properties["${type}_comic_width"]);
            }
          }
        }
      }
    }

    if ($ok_to_generate_thumbs) {
      if (count($comicpress_manager->comic_files) > 0) { ?>
        <form onsubmit="$('submit').disabled=true" action="" method="post">
          <input type="hidden" name="action" value="generate-thumbnails" />

          <p><?php printf(__("You'll be generating %s.", 'comicpress-manager'), implode(__(" and ", 'comicpress-manager'), $is_generating)) ?></p>

          <?php _e("Thumbnails to regenerate (<em>to select multiple comics, [Ctrl]-click on Windows &amp; Linux, [Command]-click on Mac OS X</em>):", 'comicpress-manager') ?>
          <br />
          <select style="height: auto; width: 445px" id="select-comics-dropdown" name="comics[]" size="<?php echo min(count($comicpress_manager->comic_files), 30) ?>" multiple>
            <?php foreach ($comicpress_manager->comic_files as $file) {
              $filename = pathinfo($file, PATHINFO_BASENAME);
              $any_thumbs = false;
              foreach (array('rss', 'archive') as $type) {
                $thumb_file = str_replace($comicpress_manager->properties['comic_folder'],
                                          $comicpress_manager->properties["${type}_comic_folder"],
                                          $file);
                if (file_exists($thumb_file)) { $any_thumbs = true; break; }
              }
              ?><option value="<?php echo $filename ?>"><?php echo $filename . (($any_thumbs) ? " (*)" : "") ?></option>
            <?php } ?>
          </select>
          <div style="text-align: center">
            <input class="button" type="submit" id="submit" value="<?php _e("Generate Thumbnails for Selected Comics", 'comicpress-manager') ?>" />
          </div>
        </form>
      <?php } else { ?>
        <p><?php _e("You haven't uploaded any comics yet.", 'comicpress-manager') ?></p>
      <?php }
    } else { ?>
      <p>
        <?php _e("<strong>You either aren't able to generate any thumbnails for your comics, or you have disabled thumbnail generation.</strong> This may be caused by a configuration error. Have you set up your RSS and archive directories and <a href=\"?page=" . plugin_basename(realpath(dirname(__FILE__) . '/../comicpress_manager_admin.php')) . "-config\">configured your ComicPress theme to use them</a>? Do you have either the GD library or Imagemagick installed?", 'comicpress-manager') ?>
      </p>
    <?php }
  ?>

  <?php

  $activity_content = ob_get_clean();

  cpm_wrap_content($help_content, $activity_content);
}

?>