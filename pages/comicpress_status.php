<?php

/**
 * The generate status dialog.
 */
function cpm_manager_status() {
  global $comicpress_manager, $comicpress_manager_admin;

  $comicpress_manager->need_calendars = true;

  if ($comicpress_manager->get_subcomic_directory() !== false) {
    $comicpress_manager->messages[] = sprintf(__("<strong>Reminder:</strong> You are managing the <strong>%s</strong> comic subdirectory.", 'comicpress-manager'), get_cat_name(get_option('comicpress-manager-manage-subcomic')));
  }

  if ($comicpress_manager->get_cpm_option('cpm-skip-checks') != 1) {
    if (!function_exists('get_comic_path')) {
      $comicpress_manager->warnings[] =  __('<strong>It looks like you\'re running an older version of ComicPress.</strong> Storyline, hovertext, and transcript are fully supported in <a href="http://comicpress.org/">ComicPress 2.7</a>. You can use hovertext and transcripts in earlier themes by using <tt>get_post_meta($post->ID, "hovertext", true)</tt> and <tt>get_post_meta($post->ID, "transcript", true)</tt>.', 'comicpress-manager');
    }
  }

  ob_start(); ?>
  
  <h2 style="padding-right:0;"><?php _e("Bulk Edit", 'comicpress-manager') ?></h2>
  <p><strong>ComicPress-related information, such as transcripts and Storyline categories for posts, and thumbnail regeneration, can be bulk edited here.</strong> To edit post-specific information, such as title and publishing date, use <strong><a href="edit.php">Edit Posts</a></strong>.

  <?php

  $data_by_date = array();
  $dates_per_page = 15;

  foreach ($comicpress_manager->comic_files as $comic_filepath) {
    $comic_file = pathinfo($comic_filepath, PATHINFO_BASENAME);
    if (($result = $comicpress_manager->breakdown_comic_filename($comic_file)) !== false) {
      $timestamp = strtotime($result['date']);
      $comic_date = date("Y-m-d", $timestamp);
      if (!isset($data_by_date[$comic_date])) {
        $data_by_date[$comic_date] = array();
      }

      $comic_info = array(
        'type' => 'comic',
        'timestamp' => $timestamp,
        'comic_file' => $comic_file,
        'file_title' => $result['converted_title'],
        'comic_uri' => $comicpress_manager->build_comic_uri($comic_filepath, CPM_DOCUMENT_ROOT)
      );

      if (count($thumbnails_found = $comicpress_manager_admin->find_thumbnails_by_filename($comic_filepath)) > 0) {
        foreach ($thumbnails_found as $thumb_type => $thumb_filename) {
          $comic_info["thumbnails_found_${thumb_type}"] = $comicpress_manager->build_comic_uri(CPM_DOCUMENT_ROOT . $thumb_filename, CPM_DOCUMENT_ROOT);
        }
      }

      $icon_file_to_use = $comic_filepath;
      foreach (array('rss', 'archive') as $type) {
        if (isset($thumbnails_found[$type])) { $icon_file_to_use = CPM_DOCUMENT_ROOT . $thumbnails_found[$type]; }
      }
      $comic_info['icon_uri'] = $comicpress_manager->build_comic_uri($icon_file_to_use, CPM_DOCUMENT_ROOT);

      $data_by_date[$comic_date][] = $comic_info;
    }
  }

  foreach ($comicpress_manager->query_posts() as $comic_post) {
    $ok = true;
    if ($comicpress_manager->get_subcomic_directory() !== false) {
      $ok = in_array(get_option('comicpress-manager-manage-subcomic'), wp_get_post_categories($comic_post->ID));
    }

    if ($ok) {
      $timestamp = strtotime($comic_post->post_date);
      $post_date = date("Y-m-d", $timestamp);
      if (!isset($data_by_date[$post_date])) {
        $data_by_date[$post_date] = array();
      }

      $post_info = array(
        'type' => 'post',
        'timestamp' => $timestamp,
        'post_id' => $comic_post->ID,
        'post_title' => $comic_post->post_title,
        'post_object' => (array)$comic_post
      );

      $data_by_date[$post_date][] = $post_info;
    }
  }

  krsort($data_by_date);

  $all_months = array();
  foreach (array_keys($data_by_date) as $date) {
    list($year, $month, $day) = explode("-", $date);
    $key = "${year}-${month}";

    if (!isset($all_months[$key])) {
      $all_months[$key] = date("F Y", strtotime($date));
    }
  }

  krsort($all_months);

  if (isset($_POST['dates'])) {
    if ($_POST['dates'] != -1) {
      $new_data_by_date = array();
      foreach ($data_by_date as $date => $data) {
        if (strpos($date, $_POST['dates']) === 0) {
          $new_data_by_date[$date] = $data;
        }
      }
      $data_by_date = $new_data_by_date;
    }
  }

  if (!isset($_GET['paged'])) {
    $_GET['paged'] = 1;
  }

  $page_links = paginate_links( array(
       'base' => add_query_arg( 'paged', '%#%' ),
       'format' => '',
       'prev_text' => __('&laquo;'),
       'next_text' => __('&raquo;'),
       'total' => ceil(count($data_by_date) / $dates_per_page),
       'current' => $_GET['paged']
  ));

  $total_data_by_date = count($data_by_date);

  $data_by_date = array_slice($data_by_date, ($_GET['paged'] - 1) * $dates_per_page, $dates_per_page);

  extract($comicpress_manager->normalize_storyline_structure());
  $comic_categories = array();
  foreach ($category_tree as $node) { $comic_categories[] = end(explode("/", $node)); }

  $thumbnail_writes = $comicpress_manager->get_thumbnails_to_generate();

  if ($total_data_by_date > 0) {
    $displaying_num_content = sprintf(__("Displaying %d-%d of %d", 'comicpress-manager'),
                                      (($_GET['paged'] - 1) * $dates_per_page) + 1,
                                      min($total_data_by_date, (($_GET['paged'] - 1) * $dates_per_page) + $dates_per_page),
                                      $total_data_by_date);
  } else {
    $displaying_num_content = __('No items to display', 'comicpress-manager');
  }

  ?>

  <style type="text/css">
    .storyline-checklist {
      overflow-y: scroll;
      border: solid #ddd 1px;
      height: 12em
    }

    #wpbody-content .bulk-edit-row-post .inline-edit-col-right {
      width: 42%
    }
  </style>
  <div id="table-holder">
    <form id="bulk-form" action="" method="post">
      <input type="hidden" name="action" value="batch-processing" />
      <div class="tablenav">
        <div class="alignleft actions">
          <select id="bulk-action" name="bulk-action">
            <option selected value="-1">Bulk Actions</option>
            <option value="individual">Submit Individual Changes</option>
            <option value="edit">Edit Selected</option>
            <option value="delete">Delete Selected</option>
            <option value="import">Create Posts for Selected Files</option>
            <option value="regen-thumbs">Regenerate Thumbs</option>
          </select>
          <input id="doaction" class="button-secondary action" type="submit" value="Apply" />
          <select id="dates" name="dates">
            <option selected value="-1">Show all dates</option>
            <?php foreach ($all_months as $key => $date) { ?>
              <option value="<?php echo $key ?>"<?php echo ($key == $_POST['dates']) ? " selected" : "" ?>><?php echo $date ?></option>
            <?php } ?>
          </select>
          <input id="dofilter" class="button-secondary action" type="submit" value="Filter" />
        </div>
        <div class="tablenav-pages">
          <span class="displaying-num"><?php echo $displaying_num_content ?></span>
          <?php echo $page_links ?>
        </div>
      </div>
      <table class="widefat fixed" id="status-table">
        <thead>
          <tr>
            <th class="check-column" width="3%">
              <input type="checkbox" name="toggle-all" class="batch-all" value="yes" />
            </th>
            <th width="12%"><?php _e("Date", 'comicpress-manager') ?></th>
            <th width="34%"><?php _e("Object Info", 'comicpress-manager') ?></th>
            <th width="61%"><?php _e("Operations", 'comicpress-manager') ?></th>
          </tr>
        </thead>
        <tfoot>
          <tr>
            <th class="check-column">
              <input type="checkbox" name="toggle-all" class="batch-all" value="yes" />
            </th>
            <th><?php _e("Date", 'comicpress-manager') ?></th>
            <th><?php _e("Object Info", 'comicpress-manager') ?></th>
            <th><?php _e("Operations", 'comicpress-manager') ?></th>
          </tr>
        </tfoot>
        <tbody>
          <tr style="display: none" id="bulk-edit" class="inline-edit-row inline-edit-row-post bulk-edit-row bulk-edit-row-post inline-editor">
            <td colspan="4">
              <fieldset class="inline-edit-col-left">
                <div class="inline-edit-col">
                  <h4>Bulk Edit Comic Posts</h4>
                  <div id="bulk-title-div">
                    <div id="bulk-titles" />
                  </div>
                </div>
              </fieldset>
              <fieldset class="inline-edit-col-center inline-edit-categories">
                <div class="inline-edit-col">
                  <span class="title inline-edit-categories-label">
                    Storyline
                  </span>
                  <div class="storyline-checklist">
                    <?php cpm_display_storyline_checkboxes($category_tree, array(), "bulk-storyline") ?>
                  </div>
                </div>
              </fieldset>
              <fieldset class="inline-edit-col-right">
                <label>
                  <span class="title" style="width: auto; margin-right: 5px">&lt;img title&gt;/hovertext</span>
                  <input type="text" name="bulk-hovertext" style="width: 100%" />
                </label>
                <label>
                  <span class="title" style="width: auto; margin-right: 5px">Transcript</span>
                  <textarea name="bulk-transcript" style="height: 7em"></textarea>
                </label>
              </fieldset>
              <p class="submit inline-edit-save">
                <a id="cancel" class="button-secondary cancel alignleft" title="Cancel" href="#">Cancel</a>
                <input class="button-primary alignright" id="bulk-edit-submit" type="submit" value="Update Comic Posts" name="bulk-comic-edit" />
                <br class="clear" />
              </p>
            </td>
          </tr>
          <?php
          $is_grey = false;
          $image_index = 0;

          foreach ($data_by_date as $date => $data) {
            $all_objects_by_type = array();

            foreach ($data as $object) {
              if (!isset($all_objects_by_type[$object['type']])) {
                $all_objects_by_type[$object['type']] = array();
              }
              $all_objects_by_type[$object['type']][] = $object;
            }
            $classes = array("data-row");
            if ($is_grey) { $classes[] = "grey"; }

            $is_first_row = true;

            foreach ($data as $object) { ?>
              <tr class="<?php echo implode(" ", $classes) ?>">
                <?php if ($is_first_row) { ?>
                  <td align="center" rowspan="<?php echo count($data) ?>">
                    <input id="batch-<?php echo $date ?>" type="checkbox" class="batch" name="batch-<?php echo $date ?>" value="yes" />
                  </td>
                  <td rowspan="<?php echo count($data) ?>"><?php echo $date ?></td>
                  <?php $is_first_row = false;
                } ?>
                <td class="<?php echo $object['type'] ?>">
                  <?php
                    switch ($object['type']) {
                      case "comic": ?>
                        <div style="overflow: hidden">
                          <div><strong>
                            Comic: <?php echo empty($object['file_title']) ? $object['comic_file'] : $object['file_title'] ?>
                          </strong></div>

                          <a href="<?php echo $object['comic_uri'] ?>"><img style="float: right; display: inline; margin-right: 5px; height: 100px" id="comic-icon-<?php echo $image_index ?>" src="<?php echo $object['icon_uri'] ?>" /></a>

                        <?php
                          $all_found = array();
                          foreach (array('rss', 'archive') as $type) {
                            if (isset($object["thumbnails_found_${type}"])) { $all_found[$type] = $object["thumbnails_found_${type}"]; }
                          }

                          if (count($all_found) > 0) { ?>
                            [
                              <?php foreach ($all_found as $type => $uri) { ?>
                                <a href="<?php echo $uri ?>"><?php echo $type ?></a>
                              <?php } ?>
                            ]
                          <?php } else { ?>
                            [ No thumbnails found ]
                          <?php }

                          $image_index++;
                        ?>
                        </div>
                        <?php break;
                      case "post":
                        if (isset($object['post_id'])) { ?>
                          <strong>Post: <?php echo $object['post_title'] ?></strong>
                          <em>(<?php echo $object['post_id'] ?>)</em><br />
                          [ <?php echo generate_view_edit_post_links($object['post_object']) ?> ]
                        <?php }

                        break;
                  } ?>
                </td>
                <td class="individual-operations <?php echo $object['type'] ?>">
                  <?php
                    switch ($object['type']) {
                      case "comic": ?>
                        <input type="hidden" name="file,<?php echo $date ?>,<?php echo $object['comic_file'] ?>" value="yes" />

                        <?php if (count($all_objects_by_type['post']) == 0) { ?>
                          <label><input type="checkbox" name="generate-post-<?php echo $object['comic_file'] ?>" value="yes" /> Generate a Post <em>(as a draft)</em></label><br />
                        <?php } ?>

                        <?php if (count($thumbnail_writes) > 0) { ?>
                          <label><input type="checkbox" name="regen-<?php echo $object['comic_file'] ?>" value="yes" /> Regenerate Thumbs</label><br />
                        <?php } ?>

                        <label><input type="checkbox" class="delete-file" name="delete-file-<?php echo $object['comic_file'] ?>" value="yes" /> Delete Comic</label><br />
                        <!-- <input type="checkbox" id="do-redate-file-<?php echo $object['comic_file'] ?>" name="do-redate-file-<?php echo $object['comic_file'] ?>" /> Move to: <input class="needs-calendar" id="redate-file-<?php echo $object['comic_file'] ?>" type="text" name="redate-file-<?php echo $object['comic_file'] ?>" value="<?php echo $date ?>" /> -->

                        <?php break;
                      case "post":
                        $post_categories = array_intersect(wp_get_post_categories($object['post_id']), $comic_categories);
                        $post_category_names = array();

                        foreach ($post_categories as $category_id) { $post_category_names[] = get_cat_name($category_id); }
                        ?>
                        <input type="hidden" name="post,<?php echo $date ?>,<?php echo $object['post_id'] ?>" value="yes" />
                        <label><input type="checkbox" class="delete-post" name="delete-post-<?php echo $object['post_id'] ?>" value="yes" /> Delete Post</label><br />
                        <!-- <input type="checkbox" id="do-redate-post-<?php echo $object['post_id'] ?>" name="do-redate-post-<?php echo $object['post_id'] ?>" /> Move to: <input type="text" class="needs-calendar" id="redate-post-<?php echo $object['post_id'] ?>" name="redate-post-<?php echo $object['post_if'] ?>" value="<?php echo $date ?>" /><br /> -->

                        <?php
                          $hovertext = get_post_meta($object['post_id'], 'hovertext', true);
                          if (!empty($hovertext)) { ?>
                          <strong>&lt;img title&gt;/hovertext:</strong> <?php echo $hovertext ?><br />
                        <?php } ?>

                         <?php
                           $transcript = get_post_meta($object['post_id'], 'transcript', true);
                           if (!empty($transcript)) { ?>
                          <strong>Transcript:</strong>
                          <div class="transcript-holder"><?php echo $transcript ?></div>
                        <?php } ?>

                        <strong>Storyline:</strong> <span class="category-names"><?php echo implode(", ", $post_category_names) ?></span> [ <a href="#" id="category-<?php echo $object['post_id'] ?>" class="category">Edit</a> ]
                        <div id="category-holder-<?php echo $object['post_id'] ?>" style="display: none">
                          <?php cpm_display_storyline_checkboxes($category_tree, $post_categories, $object['post_id']) ?>
                        </div>

                        <?php break;
                    } ?>
                </td>
              </tr>
            <?php }
            $is_grey = !$is_grey;
          } ?>
        </tbody>
      </table>
      <div class="tablenav">
        <div class="alignleft actions">
          <select id="linked-bulk-action" name="linked-bulk-action">
            <option selected value="-1">Bulk Actions</option>
            <option value="individual">Submit Individual Changes</option>
            <option value="edit">Edit Selected</option>
            <option value="delete">Delete Selected</option>
            <option value="import">Create Posts for Selected Files</option>
            <option value="regen-thumbs">Regenerate Thumbs</option>
          </select>
          <input id="linked-doaction" class="button-secondary action" type="submit" value="Apply" />
        </div>
        <div class="tablenav-pages">
          <span class="displaying-num"><?php echo $displaying_num_content ?></span>
          <?php echo $page_links ?>
        </div>
      </div>
    </form>
    <script type="text/javascript">
      Event.observe(window, 'load', function() {
        Event.observe($('linked-bulk-action'), 'change', function() {
          $('bulk-action').selectedIndex = $('linked-bulk-action').selectedIndex;
        });

        Event.observe($('bulk-action'), 'change', function() {
          $('linked-bulk-action').selectedIndex = $('bulk-action').selectedIndex;
        });

        $$('.needs-calendar').each(function(element) {
          Calendar.setup({
            inputField: element.id,
            ifFormat: "%Y-%m-%d",
            button: element.id
          });

          Event.observe(element, 'click', function(e) {
            var element = Event.element(e);
            $("do-" + element.id).checked = true;
          });
        });

        $$('a.category').each(function(element) {
          Event.observe(element, 'click', function(e) {
            Event.stop(e);
            Element.toggle("category-holder-" + Event.element(e).id.replace(/^.*\-([0-9]+)$/, '$1'));
          });
        });
      });

      $$('.batch-all').each(function(element) {
        Event.observe(element, 'change', function(e) {
          $$('.batch').each(function(b) { b.checked = element.checked; });
          $$('.batch-all').each (function(b) { b.checked = element.checked; });
        });
      });

      $$('.individual-operations input[type=checkbox]').each(function(element) {
        Event.observe(element, 'click', function(e) {
          $('bulk-action').selectedIndex = 1;
          $('linked-bulk-action').selectedIndex = 1;
        });
      });

      var ok_to_submit = false;

      Event.observe($('bulk-form'), 'submit', function(e) {
        return ok_to_submit;
      });

      Event.observe($('bulk-edit-submit'), 'click', function(e) {
        ok_to_submit = true;
        $('bulk-form').submit();
      });

      Event.observe($('cancel'), 'click', function(e) {
        Event.stop(e);
        $('bulk-edit').hide();
        $('bulk-action').selectedIndex = 0;
      });

      Event.observe($('dofilter'), 'click', function(e) {
        Event.stop(e);
        $('bulk-action').selectedIndex = 0;
        ok_to_submit = true;
        $('bulk-form').submit();
      });

      ['doaction', 'linked-doaction'].each(function(which) {
        Event.observe($(which), 'click', function(e) {
          Event.stop(e);

          switch ($F('bulk-action')) {
            case -1:
            case "-1":
              break;
            case "edit":
              $('bulk-titles').innerHTML = "";
              var any_checked = false;
              $$('.batch').each(function(element) {
                if (element.checked) {
                  any_checked = true;
                  /\-(.*)$/.test(element.id);
                  var date = RegExp.$1;

                  var node = Builder.node("div", [
                    Builder.node("a", { id: date, className: "ntdelbutton", title: "Remove From Bulk Edit" }, [ "X" ]),
                    date
                  ]);

                  $('bulk-titles').insert(node);

                  Event.observe(node, 'click', function(evt) {
                    var node = Event.element(evt);
                    $('batch-' + node.id).checked = false;
                    Element.remove(node.parentNode);

                    var any_checked = false;
                    $$('.batch').each(function(n) {
                      if (n.checked) { any_checked = true; }
                    });

                    if (!any_checked) { $('bulk-edit').hide(); }
                  });
                }
              });

              if (any_checked) { $('bulk-edit').show(); }

              break;
            case "delete":
              if (confirm("<?php _e('You are about to delete the selected posts and comic files. Are you sure?', 'comicpress-manager') ?>")) {
                ok_to_submit = true;
                $('bulk-form').submit();
              }
              break;
            default:
              ok_to_submit = true;
              $('bulk-form').submit();
              break;
          }
        });
      });
    </script>
  </div>

  <?php

  $activity_content = ob_get_clean();

  cpm_wrap_content($help_content, $activity_content, false);
}

?>