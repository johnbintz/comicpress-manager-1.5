=== ComicPress Manager ===
Contributors: johncoswell
Tags: comicpress, webcomics, management, admin, posts, plugin
Requires at least: 2.5.1
Tested up to: 2.7.1
Stable tag: 1.5
Donate link: http://www.coswellproductions.com/wordpress/wordpress-plugins/

ComicPress Manager ties in with the ComicPress theme to make managing your WordPress-hosted Webcomic easy and fast.

== Description ==

The ComicPress Manager plugin works in conjunction with an installation of [ComicPress](http://comicpress.org/), the Webcomic theme for WordPress. ComicPress Manager is intended to reduce the amount of work required to administer a site running ComicPress.

ComicPress Manager allows you to:

* Upload individual comic files or a Zip archive of comic files directly into your comics folder and generate posts for each comic as it's uploaded with the correct go-live date and time
  * To save a trip to the Edit Post page, you can use the Visual Editor right in ComicPress Manager to add styled text content to your post.
  * Using this method ensures that the post has the correct date and time as it's being created, reducing mistakes in posting.
  * You can also upload a single file that does not specify a date in the filename, and enter in the post date on the upload page.
  * You can also replace a single existing file with any other file, preserving the original file's name after upload
  * If you're using a different date naming convention, you can convert the old convention to the currently defined date naming convention
  * To keep nosy readers from snooping around, you can obfuscate filenames, either by adding random characters to the end, or by replacing the title in the filename with random characters.
  * Images are thoroughly checked while being uploaded and fixed when possible
* Create comic posts straight from the Edit Post screen
  * Upload files, set comic metadata such as hovertext and transcripts, see if thumbnails exist, and set Storyline categories for a post right in Edit Post
  * If Edit Post File Intergration is enabled, automatically rename and delete comic files when post dates are changed or when posts are deleted
  * See comic post information right in the Edit Posts list
* Use a stripped-down version of the Comic uploader straight from the Dashboard (QuomicPress)
* Manage complex, infinitely-nested Storyline structures for your comic
  * Storyline segments are Categories, so all WordPress category functions work as expected
* Bulk Edit the information on comics in your site
  * With a look and feel similar to Bulk Actions in Edit Posts, you can edit comic data, regenerate thumbnails, and delete multiple comics and posts in one operation
* Upload thumbnails directly to the archive and RSS folders
* Get a sanity check on your installation
  * ComicPress Manager will check to see if:
    * Your comics, archive, and RSS folders exist
    * Your comics, archive, and RSS folders are writable by the Webserver (if you're generating thumbnails)
    * You have enough categories defined to use ComicPress
    * You have defined a valid blog category (and comic category for ComicPress 2.5)
    * You're using a ComicPress-derived theme
      * NOTE: This check is done by examining the name of the theme as defined in style.css. If you want this non-fatal check to succeed, leave the term "ComicPress" in the theme title.
    * You have comics in your comics folder (if there aren't any, it could be a sign of other problems)
  * You can also disable these checks once you know your configuration is correct, to improve performance
* Create any missing posts for comics that have been uploaded to your comics folder
  * If you're migrating from another Webcomic hosting solution, or if you prefer to directly transfer your comics into your comics folder, then you can generate posts for all comics that don't already have posts.
* Bulk Change the post dates and comic filenames for any comic you've uploaded
  * You can use this advanced feature to shift a large number of comics forward or backwards in time.
* Manage multiple comics running on one WordPress installation by using directories named after category slugs (work in progress)
* Modify your comicpress-config.php file from ComicPress Manager.
  * If you're using a comicpress-config.php file, and the permissions are set correctly, you can modify the settings directly from ComicPress manager. If your permissions are not correct, the config file that ComicPress Manager would have written will be shown so that you can copy and paste it into your comicpress-config.php file.
  * Manage your ComicPress Manager configuration directly from the CPM interface, instead of modifying comicpress\_manager\_config.php
  * If your config goes awry, you can also restore from a backup config.

For those upgrading from the 1.3 line, note that the Delete and Generate Thumbnails pages have been merged into Bulk Edit and into the Edit Post File Integration option. You can now delete comic files and posts in single operations through either method, and you can regenerate thumbnails on the Bulk Edit page.

Additionally, on your Dashboard, you'll see the latest stories from the ComicPress site, so you can keep up on updates, upgrades, and other news to make your Webcomics publishing using WordPress + ComicPress easier and more fun.

You can also change what is viewed in the left sidebar as you're working in ComicPress Manager. You can see:

* The classic ComicPress Manager sidebar and help boxes.
* The current, last, and upcoming comics to be published on your site, with thumbnails to ensure the right comic is going live.
* No Sidebar, which speeds up page load times

ComicPress Manager is built for WordPress 2.5.1 & above and ComicPress 2.1, 2.5, and 2.7, but using ComicPress 2.7 is strongly recommended. ComicPress Manager works on PHP 4, but using PHP 5 is strongly recommended.

Before you begin working with ComicPress Manager, and especially while the software is still in development, it is recommended that you make regular backups of your WordPress database and comics folder.

If you're having problems while upgrading, be sure to delete your existing comicpress_manager directory from your plugins folder.

== Installation ==

Copy the comicpress-manager directory to your wp-content/plugins/ directory and activate the plugin.  ComicPress Manager works on PHP 4, but using PHP 5 is strongly recommended.

== Frequently Asked Questions ==

= I'm unable to edit my comicpress-config.php file from the plugin interface =

Check the permissions on the theme directory and on the comicpress-config.php file itself.  Both of these need to be writable by the user that the Webserver runs as.  For more information on this, contact your Webhost.

Alternatively, if you can't automatically write to the comicpress-config.php file, the config that would have been written will be shown on-screen.  Copy and paste this into your comicpress-config.php file.

= I edited my config, and now I'm getting errors =

The error you're most likely to see when working with config files in ComicPress Manager contains the following string:

<pre>[function.main]: failed to open stream: No such file or directory</pre>

This means that functions.php is attempting to include the comicpress-config.php file from your theme directory and comicpress-config.php does not exist.  Check your theme folder for the following:

* A missing comicpress-config.php file
* A file (or files) named comicpress-config.php.{long string of numbers}

If this has happened, either use the restore function that appears on the config errors screen, or copy the most recent comicpress-config.php.{long string of numbers} file in your theme's directory back to comicpress-config.php.  Then, try experimenting with different permissions settings on your theme folder to see if the situation improves, or use ComicPress Manager generate the config that you can then copy and paste into ComicPress Manager.

= I'm getting permissions errors when uploading comics =

Depending on your hosting type and set up, you may need to use your FTP client's chmod command or your Webhost's file management frontend to increase the permissions of your folders. chmod 775 or chmod 777 are both settings that may work on your hosting.

= I can't upload a Zip file at all =

You need the PHP Zip extension installed to be able to process Zip files.  Ask your Webhost to install it.

= I can't upload a large image file or a large Zip file =

The upload\_max\_filesize or max\_post\_size settings on your server may be set too low.  You can do one of the following:

* Talk with your Webhost about increasing the upload\_max\_size and max\_post\_size for your entire site
* Split the upload up into several smaller piece
* Create or modify an .htaccess file at the root of your WordPress site, and place a php.ini directive to increase upload\_max\_filesize and max\_post\_size just for that part of the site:

<pre>
php_value upload_max_filesize "5M"
php_value pax_post_size "5M"
</pre>

= (For Windows servers) It appears that I can upload a file, and I can see it in FTP listings, but neither ComicPress nor ComicPress Manager can see the file. The same thing happens with thumbnails, and when I edit my config. =

There seems to be an issue with FastCGI for IIS, where permissions on files created by the Webserver process have no permissions whatsoever, and need to have permissions granted by a user with Administrator privileges.

Additionally, there should only be one instance of <code>upload_tmp_file</code> in your php.ini file, and the directory specified needs to be writable by the Webserver, and has to have no backslash at the end of the path:

<pre>upload_tmp_dir = "c:\inetpub\temp\uploads"</pre>

= How can I change the minimum access level for the plugin? =

There are three lines at the top of comicpress\_manager\_config.php that define the <code>$access_level</code> of the plugin.  Uncomment the line that defines the level of access you want to give and comment out the others.

= How do I change the width of generated thumbnails for the Archive and RSS version of my comics? =

Change the "Archive Width" in your ComicPress config (comicpress-config.php in your theme) to the thumbnail width you wish to have for both Archive and RSS. Note that only ComicPress 2.7 supports RSS thumbnail width adjustment.

= Why can't I generate thumbnails? =

You will need either GD library support compiled in or loaded with PHP, or the ImageMagick "convert" and "identify" binaries in your path.  If neither of these are available, you will be unable to generate thumbnails.  Your thumbnail directories also need to be writable by the Webserver process.

= What if I don't want to automatically generate thumbnails? =

Disable the appropriate options on the ComicPress Manager configuration page.

= How do I change the output quality of the JPEG thumbnail file? =

Change the JPEG thumbnail quality on the ComicPress Manager configuration page to a value between 0 (ugly & small filesize) to 100 (no compression).

= The plugin fails during import. =

If you are importing a large number of files, especially if you're generating thumbnails, the amount of time it would take to process the comics can exceed the time allotted by your Webhost for a script to run.  ComicPress Manager will attempt to split up the Import into smaller chunks to work around this, but it may still fail in rare cases. If it does, you can do the following:

* Don't generate thumbnails during import, and instead generate them later under Bulk Edit.
* Import your comic in chunks by uploading Zip files of comics or by uploading only a few at a time to the comics directory.
* Add a [<code>set_time_limit</code>](http://us3.php.net/set_time_limit) command to the top of the plugin
* Ask your Webhost to increase the <code>max_execution_time</code> for your site, or use an .htaccess file to change it yourself.

= I know what I'm doing.  How do I disable the sanity checks to improve performance? =
= I've been getting ComicPress upgrade messages, and I really don't want to see them anymore. =

You can disable sanity checks and upgrade messages on the ComicPress Manager configuration page.

= I want to change the date format used by ComicPress and ComicPress Manager from Y-m-d to something else. =

You can change the date format on the ComicPress Manager configuration page. Change the format to a [<code>date</code>](http://us3.php.net/date) compatible format.  Then, in your ComicPress theme, in the functions.php file, change every instance of <code>Y-m-d</code> to your new format (or better yet, use CPM\_DATE\_FORMAT directly, as comicpress\_manager\_config.php is loaded with every page load in WordPress).

= I don't want to clutter up my Dashboard with the ComicPress RSS widget or with QuomicPress. =

Disable the appropriate options on the ComicPress Manager configuration page.

= How do I set up my comic directories for subdirectory management? =

Get the category slug for the Storyline category that will represent the new separate comic. Create directories within your existing comic directories that are named after this slug. Make sure the directories are writable, and then go to ComicPress Config and choose the new comic category. All future file operations will only deal with this directory.

This feature is still experimental, and currently requires you to write the comic file handling code for your ComicPress functions.php file.

= I want to translate your plugin into my language. =

Feel free to contact me, or better yet, send a translation in.  The POT file is in the plugin directory.  I'm still new to this, so if I'm doing something wrong in the code, please tell me.  :)

= I'm having another problem. =

File a bug on the [ComicPress Bug Tracker](http://bugs.comicpress.org/).  If asked, provide the info given when you click the Show Debug Info link on the left-hand side.  Make sure you're running the most recent stable version of ComicPress Manager, as there are a lot of critical bug fixes between versions.

If it's a serious problem, such as WordPress becoming non-functional or blank screens/errors after you perform certain operations, you will be asked to provide error logs for your server.  If you don't know how to get access to these, talk with your Webhost.

== License ==

ComicPress Manager is released under the GNU GPL version 2.0 or later.

The Dynarch DHTML Calendar Widget is released under the GNU LGPL.

== Credits ==

Big thanks to Tyler Martin for his assistance, bug finding, and with coming up with ComicPress in the first place.  Additional big thanks to Philip Hofer of Frumph.NET for assistance in debugging problems with Yahoo! hosting and Windows/IIS.  Also thanks to John LaRusic for the initial code contribution for Write Comic. Also also thanks to Danny Burleson, karchesky, tk0169, Tim Hengeveld, Keith C. Smith, philipspence, jhorsley3, Matthew LaCurtis, and iRobot for beta testing, the folks at the Lunchbox Funnies forum for finding & reporting bugs, and everyone who donated time, money, or equipment to make this software great.

ComicPress Manager uses the [Dynarch DHTML Calendar Widget](http://www.dynarch.com/projects/calendar/) for date fields.
