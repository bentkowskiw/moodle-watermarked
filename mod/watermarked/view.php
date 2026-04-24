<?php

// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Resource module version information
 *
 * @package    mod_resource
 * @copyright  2009 Petr Skoda  {@link http://skodak.org}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require('../../config.php');
require_once($CFG->dirroot.'/mod/watermarked/lib.php');
require_once($CFG->dirroot.'/mod/watermarked/locallib.php');
require_once($CFG->libdir.'/completionlib.php');

$id       = optional_param('id', 0, PARAM_INT); // Course Module ID
$r        = optional_param('r', 0, PARAM_INT);  // watermarked instance ID
$redirect = optional_param('redirect', 0, PARAM_BOOL);
$forceview = optional_param('forceview', 0, PARAM_BOOL);

if ($r) {
    if (!$watermarked = $DB->get_record('watermarked', array('id'=>$r))) {
        watermarked_redirect_if_migrated($r, 0);
        print_error('invalidaccessparameter');
    }
    $cm = get_coursemodule_from_instance('watermarked', $watermarked->id, $watermarked->course, false, MUST_EXIST);

} else {
    if (!$cm = get_coursemodule_from_id('watermarked', $id)) {
        watermarked_redirect_if_migrated(0, $id);
        print_error('invalidcoursemodule');
    }
    $watermarked = $DB->get_record('watermarked', array('id'=>$cm->instance), '*', MUST_EXIST);
}

$course = $DB->get_record('course', array('id'=>$cm->course), '*', MUST_EXIST);

require_course_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/watermarked:view', $context);

// Completion and trigger events.
watermarked_view($watermarked, $course, $cm, $context);

$PAGE->set_url('/mod/watermarked/view.php', array('id' => $cm->id));

if ($watermarked->tobemigrated) {
    watermarked_print_tobemigrated($watermarked, $cm, $course);
    die;
}

$fs = get_file_storage();
$files = $fs->get_area_files($context->id, 'mod_watermarked', 'content', 0, 'sortorder DESC, id ASC', false); // TODO: this is not very efficient!!
if (count($files) < 1) {
    watermarked_print_filenotfound($watermarked, $cm, $course);
    die;
} else {
    $file = reset($files);
    unset($files);
}

$watermarked->mainfile = $file->get_filename();
$displaytype = watermarked_get_final_display_type($watermarked);
if ($displaytype == RESOURCELIB_DISPLAY_OPEN || $displaytype == RESOURCELIB_DISPLAY_DOWNLOAD) {
    $redirect = true;
}

// Don't redirect teachers, otherwise they can not access course or module settings.
if ($redirect && !course_get_format($course)->has_view_page() &&
        (has_capability('moodle/course:manageactivities', $context) ||
        has_capability('moodle/course:update', context_course::instance($course->id)))) {
    $redirect = false;
}




    // coming from course page or url index page
    // this redirect trick solves caching problems when tracking views ;-)
    
	require_once('class-wb-pdf-watermark.php');
	$service = WB_PDF_Watermark::instance();
	
	$localpath = $fs->get_file_system()->get_local_path_from_storedfile($file);
	
	global $USER;
	
	
    $new_file = $service->watermark_file($localpath, $USER);
	$name = basename( $new_file);
	$path = '/'.$context->id.'/mod_watermarked/content/'.$name;
    $fullurl = moodle_url::make_file_url('/pluginfile.php', $path, $displaytype == RESOURCELIB_DISPLAY_DOWNLOAD);	
	
	//echo ':'.$file->get_filepath(); 
	//die();
	//echo $file->get_contenthash();
	redirect($fullurl);

	



