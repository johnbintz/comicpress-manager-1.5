<?php

//harmonious @zip @hash

function cpm_action_build_storyline_schema() {
  global $comicpress_manager;

  update_option('comicpress-enable-storyline-support', isset($_POST['enable-storyline-support']) ? 1 : 0);
  update_option('comicpress-storyline-show-top-category', isset($_POST['show-top-category']) ? 1 : 0);

  if (isset($_POST['enable-storyline-support'])) {
    $comicpress_manager->is_cpm_modifying_categories = true;

    $categories_to_create = array();
    $categories_to_rename = array();
    $category_ids_to_clean = array();

    extract($comicpress_manager->get_all_comic_categories());

    $comic_posts = $comicpress_manager->query_posts();
    $comic_posts_by_category_id = array();
    foreach ($comic_posts as $post) {
      foreach (wp_get_post_categories($post->ID) as $category) {
        if (!isset($comic_posts_by_category_id[$category])) { $comic_posts_by_category_id[$category] = array(); }
        $comic_posts_by_category_id[$category][] = $post->ID;
      }
    }

    foreach ($_POST as $field => $value) {
      $parts = explode("/", $field);
      if (($parts[0] == "0") && (count($parts) > 1)) {
        $category_id = end($parts);
        $category = get_category($category_id, ARRAY_A);
        if (!empty($category)) {
          if ($category['cat_name'] != $value) {
            $comicpress_manager->messages[] = sprintf(__('Category <strong>%1$s</strong> renamed to <strong>%2$s</strong>.', 'comicpress-manager'), $category['cat_name'], $value);
            $category['cat_name'] = $value;
            wp_update_category($category);

            $category_ids_to_clean[] = $category_id;
          }
        } else {
          $categories_to_create[$field] = $value;
        }

        if (($index = array_search($field, $category_tree)) !== false) {
          array_splice($category_tree, $index, 1);
        }
      }
    }

    if (isset($_POST['original-categories'])) {
      foreach (explode(",", $_POST['original-categories']) as $node) {
        if (!isset($_POST[$node])) {
          $category_id = end(explode("/", $node));
          $category = get_category($category_id);
          $original_cat_name = $category->cat_name;

          // ensure that we're not deleting a ComicPress category
          $ok = true;
          foreach (array('comiccat', 'blogcat') as $type) {
            if ($category_id == $comicpress_manager->properties[$type]) { $ok = false; }
          }

          // ensure that the category truly is a child of the comic category
          if ($ok) {
            $category = get_category($category_id);
            $ok = false;

            if (!is_wp_error($category)) {
              while (($category->parent != 0) && ($category->parent != $comicpress_manager->properties['comiccat'])) {
                $category = get_category($category->parent);
              }
              if ($category->parent == $comicpress_manager->properties['comiccat']) { $ok = true; }
            }
          }

          if ($ok) {
            wp_delete_category($category_id);
            $category_ids_to_clean[] = $category_id;

            $comicpress_manager->messages[] = sprintf(__('Category <strong>%s</strong> deleted.', 'comicpress-manager'), $original_cat_name);
          }
        }
      }
    }

    uksort($categories_to_create, 'cpm_sort_category_keys_by_length');

    $changed_field_ids = array();
    $removed_field_ids = array();

    $target_category_ids = array();

    foreach ($categories_to_create as $field => $value) {
      $original_field = $field;
      foreach ($changed_field_ids as $changed_field => $new_field) {
        if ((strpos($field, $changed_field) === 0) && (strlen($field) > strlen($changed_field))) {
          $field = str_replace($changed_field, $new_field, $field);
          break;
        }
      }

      $parts = explode("/", $field);
      $target_id = array_pop($parts);
      $parent_id = array_pop($parts);

      if (!category_exists($value)) {
        $category_id = wp_create_category($value, $parent_id);
        $category_ids_to_clean[] = $category_id;

        array_push($parts, $parent_id);
        array_push($parts, $category_id);
        $changed_field_ids[$original_field] = implode("/", $parts);

        $comicpress_manager->messages[] = sprintf(__('Category <strong>%s</strong> created.', 'comicpress-manager'), $value);
      } else {
        $comicpress_manager->warnings[] = sprintf(__("The category %s already exists. Please enter a new name.", 'comicpress-manager'), $value);
        $removed_field_ids[] = $field;
      }
    }

    $order = array_diff(explode(",", $_POST['order']), $removed_field_ids);
    for ($i = 0; $i < count($order); ++$i) {
      if (isset($changed_field_ids[$order[$i]])) {
        $order[$i] = $changed_field_ids[$order[$i]];
      }
    }

    // ensure we're writing sane data
    $new_order = array();
    $valid_comic_categories = array();
    foreach ($order as $node) {
      $parts = explode("/", $node);
      if (($parts[0] == "0") && (count($parts) > 1)) {
        $new_order[] = $node;
        $valid_comic_categories[] = end($parts);
      }
    }

    $comic_categories_preserved = array();
    foreach ($comic_posts as $post) {
      $categories = wp_get_post_categories($post->ID);
      if (count(array_intersect($valid_comic_categories, $categories)) == 0) {
        $all_parent_categories = array();

        foreach ($comic_posts_by_category_id as $category => $post_ids) {
          if (in_array($post->ID, $post_ids)) {
            foreach ($new_order as $node) {
              $parts = explode("/", $node);
              if ($category == end($parts)) {
                $parts = explode("/", $node);
                array_pop($parts);
                if (count($parts) > 1) { $all_parent_categories[] = implode("/", $parts); }
              }
            }
          }
        }

        if (count($all_parent_categories) > 0) {
          foreach ($all_parent_categories as $category_node) {
            if (in_array($category_node, $new_order)) {
              $categories[] = end(explode("/", $category_node));
            }
          }
        } else {
          $categories[] = $comicpress_manager->properties['comiccat'];
        }

        wp_set_post_categories($post->ID, $categories);
        $comic_categories_preserved[] = $post->ID;
      }
    }

    if (count($comic_categories_preserved) > 0) {
      $comicpress_manager->messages[] = sprintf(__("The following orphaned comic posts were placed into their original category's parent: <strong>%s</strong>"), implode(", ", $comic_categories_preserved));
    }

    $comicpress_manager->messages[] = __('Storyline structure saved.', 'comicpress-manager');
    update_option("comicpress-storyline-category-order", implode(",", $new_order));

    clean_term_cache($category_ids_to_clean, 'category');
    wp_cache_flush();
  }
}

function cpm_sort_category_keys_by_length($a, $b) {
  return strlen($a) - strlen($b);
}

?>
