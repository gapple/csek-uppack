#!/usr/bin/php
<?php

function prep_path($path) {
	$sections = explode('/', $path);
	$curpath = '.';
	foreach ($sections as $s) {
		$curpath .= '/' . $s;
		if (!is_dir($curpath)) {
			mkdir($curpath) or die('could not create directory: ' . $curpath);
		}
	}
}

if (in_array($argv[1], array('--help', '-help', '-h', '-?'))) {
?>

This script exports files from a subversion repository which have changed in the specified commits.
Files are copied from your current checkout, so make sure that there are no modifications.

  Usage:
  <?php echo $argv[0]; ?> [options]


  -r specify commits to ouput.  If ommited, the latest commit is used.

<?php
} else {
	if (is_dir('uppack')) {
		echo 'uppack directory already exists: Remove directory before running again.';
		// TODO delete directory if flag set.
		exit();
	}
	$execdir = getcwd();

	// Parse revisions to use.
	if (($pos = array_search('-r', $argv)) !== FALSE) {
		$revision = escapeshellarg($argv[$pos+1]);
	}
	else {
		$revision = 'HEAD';
	}

	// Change to directory if specified for running svn command.
	if (($pos = array_search('-p', $argv)) !== FALSE) {
		chdir($argv[$pos+1]);
		$changedPath = $argv[$pos+1];
	}
	exec('svn log -v -r ' . $revision, $output);
	if (isset($changedPath)) {
		chdir($execdir);
	}
	else {
		$changedPath = '.';
	}

	// Find the last action performed on each path.
	$paths = array();
	foreach($output as $l) {
		unset($matches);
		if (preg_match('<^\s+([MAD])\s/?(.*)$>', $l, $matches)) {
			$paths[$matches[2]] = $matches[1];
		}
	}
	if (empty($paths)) {
		exit('No Changed Paths');
	}

	$deletions = array();
	foreach ($paths as $path=>$change) {
		if ($change == 'D'){
			$deletions[] = $path;
		}
		else {
			if (is_file($changedPath . '/' . $path)) {
				$dirpath = preg_replace('</[^/]+$>', '', $path);
				if (!is_dir('uppack/' . $dirpath)) {
					echo 'prepping path: uppack/' . $dirpath . "\n";
					prep_path('uppack/' . $dirpath);
				}
				echo 'copying file: ' . $path . "\n";
				copy($changedPath . '/' . $path, 'uppack/' . $path) or die('could not copy: ' . $path);
			}
		}
	}

	if (!empty($deletions)) {
		file_put_contents('uppack/uppack-deletions.txt', implode("\n", $deletions)) or die('Could not write deletions file');
	}
}
