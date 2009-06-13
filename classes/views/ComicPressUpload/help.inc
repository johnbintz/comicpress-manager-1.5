<p>
  <strong>
    <?php _e("ComicPress Manager manages your comics and your time.", 'comicpress-manager') ?>
  </strong>
  <?php _e("It makes uploading new comics, importing comics from a non-ComicPress setup, and batch uploading a lot of comics at once, very fast and configurable.", 'comicpress-manager') ?>
</p>

<p>
  <strong>
    <?php _e("ComicPress Manager also manages yours and your Website's sanity.", 'comicpress-manager') ?>
  </strong>

  <?php printf(__("It can check for misconfigured ComicPress setups, for incorrectly-named files (remember, it's <em>%s-single-comic-title.ext</em>) and for when you might be duplicating a post. You will also be shown which comic will appear with which blog post in the Post editor.", 'comicpress-manager'), $this->example_date) ?>
</p>

<p>
  <?php printf(__("<strong>Single comic titles</strong> are generated from the incoming filename.  If you've named your file <strong>%s-my-new-years-day.jpg</strong> and create a new post for the file, the post title will be <strong>My New Years Day</strong>.  This default should handle the majority of cases.  If a comic file does not have a title, the date in <strong>MM/DD/YYYY</strong> format will be used.", 'comicpress-manager'), $this->example_real_date) ?>
</p>

<p>
  <?php _e("<strong>Upload image files</strong> lets you upload multiple comics at a time, and add a default post body for each comic.", 'comicpress-manager') ?>
  <?php if ($this->zip_extension_loaded) { ?>
    <?php _e("You can <strong>upload a Zip file and create new posts</strong> from the files contained within the Zip file.", 'comicpress-manager') ?>
  <?php } else { ?>
    <?php _e("<strong>You can't upload a Zip file</strong> because you do not have the PHP <strong>zip</strong> extension installed.", 'comicpress-manager') ?>
  <?php } ?>
</p>

