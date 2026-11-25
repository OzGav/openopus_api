<?
    // Synology Web Station layout. Copy this file to openopus_api/lib/inc.php and edit the placeholders.

    // keep PHP 8 messages out of the json output, and make mysqli return false on errors like older PHP did

    ini_set ("display_errors", "0");
    mysqli_report (MYSQLI_REPORT_OFF);

    // web server

    define ("INFRADIR", "/volume1/web"); // base OS directory
    define ("UTILIB", INFRADIR. "/openopus_utils"); // directory for the utilities library
    define ("BASEDIR", INFRADIR. "/openopus_api"); // directory for all project, including public and non-public files
    define ("WEBDIR", BASEDIR. "/html"); // directory for publicly accessible files
    define ("LIB", BASEDIR. "/lib"); // directory for non-publicly accessible files, like libraries
    define ("LOG", BASEDIR. "/log"); // log dir
    define ("DEBUG", LOG. '/debug.txt'); // log file for some CURL operations detailed debug
    define ("TMP_DIR", "/tmp"); // OS temp directory

    // mysql (use the port shown in the MariaDB 10 app, 3306 or 3307)

    ini_set ("mysqli.default_port", "3306");
    define ("DBDB", "openopus"); // mysql database basename
    define ("DBHOST", "127.0.0.1"); // mysql host address
    define ("DBUSER", "openopus"); // mysql username
    define ("DBPASS", "change-me"); // mysql password

    // cloud storage (only used for portrait uploads, not set up here)

    define ("GOOGLE_BUCKET", "assets.openopus.org"); // name of the storage bucket
    define ("GOOGLE_STORAGE", "https://". GOOGLE_BUCKET); // resulting url of the storage bucket
    define ("GOOGLE_KEYFILE", BASEDIR. "/keys/keyfile.json"); // google cloud key file

    // admin

    define ("SOFTWAREMAIL", "admin@example.com"); // placeholder, only used in the user agent of outgoing requests

    // debug

    define ("NOCACHE", false); // true will stop caching api results - useful for debugging

    // library initialization

    include_once (LIB. "/ini.php");
