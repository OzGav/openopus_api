<?php
// Loads dump.json into the database, keeping ids, then rebuilds search and clears the cache.
// Usage: php import.php path/to/dump.json

$nojson = true;
include_once (__DIR__. "/../lib/inc.php");

mysqli_report (MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$file = $argv[1] ?? "";
$dump = json_decode (@file_get_contents ($file), true);

if (!$dump || empty ($dump["composers"]))
{
  fwrite (STDERR, "Cannot read composers from {$file}\n");
  exit (1);
}

// empty strings are stored as null, like the rest of the API does

function nullable ($value)
{
  return ($value === "" || $value === null) ? null : $value;
}

foreach (["work_mbid", "work", "composer", "omnisearch"] as $table)
{
  mysqli_query ($mysql, "truncate table {$table}");
}

mysqli_begin_transaction ($mysql);

$composerstmt = mysqli_prepare ($mysql, "insert into composer (id, name, complete_name, portrait, birth, death, epoch, popular, recommended, mbid) values (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
$workstmt = mysqli_prepare ($mysql, "insert into work (id, composer_id, title, subtitle, searchterms, genre, popular, recommended) values (?, ?, ?, ?, ?, ?, ?, ?)");
$mbidstmt = mysqli_prepare ($mysql, "insert ignore into work_mbid (work_id, mbid) values (?, ?)");

$composers = 0;
$works = 0;

foreach ($dump["composers"] as $c)
{
  $id = (int) $c["id"];
  $portrait = nullable ($c["portrait"] ?? null);
  $death = nullable ($c["death"]);
  $popular = (int) $c["popular"];
  $recommended = (int) $c["recommended"];
  $mbid = nullable ($c["mbid"] ?? null);

  mysqli_stmt_bind_param ($composerstmt, "issssssiis", $id, $c["name"], $c["complete_name"], $portrait, $c["birth"], $death, $c["epoch"], $popular, $recommended, $mbid);
  mysqli_stmt_execute ($composerstmt);
  $composers++;

  foreach ($c["works"] as $w)
  {
    $wid = (int) $w["id"];
    $subtitle = nullable ($w["subtitle"]);
    $searchterms = nullable ($w["searchterms"]);
    $wpopular = (int) $w["popular"];
    $wrecommended = (int) $w["recommended"];

    mysqli_stmt_bind_param ($workstmt, "iissssii", $wid, $id, $w["title"], $subtitle, $searchterms, $w["genre"], $wpopular, $wrecommended);
    mysqli_stmt_execute ($workstmt);
    $works++;

    foreach ($w["mbids"] ?? [] as $workmbid)
    {
      mysqli_stmt_bind_param ($mbidstmt, "is", $wid, $workmbid);
      mysqli_stmt_execute ($mbidstmt);
    }
  }
}

mysqli_commit ($mysql);
echo "Imported {$composers} composers and {$works} works\n";

// rebuild the search table

putenv ("BASEOPENOPUSDIR=". WEBDIR);

passthru (escapeshellarg (PHP_BINARY). " ". escapeshellarg (BASEDIR. "/cln/omnisearch.php"));
echo "Search table rebuilt\n";

// clear the json cache

foreach (["composer", "genre", "work", "omnisearch"] as $dir)
{
  passthru ("rm -rf ". escapeshellarg (WEBDIR. "/". $dir));
}
echo "Cache cleared\n";
