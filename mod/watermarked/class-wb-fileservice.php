<?php

	class WB_FileService {

/**
 * Handles the sending of file data to the user's browser, including support for
 * byteranges etc.
 *
 * @category files
 * @param string|stored_file $path Path of file on disk (including real filename),
 *                                 or actual content of file as string,
 *                                 or stored_file object
 * @param string $filename Filename to send
 * @param int $lifetime Number of seconds before the file should expire from caches (null means $CFG->filelifetime)
 * @param int $filter 0 (default)=no filtering, 1=all files, 2=html files only
 * @param bool $pathisstring If true (default false), $path is the content to send and not the pathname.
 *                           Forced to false when $path is a stored_file object.
 * @param bool $forcedownload If true (default false), forces download of file rather than view in browser/plugin
 * @param string $mimetype Include to specify the MIME type; leave blank to have it guess the type from $filename
 * @param bool $dontdie - return control to caller afterwards. this is not recommended and only used for cleanup tasks.
 *                        if this is passed as true, ignore_user_abort is called.  if you don't want your processing to continue on cancel,
 *                        you must detect this case when control is returned using connection_aborted. Please not that session is closed
 *                        and should not be reopened.
 * @param array $options An array of options, currently accepts:
 *                       - (string) cacheability: public, or private.
 *                       - (string|null) immutable
 * @return null script execution stopped unless $dontdie is true
 */
 private function send_file($path, $filename, $lifetime = null , $filter=0, $pathisstring=false, $forcedownload=false, $mimetype='',
                   $dontdie=false, array $options = array()) {
    global $CFG, $COURSE;

    if ($dontdie) {
        ignore_user_abort(true);
    }

    if ($lifetime === 'default' or is_null($lifetime)) {
        $lifetime = $CFG->filelifetime;
    }

    if (is_object($path)) {
        $pathisstring = false;
    }

    \core\session\manager::write_close(); // Unlock session during file serving.

    // Use given MIME type if specified, otherwise guess it.
    if (!$mimetype || $mimetype === 'document/unknown') {
        $mimetype = get_mimetype_for_sending($filename);
    }

    // if user is using IE, urlencode the filename so that multibyte file name will show up correctly on popup
    if (core_useragent::is_ie() || core_useragent::is_edge()) {
        $filename = rawurlencode($filename);
    }

    if ($forcedownload) {
        header('Content-Disposition: attachment; filename="'.$filename.'"');

        // If this file was requested from a form, then mark download as complete.
        \core_form\util::form_download_complete();
    } else if ($mimetype !== 'application/x-shockwave-flash') {
        // If this is an swf don't pass content-disposition with filename as this makes the flash player treat the file
        // as an upload and enforces security that may prevent the file from being loaded.

        header('Content-Disposition: inline; filename="'.$filename.'"');
    }

    if ($lifetime > 0) {
        $immutable = '';
        if (!empty($options['immutable'])) {
            $immutable = ', immutable';
            // Overwrite lifetime accordingly:
            // 90 days only - based on Moodle point release cadence being every 3 months.
            $lifetimemin = 60 * 60 * 24 * 90;
            $lifetime = max($lifetime, $lifetimemin);
        }
        $cacheability = ' public,';
        if (!empty($options['cacheability']) && ($options['cacheability'] === 'public')) {
            // This file must be cache-able by both browsers and proxies.
            $cacheability = ' public,';
        } else if (!empty($options['cacheability']) && ($options['cacheability'] === 'private')) {
            // This file must be cache-able only by browsers.
            $cacheability = ' private,';
        } else if (isloggedin() and !isguestuser()) {
            // By default, under the conditions above, this file must be cache-able only by browsers.
            $cacheability = ' private,';
        }
        $nobyteserving = false;
        header('Cache-Control:'.$cacheability.' max-age='.$lifetime.', no-transform'.$immutable);
        header('Expires: '. gmdate('D, d M Y H:i:s', time() + $lifetime) .' GMT');
        header('Pragma: ');

    } else { // Do not cache files in proxies and browsers
        $nobyteserving = true;
        if (is_https()) { // HTTPS sites - watch out for IE! KB812935 and KB316431.
            header('Cache-Control: private, max-age=10, no-transform');
            header('Expires: '. gmdate('D, d M Y H:i:s', 0) .' GMT');
            header('Pragma: ');
        } else { //normal http - prevent caching at all cost
            header('Cache-Control: private, must-revalidate, pre-check=0, post-check=0, max-age=0, no-transform');
            header('Expires: '. gmdate('D, d M Y H:i:s', 0) .' GMT');
            header('Pragma: no-cache');
        }
    }


        // send the contents

     readfile_accel($path, $mimetype, !$dontdie);

    if ($dontdie) {
        return;
    }
    die; //no more chars to output!!!
}

/**
 * Handles the sending of file data to the user's browser, including support for
 * byteranges etc.
 *
 * The $options parameter supports the following keys:
 *  (string|null) preview - send the preview of the file (e.g. "thumb" for a thumbnail)
 *  (string|null) filename - overrides the implicit filename
 *  (bool) dontdie - return control to caller afterwards. this is not recommended and only used for cleanup tasks.
 *      if this is passed as true, ignore_user_abort is called.  if you don't want your processing to continue on cancel,
 *      you must detect this case when control is returned using connection_aborted. Please not that session is closed
 *      and should not be reopened
 *  (string|null) cacheability - force the cacheability setting of the HTTP response, "private" or "public",
 *      when $lifetime is greater than 0. Cacheability defaults to "private" when logged in as other than guest; otherwise,
 *      defaults to "public".
 *  (string|null) immutable - set the immutable cache setting in the HTTP response, when served under HTTPS.
 *      Note: it's up to the consumer to set it properly i.e. when serving a "versioned" URL.
 *
 * @category files
 * @param stored_file $stored_file local file object
 * @param int $lifetime Number of seconds before the file should expire from caches (null means $CFG->filelifetime)
 * @param int $filter 0 (default)=no filtering, 1=all files, 2=html files only
 * @param bool $forcedownload If true (default false), forces download of file rather than view in browser/plugin
 * @param array $options additional options affecting the file serving
 * @return null script execution stopped unless $options['dontdie'] is true
 */
public function send_stored_file($stored_file, $lifetime=null, $filter=0, $forcedownload=false, array $options=array()) {
    global $CFG, $COURSE;

    if (empty($options['filename'])) {
        $filename = null;
    } else {
        $filename = $options['filename'];
    }

    if (empty($options['dontdie'])) {
        $dontdie = false;
    } else {
        $dontdie = true;
    }

    if ($lifetime === 'default' or is_null($lifetime)) {
        $lifetime = $CFG->filelifetime;
    }

 

    $filename = is_null($filename) ? $stored_file->get_filename() : $filename;

    // Use given MIME type if specified.
    $mimetype = $stored_file->get_mimetype();

    // Allow cross-origin requests only for Web Services.
    // This allow to receive requests done by Web Workers or webapps in different domains.
    if (WS_SERVER) {
        header('Access-Control-Allow-Origin: *');
    }

    $this->send_file($stored_file, $filename, $lifetime, $filter, false, $forcedownload, $mimetype, $dontdie, $options);
}

 /**
 * Enhanced readfile() with optional acceleration.
 * @param string|stored_file $file
 * @param string $mimetype
 * @param bool $accelerate
 * @return void
 */
	private function readfile_accel($file, $mimetype, $accelerate) {
    global $CFG;

    if ($mimetype === 'text/plain') {
        // there is no encoding specified in text files, we need something consistent
        header('Content-Type: text/plain; charset=utf-8');
    } else {
        header('Content-Type: '.$mimetype);
    }

    $lastmodified = is_object($file) ? $file->get_timemodified() : filemtime($file);
    header('Last-Modified: '. gmdate('D, d M Y H:i:s', $lastmodified) .' GMT');

    if (is_object($file)) {
        header('Etag: "' . $file->get_contenthash() . '"');
        if (isset($_SERVER['HTTP_IF_NONE_MATCH']) and trim($_SERVER['HTTP_IF_NONE_MATCH'], '"') === $file->get_contenthash()) {
            header('HTTP/1.1 304 Not Modified');
            return;
        }
    }

    // if etag present for stored file rely on it exclusively
    if (!empty($_SERVER['HTTP_IF_MODIFIED_SINCE']) and (empty($_SERVER['HTTP_IF_NONE_MATCH']) or !is_object($file))) {
        // get unixtime of request header; clip extra junk off first
        $since = strtotime(preg_replace('/;.*$/', '', $_SERVER["HTTP_IF_MODIFIED_SINCE"]));
        if ($since && $since >= $lastmodified) {
            header('HTTP/1.1 304 Not Modified');
            return;
        }
    }

    if ($accelerate and empty($CFG->disablebyteserving) and $mimetype !== 'text/plain') {
        header('Accept-Ranges: bytes');
    } else {
        header('Accept-Ranges: none');
    }

    if ($accelerate) {
        if (is_object($file)) {
            $fs = get_file_storage();
            if ($fs->supports_xsendfile()) {
                if ($fs->xsendfile($file->get_contenthash())) {
                    return;
                }
            }
        } else {
            if (!empty($CFG->xsendfile)) {
                require_once("$CFG->libdir/xsendfilelib.php");
                if (xsendfile($file)) {
                    return;
                }
            }
        }
    }

    $filesize = is_object($file) ? $file->get_filesize() : filesize($file);

    header('Last-Modified: '. gmdate('D, d M Y H:i:s', $lastmodified) .' GMT');

    if ($accelerate and empty($CFG->disablebyteserving) and $mimetype !== 'text/plain') {

        if (!empty($_SERVER['HTTP_RANGE']) and strpos($_SERVER['HTTP_RANGE'],'bytes=') !== FALSE) {
            // byteserving stuff - for acrobat reader and download accelerators
            // see: http://www.w3.org/Protocols/rfc2616/rfc2616-sec14.html#sec14.35
            // inspired by: http://www.coneural.org/florian/papers/04_byteserving.php
            $ranges = false;
            if (preg_match_all('/(\d*)-(\d*)/', $_SERVER['HTTP_RANGE'], $ranges, PREG_SET_ORDER)) {
                foreach ($ranges as $key=>$value) {
                    if ($ranges[$key][1] == '') {
                        //suffix case
                        $ranges[$key][1] = $filesize - $ranges[$key][2];
                        $ranges[$key][2] = $filesize - 1;
                    } else if ($ranges[$key][2] == '' || $ranges[$key][2] > $filesize - 1) {
                        //fix range length
                        $ranges[$key][2] = $filesize - 1;
                    }
                    if ($ranges[$key][2] != '' && $ranges[$key][2] < $ranges[$key][1]) {
                        //invalid byte-range ==> ignore header
                        $ranges = false;
                        break;
                    }
                    //prepare multipart header
                    $ranges[$key][0] =  "\r\n--".BYTESERVING_BOUNDARY."\r\nContent-Type: $mimetype\r\n";
                    $ranges[$key][0] .= "Content-Range: bytes {$ranges[$key][1]}-{$ranges[$key][2]}/$filesize\r\n\r\n";
                }
            } else {
                $ranges = false;
            }
            if ($ranges) {
                if (is_object($file)) {
                    $handle = $file->get_content_file_handle();
                } else {
                    $handle = fopen($file, 'rb');
                }
                byteserving_send_file($handle, $mimetype, $ranges, $filesize);
            }
        }
    }

    header('Content-Length: '.$filesize);

    if ($filesize > 10000000) {
        // for large files try to flush and close all buffers to conserve memory
        while(@ob_get_level()) {
            if (!@ob_end_flush()) {
                break;
            }
        }
    }

    // send the whole file content
    if (is_object($file)) {
        $file->readfile();
    } else {
        readfile_allow_large($file, $filesize);
    }
}


	}