<p>
  <?php _e("Has ComicPress Manager saved you time and sanity?  <strong>Donate a few bucks to show your appreciation!</strong>", 'comicpress-manager') ?>
  <span style="display: block; text-align: center">
    <form action="https://www.paypal.com/cgi-bin/webscr" method="post">
    <input type="hidden" name="cmd" value="_s-xclick">
    <input type="hidden" name="encrypted" value="-----BEGIN PKCS7-----MIIHdwYJKoZIhvcNAQcEoIIHaDCCB2QCAQExggEwMIIBLAIBADCBlDCBjjELMAkGA1UEBhMCVVMxCzAJBgNVBAgTAkNBMRYwFAYDVQQHEw1Nb3VudGFpbiBWaWV3MRQwEgYDVQQKEwtQYXlQYWwgSW5jLjETMBEGA1UECxQKbGl2ZV9jZXJ0czERMA8GA1UEAxQIbGl2ZV9hcGkxHDAaBgkqhkiG9w0BCQEWDXJlQHBheXBhbC5jb20CAQAwDQYJKoZIhvcNAQEBBQAEgYBt5XgClPZfdf9s2CHnk4Ka5NQv+Aoswm3efVANJKvHR3h4msnSWwDzlJuve/JD5aE0rP4SRLnWuc4qIhOeeAP+MEGbs8WNDEPtUopmSy6aphnIVduSstqRWWSYElK5Wij/H8aJtiLML3rVBtiixgFBbj2HqD2JXuEgduepEvVMnDELMAkGBSsOAwIaBQAwgfQGCSqGSIb3DQEHATAUBggqhkiG9w0DBwQIlFUk3PoXtLKAgdAjA3AjtLZz9ZnJslgJPALzIwYw8tMbNWvyJXWksgZRdfMw29INEcgMrSYoWNHY4AKpWMrSxUcx3fUlrgvPBBa1P96NcgKfJ6U0KwygOLrolH0JAzX0cC0WU3FYSyuV3BZdWyHyb38/s9AtodBFy26fxGqvwnwgWefQE5p9k66lWA4COoc3hszyFy9ZiJ+3PFtH/j8+5SVvmRUk4EUWBMopccHzLvkpN2WALLAU4RGKGfH30K1H8+t8E/+uKH1jt8p/N6p60jR+n7+GTffo3NahoIIDhzCCA4MwggLsoAMCAQICAQAwDQYJKoZIhvcNAQEFBQAwgY4xCzAJBgNVBAYTAlVTMQswCQYDVQQIEwJDQTEWMBQGA1UEBxMNTW91bnRhaW4gVmlldzEUMBIGA1UEChMLUGF5UGFsIEluYy4xEzARBgNVBAsUCmxpdmVfY2VydHMxETAPBgNVBAMUCGxpdmVfYXBpMRwwGgYJKoZIhvcNAQkBFg1yZUBwYXlwYWwuY29tMB4XDTA0MDIxMzEwMTMxNVoXDTM1MDIxMzEwMTMxNVowgY4xCzAJBgNVBAYTAlVTMQswCQYDVQQIEwJDQTEWMBQGA1UEBxMNTW91bnRhaW4gVmlldzEUMBIGA1UEChMLUGF5UGFsIEluYy4xEzARBgNVBAsUCmxpdmVfY2VydHMxETAPBgNVBAMUCGxpdmVfYXBpMRwwGgYJKoZIhvcNAQkBFg1yZUBwYXlwYWwuY29tMIGfMA0GCSqGSIb3DQEBAQUAA4GNADCBiQKBgQDBR07d/ETMS1ycjtkpkvjXZe9k+6CieLuLsPumsJ7QC1odNz3sJiCbs2wC0nLE0uLGaEtXynIgRqIddYCHx88pb5HTXv4SZeuv0Rqq4+axW9PLAAATU8w04qqjaSXgbGLP3NmohqM6bV9kZZwZLR/klDaQGo1u9uDb9lr4Yn+rBQIDAQABo4HuMIHrMB0GA1UdDgQWBBSWn3y7xm8XvVk/UtcKG+wQ1mSUazCBuwYDVR0jBIGzMIGwgBSWn3y7xm8XvVk/UtcKG+wQ1mSUa6GBlKSBkTCBjjELMAkGA1UEBhMCVVMxCzAJBgNVBAgTAkNBMRYwFAYDVQQHEw1Nb3VudGFpbiBWaWV3MRQwEgYDVQQKEwtQYXlQYWwgSW5jLjETMBEGA1UECxQKbGl2ZV9jZXJ0czERMA8GA1UEAxQIbGl2ZV9hcGkxHDAaBgkqhkiG9w0BCQEWDXJlQHBheXBhbC5jb22CAQAwDAYDVR0TBAUwAwEB/zANBgkqhkiG9w0BAQUFAAOBgQCBXzpWmoBa5e9fo6ujionW1hUhPkOBakTr3YCDjbYfvJEiv/2P+IobhOGJr85+XHhN0v4gUkEDI8r2/rNk1m0GA8HKddvTjyGw/XqXa+LSTlDYkqI8OwR8GEYj4efEtcRpRYBxV8KxAW93YDWzFGvruKnnLbDAF6VR5w/cCMn5hzGCAZowggGWAgEBMIGUMIGOMQswCQYDVQQGEwJVUzELMAkGA1UECBMCQ0ExFjAUBgNVBAcTDU1vdW50YWluIFZpZXcxFDASBgNVBAoTC1BheVBhbCBJbmMuMRMwEQYDVQQLFApsaXZlX2NlcnRzMREwDwYDVQQDFAhsaXZlX2FwaTEcMBoGCSqGSIb3DQEJARYNcmVAcGF5cGFsLmNvbQIBADAJBgUrDgMCGgUAoF0wGAYJKoZIhvcNAQkDMQsGCSqGSIb3DQEHATAcBgkqhkiG9w0BCQUxDxcNMDkwMTA2MDAyOTQwWjAjBgkqhkiG9w0BCQQxFgQUITTqZaXyM43N5f08PBPDuRmzzdEwDQYJKoZIhvcNAQEBBQAEgYAV0szDQPbcyW/O9pZ7jUghTRdgHbCX4RyjPzR35IrI8MrqmtK94ENuD6Xf8PxkAJ3QdDr9OvkzWOHFVrb6YrAdh+XxBsMf1lD17UbwN3XZFn5HqvoWNFxNr5j3qx0DBsCh5RlGex+HAvtIoJu21uGRjbOQQsYFdlAPHxokkVP/Xw==-----END PKCS7-----
    ">
    <input type="image" src="https://www.paypal.com/en_US/i/btn/btn_donate_SM.gif" border="0" name="submit" alt="">
    <img alt="" border="0" src="https://www.paypal.com/en_US/i/scr/pixel.gif" width="1" height="1">
    </form>
  </span>
</p>    