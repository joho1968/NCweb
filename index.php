<?php
/*
 * NCweb 0.4
 * Serve a Nextcloud folder as a public website
 * https://github.com/joho1968/NCweb
 *
 * BSD 3-Clause License
 * Copyright (c) 2020 Joaquim Homrighausen. All rights reserved.
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 *
 * - Redistributions of source code must retain the above copyright notice, this
 *   list of conditions and the following disclaimer.
 *
 * - Redistributions in binary form must reproduce the above copyright notice,
 *   this list of conditions and the following disclaimer in the documentation
 *   and/or other materials provided with the distribution.
 *
 * - Neither the name of the copyright holder nor the names of its contributors
 *   may be used to endorse or promote products derived from this software
 *   without specific prior written permission.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS"
 * AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
 * IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
 * DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT HOLDER OR CONTRIBUTORS BE LIABLE
 * FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL
 * DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR
 * SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER
 * CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY,
 * OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
 * OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 */

use League\CommonMark\CommonMarkConverter;
require_once 'league/vendor/autoload.php';

use Sabre\DAV\Client;
require_once 'sabredav/vendor/autoload.php';

require_once 'config.inc.php';


// Some compatibility stuff

if (! function_exists('array_key_first')) {
    function array_key_first (array $a)
    {
        foreach ($a as $k => $v) {
            return $k;
        }
        return (false);
    }
}

// Some basic sanity checks

if (! function_exists ('curl_exec')) {
    error_log ('curl_exec() does not seem to exist, or has been disabled?');
    die ('We are not configured properly yet, sorry for the inconvenience');
}
if (! function_exists ('mb_strpos')) {
    error_log ('mb_strpos() does not seem to exist, or has been disabled?');
    die ('We are not configured properly yet, sorry for the inconvenience');
}
if (empty ($GLOBALS ['nc_dav_web_server_url'])
        || empty ($GLOBALS ['nc_dav_web_server_username'])
            || empty ($GLOBALS ['nc_dav_web_server_password'])) {
    error_log ('Missing basic configuration parameters');
    die ('We are not configured properly yet, sorry for the inconvenience');
}
if (empty ($GLOBALS ['nc_dav_web_root'])) {
    error_log ('The web root seems to be missing or empty');
    die ('We are not configured properly yet, sorry for the inconvenience');
}
if (empty ($GLOBALS ['nc_dav_web_server_var'])) {
    // We default to using this
    error_log ('$GLOBALS [\'nc_dav_web_server_var\'] has not been configured, defaulting to REQUEST_URI');
    $GLOBALS ['nc_dav_web_server_var'] = 'REQUEST_URI';
}
if (empty ($_SERVER [$GLOBALS ['nc_dav_web_server_var']])) {
    error_log ('$_SERVER index '.$GLOBALS ['nc_dav_web_server_var'].' is empty?');
    die ('We are not configured properly yet, sorry for the inconvenience');
}
if (! isset ($GLOBALS ['nc_dav_web_root_url'])) {
    error_log ('$GLOBALS [\'nc_dav_web_root_url\'] has not been configured');
    die ('We are not configured properly yet, sorry for the inconvenience');
}
if (empty ($GLOBALS ['nc_dav_web_server_locale'])) {
    error_log ('$GLOBALS [\'nc_dav_web_server_locale\'] has not been configured');
    die ('We are not configured properly yet, sorry for the inconvenience');
}

// Set locale as configured

@ setlocale (LC_ALL, $GLOBALS ['nc_dav_web_server_locale']);

// We can't use $_REQUEST since there's some translation / sanitizing going on
// there, so we want as much of this in raw form if possible.

$request = urldecode ($_SERVER [$GLOBALS ['nc_dav_web_server_var']]);
$original_request = $_SERVER ['REQUEST_SCHEME'] . '://' . $_SERVER ['HTTP_HOST'] . $request;

// !!! THIS IS BY NO MEANS FOOL PROOF !!!
//
// We expect the underlying storage handler (e.g. Nextcloud) to provide proper
// handling of weird paths.

$valid_request = $_SERVER ['REQUEST_SCHEME'] .
                 '://' .
                 $_SERVER ['HTTP_HOST'] .
                 str_replace (array ('../', './', '//', '..\\', '.\\', '\\\\', '///', '<', '>'),
                              '',
                              $request);

// Let PHP have a go at the URI

$tmp_parse = parse_url ($valid_request);
if (empty ($tmp_parse ['scheme']) || empty ($tmp_parse ['host'])) {
    error_log ('parse_url () failed');
    die ('Invalid request');
}
$valid_request = $tmp_parse ['scheme'] . '://' . $tmp_parse ['host'];
if (! empty ($tmp_parse ['path'])) {
    $valid_request .= $tmp_parse ['path'];
    $request_path = $tmp_parse ['path'];
} else {
    $request_path = '';
}
$valid_request = filter_var (str_replace (' ', '%20', $valid_request), FILTER_SANITIZE_URL, FILTER_FLAG_STRIP_BACKTICK);
if ($valid_request === false) {
    die ('Invalid request, sanitize failed ('.htmlentities ($original_request).')');
}
if (! filter_var ($valid_request, FILTER_VALIDATE_URL)) {
    die ('Invalid request, validate failed ('.htmlentities ($original_request).')');
}

