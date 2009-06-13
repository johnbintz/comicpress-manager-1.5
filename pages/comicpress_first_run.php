<?php

/**
 * The main manager screen.
 */
function cpm_manager_first_run($target_page) {
  global $comicpress_manager;

  $target_page = "?page=${target_page}";

  $is_wpmu = function_exists("get_site_option");

  ob_start();

  ?>
  <h2>ComicPress Manager First Run</h2>

  <p><strong>Thank you for using ComicPress Manager.</strong> I can attempt to create your starting comic directories for you.
    <?php if (!$is_wpmu) { ?>
      I'll be creating them in <?php echo CPM_DOCUMENT_ROOT ?>.
    <?php } ?>
  </p>

  <form action="<?php echo $target_page ?>" method="post">
    <input type="hidden" name="action" value="do-first-run" />
    <input class="button" type="submit" value="Yes, try and make my comic directories" />
  </form>

  <?php if (!$is_wpmu) { ?>
    <form action="<?php echo $target_page ?>" method="post">
      <input type="hidden" name="action" value="skip-first-run" />
      <input class="button" type="submit" value="No, I'll make them myself" />
    </form>
  <?php } ?>

  <?php

  $activity_content = ob_get_clean();

  cpm_wrap_content(null, $activity_content);
}
