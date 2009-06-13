<?php

/**
 * The import dialog.
 */
function cpm_manager_import() {
  global $comicpress_manager;

  if ($comicpress_manager->get_subcomic_directory() !== false) {
    $comicpress_manager->messages[] = sprintf(__("<strong>Reminder:</strong> You are managing the <strong>%s</strong> comic subdirectory.", 'comicpress-manager'), get_cat_name(get_option('comicpress-manager-manage-subcomic')));
  }

  if ($comicpress_manager->get_cpm_option('cpm-skip-checks') != 1) {
    if (!function_exists('get_comic_path')) {
      $comicpress_manager->warnings[] =  __('<strong>It looks like you\'re running an older version of ComicPress.</strong> Storyline, hovertext, and transcript are fully supported in <a href="http://comicpress.org/">ComicPress 2.7</a>. You can use hovertext and transcripts in earlier themes by using <tt>get_post_meta($post->ID, "hovertext", true)</tt> and <tt>get_post_meta($post->ID, "transcript", true)</tt>.', 'comicpress-manager');
    }
  }

  ob_start(); ?>
    <p>
      <?php _e("<strong>Create missing posts for uploaded comics</strong> is for when you upload a lot of comics to your comic folder and want to generate generic posts for all of the new comics, or for when you're migrating from another system to ComicPress.", 'comicpress-manager') ?>
    </p>

    <p>
      <?php
        $link_text = __("Bulk Edit page", 'comicpress-manager');
        $link = "<a href=\"?page=" . plugin_basename(realpath(dirname(__FILE__) . '/../comicpress_manager_admin.php')) . "-status\">${link_text}</a>";

        printf(__("<strong>Generating thumbnails on an import is a slow process.</strong>  Some Webhosts will limit the amount of time a script can run.  If your import process is failing with thumbnail generation enabled, disable thumbnail generation, perform your import, and then visit the %s to complete the thumbnail generation process.", 'comicpress-manager'), $link);
      ?>
    </p>
  <?php $help_content = ob_get_clean();

  ob_start(); ?>
  
  <h2 style="padding-right:0;"><?php _e("Create Missing Posts For Uploaded Comics", 'comicpress-manager') ?></h2>
  <h3>&mdash; <?php _e("acts as a batch import process", 'comicpress-manager') ?></h3>

  <div id="import-count-information">
    <?php
      if ($comicpress_manager->import_safe_exit === true) {
        _e("<strong>You are in the middle of an import operation.</strong> To continue, click the button below:", 'comicpress-manager');

        ?>
          <form action="" method="post">
            <?php foreach ($_POST as $key => $value) {
              if (is_array($value)) {
                foreach ($value as $subvalue) { ?>
                  <input type="hidden" name="<?php echo $key ?>[]" value="<?php echo $subvalue ?>" />
                <?php }
              } else { ?>
                <input type="hidden" name="<?php echo $key ?>" value="<?php echo $value ?>" />
              <?php }
            } ?>
            <input type="submit" class="button" value="Continue Creating Posts" />
          </form>
        <?php

      } else {
        $execution_time = ini_get("max_execution_time");
        $max_posts_imported = (int)($execution_time / 2);

        if ($execution_time == 0) {
          _e("<strong>Congratulations, your <tt>max_execution_time</tt> is 0</strong>. You'll be able to import all of your comics in one import operation.", 'comicpress-manager');
        } else {
          if ($max_posts_imported == 0) {
            _e("<strong>Something is very wrong with your configuration!.</strong>", 'comicpress-manager');
          } else {
            printf(__("<strong>Your <tt>max_execution_time</tt> is %s</strong>. You'll be able to safely import %s comics in one import operation.", 'comicpress-manager'), $execution_time, $max_posts_imported);
          }
        }
      }
    ?>
  </div>

  <table class="form-table">
    <tr>
      <th scope="row">
        <?php _e("Count the number of missing posts", 'comicpress-manager') ?>
      </th>
      <td>
        <a href="#" onclick="return false" id="count-missing-posts-clicker"><?php _e("Click here to count", 'comicpress-manager') ?></a> (<?php _e("may take a while", 'comicpress-manager') ?>): <span id="missing-posts-display"></span>
      </td>
    </tr>
  </table>

  <div id="create-missing-posts-holder">
    <form onsubmit="$('submit').disabled=true" action="" method="post" style="margin-top: 10px">
      <input type="hidden" name="action" value="create-missing-posts" />

      <?php cpm_post_editor(435, true) ?>

      <table class="form-table">
        <tr>
          <td align="center">
            <input class="button" type="submit" id="submit" value="<?php _e("Create posts", 'comicpress-manager') ?>" />
          </td>
        </tr>
      </table>
    </form>
  </div>

  <?php

  $activity_content = ob_get_clean();

  cpm_wrap_content($help_content, $activity_content);
}

?>