// Re-parse (path) after sanitize/validate

$tmp_parse = parse_url ($valid_request);
if (! empty ($tmp_parse ['path'])) {
    $request_path = $tmp_parse ['path'];
} else {
    $request_path = '';
}

$valid_request = str_replace ('%20', ' ', $valid_request);
$request_path = str_replace ('%20', ' ', $request_path);
// Possibly remove ourselves

if (! empty ($GLOBALS ['nc_dav_web_root_url'])) {
    if (mb_strpos ($request_path, $GLOBALS ['nc_dav_web_root_url']) === 0) {
        $request_path = mb_substr ($request_path, mb_strlen ($GLOBALS ['nc_dav_web_root_url']));
        if (mb_strlen ($request_path) == 0 || $request_path == '/') {
            $request_path = (empty ($GLOBALS ['nc_dav_web_markdown']) ? '/index.html':'/index.md');
        }
    } else {
        error_log ('"'.$GLOBALS ['nc_dav_web_root_url'].'" does not seem to appear in "'.$request_path.'"');
        die ('Invalid request, unknown location');
    }
}

// Add directory from which we're serving requests

$request_path = $GLOBALS ['nc_dav_web_root'] . $request_path;
if (mb_strrpos ($request_path, '/') === mb_strlen ($request_path) - 1) {
    $request_path .= (empty ($GLOBALS ['nc_dav_web_markdown']) ? 'index.html':'index.md');
}

// Open up and ...

try {
    $nc_dav = new Client (array (
                            'baseUri'  => $GLOBALS ['nc_dav_web_server_url'].'/remote.php/dav',
                            'userName' => $GLOBALS ['nc_dav_web_server_username'],
                            'password' => $GLOBALS ['nc_dav_web_server_password'],
                            'is_debug' => false,
                            )
                         );
} catch (Exception $e) {
    error_log (__LINE__, ': Unable to initialize new WebDAV client ['.$e->getMessage ().']');
    die ('Something went wrong');
}

// Fetch requested document

// Uncomment the next line if you want to log requests
// error_log ('REQ: "'.$GLOBALS ['nc_dav_web_server_url'].$request_path.'"');

try {
    $response = $nc_dav->request ('GET', 'files'.$request_path);
} catch (Exception $e) {
    error_log ('Unable to fetch requested document '.$request_path.' ['.$e->getMessage ().']');
    die ('That document doesn\'t seem to exist');
}

// Check status of request

if (! is_array ($response) || empty ($response ['statusCode'])) {
    error_log (__LINE__, ': Malformed response from server');
    die ('Something went wrong');
}
if ($response ['statusCode'] != 200) {
    header ('HTTP/1.0 '.$response ['statusCode']);
    die ();
}

// Do some "content management" :)

$content_length = 0;
$content_type = 'text/html';
$content_encoding = 'utf-8';

foreach ($response ['headers'] as $k => $v) {
    switch ($k) {
        case 'content-type':
            // "text/html;utf-8"
            if (! empty ($v [0])) {
                $tmp_ct = explode (';', $v [0]);
                if (is_array ($tmp_ct)) {
                    if (empty ($tmp_ct [0]) || $tmp_ct [0] == 'text/plain') {
                        $content_type = 'text/html';
                    } else {
                        $content_type = $tmp_ct [0];
                        if (! empty ($GLOBALS ['nc_dav_web_markdown']) && $content_type == 'text/markdown') {
                            $content_type = 'text/html';
                            // Render Markdown
                            try {
                                $md_converter = new CommonMarkConverter(['max_nesting_level' => 100]);
                                $response ['body'] = $md_converter->convertToHtml ($response ['body']);
                            } catch (Exception $e) {
                                error_log ('Unable to render Markdown to HTML ['.$e->getMessage ().']');
                                header ('HTTP/1.0 500');
                                die ();
                            }
                        }
                    }
                    if (! empty ($tmp_ct [1])) {
                        $content_encoding = $tmp_ct [1];
                    }
                }
            }
            break;
        case 'content-length':
            if (! empty ($v [0]) && (int)$v [0] > 0) {
                $content_length = $v [0];
            }
            break;
    }//switch
}//foreach


// Output

if (mb_strpos ($content_type, 'text/') === 0) {
    header ('Content-type: '    . $content_type . '; '.$content_encoding);
} else {
    header ('Content-type: '    . $content_type);
}

header ('Content-length: '  . mb_strlen ($response ['body']));
if (! empty ($response ['body'])) {
    echo $response ['body'];
}

?>
