<?php

class ComicPressEditPostShowComic extends ComicPressView {
  function ComicPressEditPostShowComic() {
    global $post, $comicpress_manager, $comicpress_manager_admin;

    $comicpress_manager->read_comicpress_config();
    
    $this->has_comic_file = false;
    $this->in_comics_category = false;
    
    $this->thumbnails_to_generate = $comicpress_manager->get_thumbnails_to_generate();
    
    $this->_storyline_setup();
    
    if ($post->ID !== 0) {
      $this->post_categories = wp_get_post_categories($post->ID);

      $in_comics_category = (count(array_intersect($this->comic_categories, $this->post_categories)) > 0);

      $ok = true;
      if ($comicpress_manager->get_subcomic_directory() !== false) {
        $ok = in_array(get_option('comicpress-manager-manage-subcomic'), $this->post_categories);
      }

      if ($ok) {
        $post_time = time();
        foreach (array('post_date', 'post_modified', 'post_date_gmt', 'post_modified_gmt') as $time_value) {
          if (($result = strtotime($post->{$time_value})) !== false) {
            $post_time = $result; break;
          }
        }

        if (($comic = $comicpress_manager_admin->find_comic_by_date($post_time)) !== false) {
          $comic_uri = $comicpress_manager->build_comic_uri($comic);

          $this->comic_filename = preg_replace('#^.*/([^\/]*)$#', '\1', $comic_uri);
          $this->link = "<strong><a target=\"comic_window\" href=\"${comic_uri}\">{$this->comic_filename}</a></strong>";

          $date_root = substr($comic_filename, 0, strlen(date(CPM_DATE_FORMAT)));
          $this->thumbnails_found = $comicpress_manager_admin->find_thumbnails_by_filename($comic);

          $icon_file_to_use = $comic;
          foreach (array('rss', 'archive') as $type) {
            if (isset($thumbnails_found[$type])) {
              $icon_file_to_use = $this->thumbnails_found[$type];
            }
          }

          $this->icon_uri = $comicpress_manager->build_comic_uri($icon_file_to_use);

          $this->has_comic_file = true;
        }
      }
    }    
  }
  
  function render() {
    global $post, $comicpress_manager, $comicpress_manager_admin;
    
    $form_target = plugin_basename(realpath(dirname(__FILE__) . "/../comicpress_manager_admin.php"));
    
    include($this->_partial_path("script"));
    
    ?>
    <div id="comicdiv" class="postbox">
      <h3><?php _e("Comic For This Post", 'comicpress-manager') ?></h3>
      <div class="inside" style="overflow: hidden">
        <?php include($this->_partial_path("info-box")) ?>
        <?php include($this->_partial_path("edit-table")) ?>
      </div>
    </div>
  <?php }
}

?>