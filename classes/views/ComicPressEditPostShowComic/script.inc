<script type="text/javascript">
  function show_comic() {
    if ($('comic-icon').offsetWidth > $('comic-icon').offsetHeight) {
      $('preview-comic').width = 400;
    } else {
      $('preview-comic').height = 400;
    }
    Element.clonePosition('comic-hover', 'comic-icon', { setWidth: false, setHeight: false, offsetTop: -((Element.getDimensions('comic-hover')['height'] - Element.getDimensions('comic-icon')['height'])/2) });
    $('comic-hover').show();
  }

  function hide_comic() { $('comic-hover').hide(); }

  var all_comic_categories = [ <?php echo implode(",", $this->comic_categories) ?> ];
  var storyline_enabled = <?php echo (get_option('comicpress-enable-storyline-support') == 1) ? 'true' : 'false' ?>;

  Event.observe(window, 'load', function() {
    $('post').encoding = "multipart/form-data";

    if (storyline_enabled) {
      $$('div#categories-all input').each(function(i) {
        if (all_comic_categories.indexOf(Number(i.value)) != -1) { i.disabled = true; }
      });
    }
  });
</script>
