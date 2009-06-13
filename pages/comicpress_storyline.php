<?php

/**
 * The Storyline Structure editor
 */
function cpm_manager_storyline() {
  global $comicpress_manager;

  extract($comicpress_manager->normalize_storyline_structure());

  $category_tree_javascript = array();
  $category_names = array();
  foreach ($category_tree as $node) {
    $category_tree_javascript[] = "'${node}'";

    $parts = explode("/", $node);
    $category = get_category(end($parts));

    $category_names[] = '"' . $category->name . '"';
  }

  $help_content = __("<p><strong>Set Up Storyline Structure</strong> lets you manage your site's categories so that they can be used for storyline management. You would name them with the <strong>names of the logical divisions of your story: Chapter 1, Volume 2, etc.</strong></p>", 'comicpress-manager');

  $help_content .= __("<p>If you want to <strong>change the parent/child relationships of categories</strong>, you'll have to do that at <a href=\"categories.php\"><strong>Manage -> Categories</strong></a> for now.</p>", 'comicpress-manager');

  $help_content .= __("<p>If you <strong>added or deleted categories, or changed parent/child relationships, via Manage -> Categories</strong>, you may need to <strong>rearrange your internal story structure</strong>, otherwise your navigation will be incorrect.</p>", 'comicpress-manager');

  if (!function_exists('get_comic_path')) {
    $comicpress_manager->warnings[] = __("<strong>It looks like you're running an older version of ComicPress.</strong> Storylines are only fully supported in <a href=\"http://comicpress.org/\" target=\"_new\">ComicPress 2.7</a> or with certain theme modifications.", 'comicpress-manager');
  }

  ob_start();
  
  ?>
  
  <h2 style="padding-right:0;"><?php _e("Set Up Storyline Structure", 'comicpress-manager') ?></h2>
  <h3>&mdash; <?php _e("remember, these are all categories and their details can be edited in Posts -> Categories", 'comicpress-manager') ?></h3>

  <form action="" method="post">
    <input type="hidden" name="action" value="build-storyline-schema" />
    <input type="hidden" name="order" id="order" value="" />
    <input type="hidden" name="original-categories" value="<?php echo implode(",", $category_tree) ?>" />
    <div style="margin-bottom: 5px">
      <label>
        <input type="checkbox"
               id="enable-storyline-support"
               name="enable-storyline-support"
               value="yes"
               <?php echo (get_option('comicpress-enable-storyline-support') == 1) ? "checked" : "" ?> /> Enable Storyline Support
      </label>
    </div>

    <div id="storyline-holder"
         style="margin-bottom: 10px<?php echo (get_option('comicpress-enable-storyline-support') == 1) ? "" : "; display: none" ?>">
      <div style="text-align: center"><?php _e("Loading...", 'comicpress-manager') ?></div>
    </div>
    <input type="submit" class="button" value="<?php _e("Save Structure and Modify Categories", 'comicpress-manager') ?>" /> | <a href="">Cancel Changes</a>
  </form>
  <script type="text/javascript">
    var max_id = <?php echo $max_id ?>;

    var tree = [ <?php echo implode(", ", $category_tree_javascript) ?> ];
    var category_names = [ <?php echo implode(", ", $category_names) ?> ];

    var show_top_category = <?php echo (get_option('comicpress-storyline-show-top-category') == 1) ? "true" : "false" ?>;

    function set_order() {
      var order = [];
      $$('#storyline-holder div').each(function(node) {
        if (get_cat_path(node.id) != "") {
          order.push(get_cat_path(node.id));
        }
      });
      $('order').value = order.join(",");
    }

    function new_category(parent) {
      max_id++;
      var parts = parent.split("/");
      var target_id = parent + "/" + max_id;

      return { target_id: target_id, holder: generate_cat_editor(target_id, "", true) };
    }

    function generate_cat_editor(target_id, name, ok_to_delete) {
      var commands;
      if (target_id.split("/").length == 2) {
        var input_parameters = { type: "checkbox", name: "show-top-category", value: "yes" };
        if (show_top_category) { input_parameters['checked'] = true; };
        commands = Builder.node("div", [
          "Create: [ ",
          Builder.node("a", { href: "#", className: "child", id: "child-" + target_id }, [ "Child" ]),
          "|",
          Builder.node("label", [
            Builder.node("input", input_parameters),
            " Show This Category on Site ",
          ]),
          " ]"
        ]);
      } else {
        commands = Builder.node("div", [
          "Create: [ ",
          Builder.node("a", { href: "#", className: "sibling", id: "sibling-" + target_id }, [ "Sibling" ]),
          " | ",
          Builder.node("a", { href: "#", className: "child", id: "child-" + target_id }, [ "Child" ]),
          " ] Move: [ ",
          Builder.node("a", { href: "#", className: "up", id: "up-" + target_id }, [ "Up" ]),
          " | ",
          Builder.node("a", { href: "#", className: "down", id: "down-" + target_id }, [ "Down" ]),
          " ] ",
          Builder.node("a", { href: "#", className: "delete", id: "delete-" + target_id }, [ "Delete" ])
        ]);
      }

      return Builder.node('div', { className: "holder", id: "holder-" + target_id, style: "margin-left: " + (target_id.split("/").length - 2) * 30 + "px" }, [
        Builder.node("input", { type: "text", value: name, name: target_id, size: 40 }),
        commands
      ]);
    }

    var css_search_path = "#storyline-holder .holder";

    function do_move(id, is_down) {
      var start_parts = id.split('/');
      var end_parts;
      var start_node = $('holder-' + id);
      var end_node = $('holder-' + id);
      while (end_node) {
        end_node = is_down ? end_node.next(css_search_path) : end_node.previous(css_search_path);
        if (end_node) {
          end_parts = get_cat_path(end_node.id).split("/");
          if (end_parts.length == start_parts.length) {
            break;
          }
        }
      }

      if (end_node) {
        var ok = true;
        for (i = 0; i < start_parts.length - 1; ++i) {
          if (start_parts[i] != end_parts[i]) { ok = false; break; }
        }

        if (ok) {
          var child_node_lists = {};
          [ [ "start", start_node ], [ "end", end_node ] ].each(function(info) {
            var child_node_list = [];
            var child_node = info[1];
            while (child_node) {
              child_node_list.push(child_node)
              child_node = child_node.next(css_search_path);
              if (child_node) {
                var child_parts = get_cat_path(child_node.id).split("/");
                if (child_parts.length <= start_parts.length) {
                  break;
                }
              }
            }
            child_node_lists[info[0]] = child_node_list;
          });

          if (is_down) { child_node_lists['start'].reverse(true); }

          child_node_lists['start'].each(function(node) {
            for (i = 0, il = child_node_lists['end'].length; i < il; ++i) {
              var target_swap_node = is_down ? node.next(css_search_path) : node.previous(css_search_path);

              is_down ? Element.swapWith(node, target_swap_node) : Element.swapWith(target_swap_node, node);
            }
          });
        }
      }
    }

    function set_up_handlers(id) {
      Event.observe("child-" + id, 'click', function(e) {
        Event.stop(e);
        var new_cat = new_category(id);

        $('holder-' + id).insert({ after: new_cat.holder });
        set_up_handlers(new_cat.target_id);
        set_order();
      });

      if ($("sibling-" + id)) {
        Event.observe("sibling-" + id, 'click', function(e) {
          Event.stop(e);
          var parent_id = id.replace(/\/[^\/]+$/, '');
          var new_cat = new_category(parent_id);

          var top_node = $('holder-' + id);
          do {
            var next_node = top_node.next(css_search_path);
            if (next_node) {
              if (get_cat_path(next_node.id).length > id.length) {
                top_node = next_node;
              } else {
                next_node = null;
              }
            }
          } while (next_node);

          top_node.insert({ after: new_cat.holder });
          set_up_handlers(new_cat.target_id);
          set_order();
        });

        Event.observe("up-" + id, 'click', function(e) {
          Event.stop(e);
          do_move(id, false);
          set_order();
        });

        Event.observe("down-" + id, 'click', function(e) {
          Event.stop(e);
          do_move(id, true);
          set_order();
        });
      }

      if ($("delete-" + id)) {
        Event.observe("delete-" + id, 'click', function(e) {
          Event.stop(e);
          var top_node = $('holder-' + id);
          do {
            var next_node = top_node.next(css_search_path);
            Element.remove(top_node);

            if (next_node) {
              if (get_cat_path(next_node.id).length > id.length) {
                top_node = next_node;
              } else {
                next_node = null;
              }
            }
          } while (next_node);
        });
      }
    }

    function get_cat_path(id) { return id.replace(/^.+\-([^\-]+)$/, '$1'); }

    Event.observe(window, 'load', function() {
      $('storyline-holder').innerHTML = "";
      for (i = 0, il = tree.length; i < il; ++i) {
        $('storyline-holder').insert(generate_cat_editor(tree[i], category_names[i]));
        set_up_handlers(tree[i]);
      }
      set_order();

      Event.observe($('enable-storyline-support'), 'click', function(e) {
        $('enable-storyline-support').checked ? $('storyline-holder').show() : $('storyline-holder').hide();
      });
    });

    Element.addMethods({
      swapWith: (function() {
        if ('swapNode' in document.documentElement)
          return function(element, other) {
            return $(element).swapNode($(other));
          };
        return function(element, other) {
           element = $(element);
           other = $(other);
           var next = other.nextSibling, parent = other.parentNode;
           element.parentNode.replaceChild(other, element);
           return parent.insertBefore(element, next);
        };
      })()
    });
  </script>

  <?php

  $activity_content = ob_get_clean();

  cpm_wrap_content($help_content, $activity_content);
}

?>