<?php

class ComicPressView {
  var $_partial_path;

  function _storyline_setup() {
    global $comicpress_manager;
    
    foreach ($comicpress_manager->normalize_storyline_structure() as $key => $value) {
      $this->{$key} = $value;
    }
    
    $this->comic_categories = array();
    foreach ($this->category_tree as $node) {
      $this->comic_categories[] = end(explode("/", $node));
    }
    
    $this->post_categories = array();
  }

  function _partial_path($file) {
    if (empty($this->_partial_path)) {
      $this->_partial_path = dirname(__FILE__) . '/views/' . get_class($this) . '/';
    }
    return $this->_partial_path . $file . ".inc";
  }
  
  function _display_storyline_checkboxes($prefix = "", $root_name = 'in-comic-category') {
    foreach ($this->category_tree as $node) {
      $parts = explode("/", $node);
      $category_id = end($parts);
      $name = (empty($prefix) ? "" : "${prefix}-") . $root_name; ?>
        <div style="margin-left: <?php echo (count($parts) - 2) * 20 ?>px; white-space: nowrap">
          <label>
            <input type="checkbox"
                   name="<?php echo $name ?>[]"
                   value="<?php echo $category_id ?>" id="<?php echo $name ?>-<?php echo $category_id ?>"
                   <?php echo in_array($category_id, $this->post_categories) ? "checked=\"checked\"" : "" ?> />
            <?php echo get_cat_name($category_id) ?>
          </label>
        </div>
      <?php
    }
  }

  /**
   * Create a list of checkboxes that can be used to select additional categories.
   */
  function _generate_additional_categories_checkboxes($override_name = null) {
    global $comicpress_manager;

    $additional_categories = array();

    $invalid_ids = array($comicpress_manager->properties['blogcat']);
    foreach ($this->category_tree as $node) { $invalid_ids[] = end(explode('/', $node)); }

    foreach (get_all_category_ids() as $cat_id) {
      if (!in_array($cat_id, $invalid_ids)) {
        $category = get_category($cat_id);
        $additional_categories[strtolower($category->cat_name)] = $category;
      }
    }

    ksort($additional_categories);

    $name = (!empty($override_name)) ? $override_name : "additional-categories";
    $selected_additional_categories = explode(",", $comicpress_manager->get_cpm_option("cpm-default-additional-categories"));

    $this->category_checkboxes = array();
    if (count($additional_categories) > 0) {
      foreach ($additional_categories as $category) {
        $checked = (in_array($category->cat_ID, $selected_additional_categories) ? "checked=\"checked\"" : "");

        $this->category_checkboxes[] = "<label><input id=\"additional-" . $category->cat_ID . "\" type=\"checkbox\" name=\"${name}[]\" value=\"" . $category->cat_ID . "\" ${checked} /> " . $category->cat_name . "</label><br />";
      }
    }
    return $this->category_checkboxes;
  }

  function render_help() {
    include($this->_partial_path('help'));
  }
}

?>