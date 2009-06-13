<?php

function cpm_action_write_comic_post() {
  global $comicpress_manager, $comicpress_manager_admin;

  $files_to_handle = array();
  if (isset($_FILES['upload'])) {
    if (is_uploaded_file($_FILES['upload']['tmp_name'])) {
      $files_to_handle[] = 'upload';
    }
  }

  if (count($files_to_handle) > 0) {
    list($posts_created, $duplicated_posts) = $comicpress_manager_admin->handle_file_uploads($files_to_handle);

    if (count($posts_created) > 0) {
      if (count($posts_created) == 1) {
        ?><script type="text/javascript">document.location.href = "post.php?action=edit&post=<?php echo $posts_created[0]['ID'] ?>";</script><?php
      } else {
        $comicpress_manager->warnings[] = __("<strong>More than one new post was generated!</strong> Please report this error.", 'comicpress-manager');
      }
    }

    if (count($duplicated_posts) > 0) {
      if (count($duplicated_posts) == 1) {
        ?><script type="text/javascript">document.location.href = "post.php?action=edit&post=<?php echo $duplicated_posts[0][0]['ID'] ?>";</script><?php
      } else {
        $comicpress_manager->warnings[] = __("<strong>More than one duplicate post was found!</strong> Please report this error.", 'comicpress-manager');
      }
    }

    $comicpress_manager->warnings[] = __("<strong>No posts were created, and no duplicate posts could be found!</strong>", 'comicpress-manager');
  } else {
    $comicpress_manager->warnings[] = __("<strong>You didn't upload any files!</strong>", 'comicpress-manager');
  }
}

?>