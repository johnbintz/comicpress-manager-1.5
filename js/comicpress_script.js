/**
 * hide/show the new post holder box depending on the status of the checkbox.
 */
function hide_show_checkbox_holder(which, reverse) {
  if (reverse !== true) { reverse = false; }
  ($(which + '-checkbox').checked !== reverse) ? new Effect.Appear(which + '-holder') : new Effect.BlindUp(which + '-holder');
}

function setup_hide_show_checkbox_holder(which) {
  Event.observe(which + '-checkbox', 'click', function() { hide_show_checkbox_holder(which) });
  hide_show_checkbox_holder(which);
}

function hide_show_div_on_checkbox(div, checkbox, flip_behavior) {
  if ($(checkbox) && $(div)) {
    ok = (flip_behavior) ? !$(checkbox).checked : $(checkbox).checked;
    (ok) ? new Effect.Appear(div) : new Effect.BlindUp(div);
  }
}

/**
 * Show the preview image for deleting an image.
 */
function change_image_preview() {
  var which = $F('delete-comic-dropdown');
  $('image-preview').innerHTML = '<img src="' + which + '" width="420" />';
}

var current_file_index = 0;
var current_file_upload_count = 0;

var on_change_file_upload_count = null;

/**
 * Add a file upload field.
 */
function add_file_upload() {
  var field  = "<div class=\"upload-holder\" id=\"upload-holder-" + current_file_index + "\">";
      field += messages['add_file_upload_file'] + "<input size=\"35\" type=\"file\" name=\"upload-" + current_file_index + "\" />";
      field += " [<a href=\"#\" onclick=\"remove_file_upload('" + current_file_index + "');\">" + messages['add_file_upload_remove'] + "</a>]";
      field += "</div>";
  Element.insert('multiple-file-upload', { bottom: field });
  current_file_index++;
  current_file_upload_count++;

  if (on_change_file_upload_count) { on_change_file_upload_count(current_file_upload_count); }
}

function remove_file_upload(which) {
  Element.remove('upload-holder-' + which);
  current_file_upload_count--;

  if (on_change_file_upload_count) { on_change_file_upload_count(current_file_upload_count); }
}

// page startup code
function prepare_comicpress_manager() {
  if ($('multiple-new-post-checkbox')) {
    setup_hide_show_checkbox_holder("multiple-new-post");
    add_file_upload();

    hide_show_div_on_checkbox('override-title-holder', 'override-title');
    hide_show_div_on_checkbox('thumbnail-write-holder', 'no-thumbnails', true);

    var add_to_tags = function(href) {
      var all_tags = [];
      if (!$F('tags').empty()) {
        all_tags = $F('tags').replace(new RegExp("s*\,\s*"), ",").split(",");
      }

      if (all_tags.indexOf(href.innerHTML) == -1) {
        all_tags.push(href.innerHTML);
      }

      $('tags').value = all_tags.join(",");
    }

    $$('a.tag').each(function(href) {
      Event.observe(href, 'click', function(e) {
        Event.stop(e);
        add_to_tags(href);
      });
    });
  }

  var handle_show_rebuild_thumbnails = function(e) {
    (($F('overwrite-existing-file-choice') != "") && ($F('upload-destination') == "comic")) ? $('rebuild-thumbnails').show() : $('rebuild-thumbnails').hide();
  };

  if ($('overwrite-existing-file-choice')) {
    Event.observe('overwrite-existing-file-choice', 'change', handle_show_rebuild_thumbnails);
    handle_show_rebuild_thumbnails();
  }

  if ($('replace-comic-rebuild-thumbnails') && $('thumbnails')) {
    Event.observe($('replace-comic-rebuild-thumbnails'), 'click', function(e) {
      $('thumbnails').checked = $('replace-comic-rebuild-thumbnails').checked;
    });
  }

  if ($('upload-destination')) {
    var toggle_upload_destination_holder = function() {
      if ($F('overwrite-existing-file-choice') == "") {
        if ($('upload-destination').options[$('upload-destination').selectedIndex].value == "comic") {
          new Effect.Appear('upload-destination-holder');
        } else {
          new Effect.BlindUp('upload-destination-holder');
        }
      } else {
        new Effect.BlindUp('upload-destination-holder');
      }
      handle_show_rebuild_thumbnails();
    };
    Event.observe('upload-destination', 'change', toggle_upload_destination_holder);
    toggle_upload_destination_holder();

    on_change_file_upload_count = function(count) {
      if (count == 1) {
        new Effect.Appear('specify-date-holder');
        new Effect.Appear('overwrite-existing-holder');
      } else {
        new Effect.BlindUp('specify-date-holder');
        new Effect.BlindUp('overwrite-existing-holder');
        toggle_upload_destination_holder();
      }
    }

    if ($('overwrite-existing-file-choice')) {
      Event.observe('overwrite-existing-file-choice', 'change', function() {
        toggle_upload_destination_holder();
      });
    }
  }

  if ($('count-missing-posts-clicker')) {
    hide_show_div_on_checkbox('override-title-holder', 'override-title');
    hide_show_div_on_checkbox('thumbnail-write-holder', 'no-thumbnails', true);

    Event.observe('count-missing-posts-clicker', 'click', function() {
      $('missing-posts-display').innerHTML = "..." + messages['count_missing_posts_counting'] + "...";

      new Ajax.Request(ajax_request_uri,
                       {
                         method: 'post',
                         parameters: {
                           action: "count-missing-posts"
                         },
                         onSuccess: function(transport) {
                           if (transport.headerJSON) {
                             $('missing-posts-display').innerHTML = transport.headerJSON.missing_posts;
                           } else {
                             $('missing-posts-display').innerHTML = messages['count_missing_posts_none_missing'];
                           }
                         },
                         onFailure: function(transport) {
                           $('missing-posts-display').innerHTML = messages['failure_in_counting_posts'];
                         }
                       }
                      );
      return false;
    });
  }

  if ($('image-preview')) { change_image_preview(); }
}