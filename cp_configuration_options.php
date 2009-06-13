<?php

$comicpress_configuration_options = array(
  array(
    'id' => 'comiccat',
    'name' => 'Comic Category',
    'type' => 'category',
    'description' => 'The category ID of your comic category',
    'default' => 1
  ),
  array(
    'id' => 'blogcat',
    'name' => 'Blog Category',
    'type' => 'category',
    'description' => 'The category ID of your blog category',
    'default' => 2
  ),
  array(
    'id' => 'comics_path',
    'variable_name' => 'comic_folder',
    'name' => 'Comic Folder',
    'type' => 'folder',
    'description' => 'The folder your comics are located in',
    'default' => "comics",
    'no_wpmu' => true
  ),
  array(
    'id' => 'comicsrss_path',
    'variable_name' => 'rss_comic_folder',
    'name' => 'RSS Comic Folder',
    'type' => 'folder',
    'description' => 'The folder your comics are located in for the RSS feed',
    'default' => "comics-rss",
    'no_wpmu' => true
  ),
  array(
    'id' => 'comicsarchive_path',
    'variable_name' => 'archive_comic_folder',
    'name' => 'Archive Comic Folder',
    'type' => 'folder',
    'description' => 'The folder your comics are located in for the Archive pages',
    'default' => "comics-archive",
    'no_wpmu' => true
  ),
  array(
    'id' => 'archive_comic_width',
    'name' => 'Archive Comic Width',
    'type' => 'integer',
    'description' => 'The width your comics will appear on archive or search results',
    'default' => "380"
  ),
  array(
    'id' => 'rss_comic_width',
    'name' => 'RSS Comic Width',
    'type' => 'integer',
    'description' => 'The width your comics will appear in the RSS feed',
    'default' => "380"
  ),
  array(
    'id' => 'blog_postcount',
    'name' => 'Blog Post Count',
    'type' => 'integer',
    'description' => 'The number of blog entries to appear on the home page',
    'default' => "10"
  ),
);

?>