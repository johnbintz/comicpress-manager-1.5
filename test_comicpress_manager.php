<?php

// this test suite requires SimpleTest to be installed in your library include path.
require_once('simpletest/autorun.php');

require_once('comicpress_manager_library.php');

define("CPM_TEST_DEFAULT_URL", "http://mysite");
define("CPM_TEST_DOCUMENT_ROOT", "/var/www/mysite/htdocs");
define("CPM_TEST_FILENAME_PATTERN", "Y-m-d");

/**
 * Test the functions in the ComicPress Manager Library.
 **/
class TestComicPressManagerLibrary extends UnitTestCase {
  function testGenerateExampleDate() {
    foreach (array(
      array("Y", "YYYY"),
      array("m", "MM"),
      array("d", "DD"),
      array("123", "123"),
      array('\Y', "Y")
    ) as $test) {
      list($example_date, $expected_result) = $test;
      $this->assertEqual($expected_result, cpm_generate_example_date($example_date));
    }
  }

  function testBuildComicURI(){
    foreach (array(
      array("test.gif", false),
      array("/test.gif", false),
      array("/mycomic/test.gif", false),
      array(CPM_TEST_DOCUMENT_ROOT . "/mycomic/test.gif", '/mycomic/test.gif'),
      array(CPM_TEST_DOCUMENT_ROOT . "/my_website/mycomic/test.gif", '/mycomic/test.gif'),
      array(CPM_TEST_DOCUMENT_ROOT . '\mycomic\test.gif', '/mycomic/test.gif'),
    ) as $test) {
      list($example_filename, $expected_url) = $test;
      echo $example_filename . "\n";
      $result = cpm_build_comic_uri($example_filename, CPM_TEST_DOCUMENT_ROOT);
      echo $result . "\n";
      $this->assertEqual($expected_url, $result);
    }
  }

  function testBreakdownComicFilename() {
    $_POST['upload-date-format'] = CPM_TEST_FILENAME_PATTERN;
    $test_date = "2008-01-01";
    foreach (array(
      array("meow", false),
      array("meow.xfm", false),
      array(md5(rand()), false),
      array("${test_date}.jpg", array("title" => "", "date" => $test_date, "converted_title" => "")),
      array("comics/${test_date}.jpg", false),
      array("${test_date}-Test.jpg", array("title" => "-Test", "date" => $test_date, "converted_title" => "Test")),
      array("${test_date}-test.jpg", array("title" => "-test", "date" => $test_date, "converted_title" => "Test")),
      array("1900-01-01.jpg", false),
    ) as $test) {
      list($filename, $expected_value) = $test;
      $this->assertEqual($expected_value, $comicpress_manager->breakdown_comic_filename($filename, true));
    }
  }

  function testTransformDateString() {
    $valid_string = "Y-m-d";
    $valid_transform = array("Y" => "YYYY", "m" => "MM", "d" => "DD");
    foreach (array(
      array($valid_string, null, false),
      array(null, $valid_transform, false),
      array($valid_string, array("Y"), false),
      array($valid_string, array("Y" => "YYYY"), false),
      array($valid_string, $valid_transform, "YYYY-MM-DD"),
      array('\\Y', $valid_transform, "Y"),
    ) as $test) {
      list($string, $replacements, $result) = $test;
      $transform_result = cpm_transform_date_string($string, $replacements);
      if (empty($transform_result)) {
        $this->assertTrue($transform_result === false);
      } else {
        $this->assertEqual($result, $transform_result);
      }
    }
  }

  function testCalculateDocumentRoots() {
    $original_script_filename = $_SERVER['SCRIPT_FILENAME'];
    $original_script_name     = $_SERVER['SCRIPT_NAME'];
    $original_script_url      = $_SERVER['SCRIPT_URL'];
    $original_document_root   = $_SERVER['DOCUMENT_ROOT'];
  }
}

function get_bloginfo($which) {
  switch ($which) {
    case "url": return CPM_TEST_DEFAULT_URL;
  }
  return "";
}

function get_cat_name($cat) {
  return "meow";
}

?>