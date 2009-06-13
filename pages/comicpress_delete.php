<?php
 
 /**
 * The delete dialog.
 */
function cpm_manager_delete() {
  global $comicpress_manager;

  $help_content = __("<p><strong>Delete a comic file and the associated post, if found</strong> lets you delete a comic file and the post that goes with it.  Any thumbnails associated with the comic file will also be deleted.</p>", 'comicpress-manager');

  ob_start(); ?>
  
  <h2 style="padding-right:0;"><?php _e("Delete A Comic File &amp; Post (if found)", 'comicpress-manager') ?></h2>

  <?php if (count($comicpress_manager->comic_files) > 0) { ?>
    <form onsubmit="$('submit').disabled=true" action="" method="post" onsubmit="return confirm('<?php _e("Are you sure?", 'comicpress-manager') ?>')">
      <input type="hidden" name="action" value="delete-comic-and-post" />

      <?php _e("Comic to delete:", 'comicpress-manager') ?><br />
        <select style="width: 445px" id="delete-comic-dropdown" name="comic" align="absmiddle" onchange="change_image_preview()">
          <?php foreach ($comicpress_manager->comic_files as $file) { ?>
            <option value="<?php echo $comicpress_manager->build_comic_uri($file, CPM_DOCUMENT_ROOT) ?>"><?php echo pathinfo($file, PATHINFO_BASENAME) ?></option>
          <?php } ?>
        </select><br />
      <div id="image-preview" style="text-align: center"></div>
      <p>
        <?php _e("<strong>NOTE:</strong> If more than one possible post is found, neither the posts nor the comic file will be deleted.  ComicPress Manager cannot safely resolve such a conflict.", 'comicpress-manager') ?>
      </p>
      <div style="text-align: center">
        <input class="button" type="submit" id="submit" value="<?php _e("Delete comic and post", 'comicpress-manager') ?>" />
      </div>
    </form>
  <?php } else { ?>
    <p><?php _e("You haven't uploaded any comics yet.", 'comicpress-manager') ?></p>
  <?php } ?>

  <?php

  $activity_content = ob_get_clean();

  cpm_wrap_content($help_content, $activity_content);
}

?>