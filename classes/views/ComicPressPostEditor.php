<?php

class ComicPressPostEditor extends ComicPressView {
  function ComicPressPostEditor($width = 435, $is_import = false) {
    global $comicpress_manager;

    if (!is_wp_error(get_category($comicpress_manager->properties['comiccat']))) {
      $this->form_titles_and_fields = array();
      
      $this->_storyline_setup();
      
      if ($comicpress_manager->get_subcomic_directory() !== false) {
        $this->post_categories = array(get_option('comicpress-manager-manage-subcomic'));
      } else {
        if ($comicpress_manager->get_cpm_option('cpm-default-comic-category-is-last-storyline') == 1) {
          $this->post_categories = array(end(explode("/", end($this->category_tree))));
        } else {
          $this->post_categories = array(end(explode("/", reset($this->category_tree))));
        }
      }
      
      $this->width = $width;
      $this->is_import = $is_import;
    }
  }
  
  function render() {
    global $comicpress_manager;
    
    if (is_null(get_category($comicpress_manager->properties['comiccat']))) { ?>
      <p><strong>You don't have a comics category defined!</strong> Go to the
      <a href="?page=<?php echo plugin_basename(__FILE__) ?>-config">ComicPress Config</a> screen and choose a category.
      <?php return;
    }

    $this->category_checkboxes = $this->_generate_additional_categories_checkboxes();
    
    if ($comicpress_manager->scale_method != false) {
      $this->thumbnail_writes = $comicpress_manager->get_thumbnails_to_generate();
    }

    $this->all_tags = get_tags();

    if (function_exists('wp_tiny_mce')) { wp_tiny_mce(); }

    include($this->_partial_path('table'));
  }
}

?>