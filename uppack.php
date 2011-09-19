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
/**
 * Return the value of a command line option.
 * @return The option value if set, false otherwise.
 */
function get_option($long, $short = null) {
	global $argv;
		
	if (($pos = array_search('--' . $long, $argv)) !== false) {
		return $argv[$pos + 1];
	}
	if (!empty($short) && ($pos = array_search('-' . $short, $argv)) !== false) {
		return $argv[$pos + 1];
	}	
	return false;
}
/**
 * Return if a command line flag is set.
 * @return true if the flag is set, false otherwise.
 */
function get_flag($long, $short = null) {
	global $argv;

	if (($pos = array_search('--' . $long, $argv)) !== false) {
		return true;
	}
	if (!empty($short) && ($pos = array_search('-' . $short, $argv)) !== false) {
		return true;
	}	
	return false;
}

if (in_array($argv[1], array('--help', '-help', '-h', '-?'))) {
?>

uppack.php: Export files from a subversion working copy which were changed in a set of commits.
Usage: uppack.php [options]

  Files are copied from your current checkout, so make sure that there are no modifications.
  
  Examples:
    uppack.php -r 100
    uppack.php -r 100:110
    uppack.php -p path/to/repository

Options:
  -r [--revision] ARG : Specify a commit or range of commits (inclusive) to 
                        ouput.  If ommited, HEAD is used.
  -p [--path] ARG     : Specify the path to the repository to use.
  -m [--merge]        : If the output directory already exists, proceed anyways.

<?php
} else {
	// TODO add flag to delete existing package contents first.
	if (is_dir('uppack')  && !get_flag('merge', 'm')) {
		echo "uppack directory already exists: \n\tRemove directory or use --merge to append files to existing directory.";
		exit();
	}
	$execdir = getcwd();

	// Parse revisions to use.
	$revision = get_option('revision', 'r');
	if ($revision) {
		$revision = escapeshellarg($revision);
	} 
	else {
		$revision = 'HEAD';
	}

	// Change to directory if specified for running svn command.
	$sourcePath = get_option('path', 'p');
	if ($sourcePath) {
		chdir($sourcePath);
	}
	// Determine the relative path in the repository, if checkout is not from 
	// repository root.
	exec('svn info', $infoOutput);
	foreach ($infoOutput as $l) {
		unset($matches);
		if (preg_match('<URL:\s(.*)$>', $l, $matches)) {
			$repoPath = $matches[1];
		}
		else if (preg_match('<Repository Root:\s(.*)$>', $l, $matches)) {
			$repoRoot = $matches[1];
		}
	}
	$repoPath = urldecode(str_replace($repoRoot, '', $repoPath));
	exec('svn log -v -r ' . $revision, $logOutput);
	if ($sourcePath) {
		chdir($execdir);
	}
	else {
		$sourcePath = '.';
	}
	
	// Find the last action performed on each path.
	$paths = array();
	foreach($logOutput as $l) {
		unset($matches);
		if (preg_match('<^\s+([MAD])\s(.*)$>', $l, $matches)) {
			$paths[preg_replace('<^' . $repoPath . '>', '', $matches[2])] = $matches[1];
		}
	}
	if (empty($paths)) {
		exit('No Changed Paths');
	}

	$deletions = array();
	foreach ($paths as $filePath=>$change) {
		if ($change == 'D'){
			$deletions[] = $filePath;
		}
		else {
			if (is_file($sourcePath . '/' . $filePath)) {
				$dirpath = preg_replace('</[^/]+$>', '', $filePath);
				if (!is_dir('uppack' . $dirpath)) {
					echo 'prepping path: uppack' . $dirpath . "\n";
					prep_path('uppack' . $dirpath);
				}
				echo 'copying file: ' . $filePath . "\n";
				copy($sourcePath . $filePath, 'uppack' . $filePath) or die('could not copy: ' . $filePath);
			}
		}
	}

	if (!empty($deletions)) {
		file_put_contents('uppack/uppack-deletions.txt', implode("\n", $deletions)) or die('Could not write deletions file');
	}
}
