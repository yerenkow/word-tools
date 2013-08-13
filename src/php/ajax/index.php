<?php
/**
 * This is main request processor, it contains only routing logic
 *
 *  Requests:
 *      - create new textblock request - return id of text; in case of small text - save it immediately
 *      - save all content to textblock by chunks of some reasonable size;
 *      - get status of parsing text
 *      - display results
 *      - get similar textes
 *      - show text by id
 *      - show last textes
 *
 *
 */

include_once "config.php";
include_once "logic.php";

/*
  Here goes controller, which do all and everything in this project.
*/
$ACTION_CODE_GET_RECENT = 1;
$ACTION_CODE_UPLOAD_NEW = 2;
$ACTION_CODE_GET_TEXT_INFO = 4;

$requestCode = (int) $_POST["act"];

if($requestCode == $ACTION_CODE_GET_RECENT) {
    showRecentTexts();
}
if($requestCode == $ACTION_CODE_UPLOAD_NEW) {
    uploadNewText();
}
if($requestCode == $ACTION_CODE_GET_TEXT_INFO) {
    showTextInfo();
}

