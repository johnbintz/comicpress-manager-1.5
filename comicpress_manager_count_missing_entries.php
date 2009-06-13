<?php
  global $blog_id;

  if (!function_exists('add_action')) {
    require_once("../../../wp-config.php");
  }

  if (WP_ADMIN) {
    require_once('comicpress_manager_config.php');
    require_once('classes/ComicPressManager.php');
  
    $comicpress_manager = new ComicPressManager();

    $comicpress_manager->read_information_and_check_config();

    if (isset($_REQUEST['blog_id']) && function_exists('switch_to_blog')) {
      switch_to_blog((int)$_REQUEST['blog_id']);
    }

    // TODO: handle different comic categories differently, this is still too geared
    // toward one blog/one comic...
    $all_post_dates = array();

    foreach ($comicpress_manager->query_posts() as $comic_post) {
      $all_post_dates[] = date(CPM_DATE_FORMAT, strtotime($comic_post->post_date));
    }
    $all_post_dates = array_unique($all_post_dates);

    ob_start();
    $missing_comic_count = 0;
    foreach ($comicpress_manager->read_comics_folder() as $comic_file) {
      $comic_file = pathinfo($comic_file, PATHINFO_BASENAME);
      if (($result = $comicpress_manager->breakdown_comic_filename($comic_file)) !== false) {
        if (!in_array($result['date'], $all_post_dates)) {
          if (($post_hash = $comicpress_manager->generate_post_hash($result['date'], 
                              $result['converted_title'],
                              array('in-comic-category' => array($comicpress_manager->properties['comiccat'])))) !== false) {
            $missing_comic_count++;
          }
        }
      }
    }

    header("X-JSON: {missing_posts: ${missing_comic_count}}");
    ob_end_flush();
  }
?>
