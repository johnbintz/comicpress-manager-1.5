<?php
  if (($result = get_option("comicpress-storyline-category-order")) !== false) {
    $categories_by_id = array();
    foreach (get_categories("hide_empty=0") as $category_object) {
      $categories_by_id[$category_object->term_id] = $category_object;
    }

    foreach (explode(",", $result) as $node) {
      $parts = explode("/", $node);
      $category_id = end($parts);
      $category = $categories_by_id[$category_id];
      $first_comic_in_category = get_terminal_post_in_category($category_id);
      $first_comic_permalink = get_permalink($first_comic_in_category->ID);
      $archive_image = get_comic_url("archive", $first_comic_in_category); ?>

      <div style="margin-left: <?php echo (count($parts) - 2) * 50 ?>px; overflow: hidden; background-color: #ddd">
        <a href="<?php echo $first_comic_permalink ?>"><img src="<?php echo $archive_image ?>" /></a>
        <h3><a href="<?php echo get_category_link($category_id) ?>"><?php echo $category->cat_name ?></a></h3>
        <p>First comic in storyline:
          <strong>
            <a href="<?php echo $first_comic_permalink ?>"><?php echo $first_comic_in_category->post_title ?></a>
          </strong>
        </p>
      </div>
    <?php }
  }
?>