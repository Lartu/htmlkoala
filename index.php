<?php
	//Constants
	$version = "1.0";
	$builddir = "htmlkoala_build";
	
	//Start HTMLKoala
	echo "\033[1;36mHTMLKoala $version\033[0m, by Martín del Río\n";
	if(count($argv) < 2){
		error("Usage \033[1;37mhtmlkoala <site root directory>\033[0m"); //TODO
	}
	$root = $argv[1];
	
	//Check if directory is valid
	if(!file_exists($root)){
		error("No existe"); //TODO
	}
	if(!is_dir($root)){
		error("Requiero un directorio");
	}
	
	//Create Build Directory
	chdir($root);
	exec("mkdir -p $builddir");
	//Empty Build Directory
	exec("rm -rf $builddir/*");
	//Get all files in directory
	$files = scandir(".");
	//Copy site to build directory
	foreach($files as $file){
		if($file == "." || $file == ".." || $file == $builddir){
			continue;
		}
		echo "- Copying $file to $builddir...\n";
		exec("cp -r $file $builddir");
	}
	
	//Move to build directory
	chdir($builddir);
	//Get all files in directory
	$files = scandir(".");
	$dte = []; //directories to explore
	foreach($files as $file){
		if($file == "." || $file == ".." || $file == $builddir){
			continue;
		}
		//If file is directory
		if(is_dir($file)){
			array_push($dte, $file);
		}
		//If file is file, replace @koala directives
		else{
			echo "- Processing $file...\n";
			$contents = file_get_contents($file);
			replace_contents($contents, $file);
			file_put_contents($file, $contents);
		}
	}
	
	//Done, exit
	echo "\033[1;32mDone!\033[0m\n";
	exit(0);
	
	//------------------ AUX ----------------------
	
	//Error message
	function error($text){
		echo "\033[1;31mError: $text\033[0m\n";
		exit(1);
	}
	
	//Warning message
	function warning($text){
		echo "\033[1;33mWarning: $text\033[0m\n";
	}
	
	//Replace @koala directives
	function replace_contents(&$contents, $filename){
		$lines = explode("\n", $contents);
		$current_dir = getcwd();
		foreach($lines as $linenum => &$line){
			$line = trim($line);
			if(strlen($line) > 0 && $line[0] == "@"){
				$tokens = explode(" ", $line, 3);
				if($tokens[0] != "@koala") continue;
				//Directive switch
				switch($tokens[1]){
					case "include":
						//Get filename of file to include
						$fti = $tokens[2];
						//Check if the file we want to include exists
						if(!file_exists($fti)){
							echo "\t";
							warning("File $fti not found (required from $filename).");
							$line = "<div style='background:#FFDDDD; display: inline-block; padding: 2px;'><code>$line</code></div>";
						}
						//Load required file
						else{
							$line = file_get_contents($fti);
							//Replace contents within line
							chdir(dirname($fti));
							replace_contents($line, $fti);
							chdir($current_dir);
						}
						break;
					default:
						echo "\t";
						$ln = $linenum + 1;
						warning("Unknown directive \033[1;37m".$tokens[1]."\033[1;33m (file $filename, line $ln)");
						$line = "<div style='background:#FFDDDD; display: inline-block; padding: 2px;'><code>$line</code></div>";
						break;
				}
			}
		}
		$contents = join("\n", $lines);
	}
?>
