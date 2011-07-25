<?php

require_once __DIR__ . '/nette.min.php';
require_once __DIR__ . '/../minified/naira.min.php';

use \Nette\Utils\Strings as String;
use \Nette\Templating\Filters\Naira;
use \Nette\Templating\Filters\NairaException;

if (isset($argv[1]) && isset($argv[2]) && isset($argv[3]) && isset($argv[4])
 && (($argv[1] == '--watch' && $argv[3] == '--out')
 ||  ($argv[3] == '--watch' && $argv[1] == '--out'))) {
	if ($argv[1] == '--watch') {
		$watch = $argv[2]; // todo strip trailing slashes
		$out = $argv[4];
	} else {
		$watch = $argv[4];
		$out = $argv[2];
	}
	
	$changes = array();
	
	if (!file_exists($watch)) {
		echo "File or directory not found.\n";
		die();
	}
	
	if (realpath($watch) === realpath($out)) {
		echo "Output directory must differ from watched.\n";
		die();
	}
	
	$isDir = is_dir($watch);
	if ($isDir) {
		echo "Watching directory $watch for changes...\n";
	} else {
		echo "Watching file $watch for changes...\n";
	}
	
	$naira = new Naira();
	while (TRUE) {
		$files = array();
		if ($isDir) {
			foreach (scanDir($watch) as $file) {
				if (in_array($file, array('.', '..'))) continue;
				$files[] = array('in' => "$watch/$file", 'out' => "$out/$file");
			}
			
		} else {
			$files[] = array('in' => $watch, 'out' => $out);
		}
		
		foreach ($files as $res) {
			$file = $res['in'];
			$change = fileMTime($file);
			if (!isset($changes[String::webalize($file)]) || $change !== $changes[String::webalize($file)]) {
				$changes[String::webalize($file)] = $change;
				echo @date("H:i:s") . " \t$file ";
				try {
					if ($isDir && !file_exists($out)) {
						mkdir($out);
					}
					file_put_contents($res['out'], $naira(file_get_contents($file)));
					echo "compiled\n";
				} catch (NairaException $e) {
					$msg = String::replace($e->getMessage(), '~\.$~') . ' on line ' . $e->sourceLine . '.';
					echo "ERROR: $msg\n";
				}
			}
		}
		
		clearstatcache();
		sleep(1);
	}
	
} else {
	echo 'Naira - HTML preprocessor
--watch file --out file
	Watches a single file for changes
	--watch src/input.html --out compiled/template.html
	
--watch directory --output directory
	Watches all files in a directory for changes
	--watch src/ --out compiled/
';
}
