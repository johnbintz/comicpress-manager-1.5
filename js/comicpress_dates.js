function pad(s, l) { s = "0000" + (s + ""); return s.substr(s.length - l, l); }

function get_date_string(date) {
  return date.getFullYear() + "-" + pad(date.getMonth() + 1, 2) + "-" + pad(date.getDate(), 2);
}

function reschedule_posts(start) {
  var start_processing = false;
  var interval = null;
  var current_date = null;
  var current_interval = 0;
  for (var i = 0, l = comic_files_keys.length; i < l; ++i) {
    if (start_processing) {
      current_date += (interval[current_interval] * 86400 * 1000);
      current_interval = (current_interval + 1) % interval.length;

      date_string = get_date_string(new Date(current_date));

      $('dates[' + comic_files_keys[i] + ']').value = date_string;
      $('holder-' + comic_files_keys[i]).style.backgroundColor = "#ddd";
    }
    if (comic_files_keys[i] == start) {
      start_processing = true;
      interval = prompt(days_between_posts_message, "7");

      if (interval !== null) {
        var all_valid = true;
        var parts = interval.split(",");
        for (var j = 0, jl = parts.length; j < jl; ++j) {
          if (!parts[j].toString().match(/^\d+$/)) { all_valid = false; break; }
        }

        if (all_valid) {
          interval = parts;
          date_parts = $F('dates[' + comic_files_keys[i] + ']').split("-");
          current_date = Date.UTC(date_parts[0], date_parts[1] - 1, date_parts[2], 2) + 86400 * 1000;
        } else {
          alert(interval + " " + valid_interval_message);
          break;
        }
      } else {
        break;
      }
    }
  }
}