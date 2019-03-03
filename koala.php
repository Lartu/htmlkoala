<?php
	//Constants
	$version = "1.1";
	$builddir = "htmlkoala_build";
	
	//Data
	$constants = [];
	$variables = [];
	$forced = [];
	
	//Start HTMLKoala
	echo "\033[1;36mHTMLKoala $version\033[0m, by Martín del Río\n";
	if(count($argv) < 2){
		error("Usage \033[1;37mhtmlkoala <site root directory>\033[0m");
	}
	$root = $argv[1];
	
	//Check if directory is valid
	if(!file_exists($root)){
		error("The requested directory doesn't exist.");
	}
	if(!is_dir($root)){
		error("$root is not a directory.");
	}
	
	//Create Build Directory
	chdir($root);
	exec("mkdir -p $builddir");
	//Empty Build Directory
	exec("rm -rf $builddir/*");
	
	//Load constants file
	echo "- Loading constants.koala...\n";
	if(file_exists("constants.koala")){
		$lines = explode("\n", file_get_contents("constants.koala"));
		foreach($lines as  $linenum => $line){
            if(strlen($line) < 1) continue;
			$tokens = explode(" ", $line, 2);
			if(count($tokens) < 2){
				error("Malformed constant on line " . ($linenum + 1) . " of constants.koala (" . $line . ")");
			}
			$constants[$tokens[0]] = $tokens[1];
		}
		echo "- Found " . count($constants) . " constant(s).\n";
	}else{
		echo "\t";
		warning("Constants.koala not found");
	}
	
	//Get all files in directory
	$files = scandir(".");
	//Copy site to build directory
	foreach($files as $file){
        //Hidden files are copied but not processed
        //.git repository in the same level this script is running is not copied.
		if($file == "." || $file == ".." || $file == $builddir || $file == "constants.koala" || $file == ".git"){
			continue;
		}
		echo "- Copying $file to $builddir...\n";
		exec("cp -r $file $builddir");
	}
	//Move to build directory
	chdir($builddir);
	
	$dte = ["."]; //directories to explore
	while(count($dte) > 0){
		$dir = array_pop($dte);
		echo "- Scanning directory '$dir'\n";
		//Get all files in directory
		$files = scandir($dir);
		foreach($files as $file){
            //Don't process hidden files
			if($file == "." || $file == ".." || $file == $builddir || (strlen($file) > 1 && $file[0] == '.')){
				continue;
			}
			$file = $dir . "/" . $file;
			//If file is directory
			if(is_dir($file)){
				array_push($dte, $file);
			}
			//If file is file, replace @koala directives
			else if(fileistext($file)){
				$contents = file_get_contents($file);
				if(replace_contents($contents, $file))
					file_put_contents($file, $contents);
				else
					array_push($forced, $file);
			}
		}
	}
	
	while(count($forced) > 0){
		$file = array_pop($forced);
		$contents = file_get_contents($file);
		replace_contents($contents, $file);
		file_put_contents($file, $contents);
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
		$replace = true;
		echo "- Processing $filename...\n";
		global $constants, $variables;
		$lines = explode("\n", $contents);
		foreach($lines as $linenum => &$line){
			$oldLine = $line;
			$line = trim($line);
			if(strlen($line) > 0 && $line[0] == "@"){
				$tokens = explode(" ", $line);
				if($tokens[0] != "@koala"){
					$line = $oldLine;
					continue;
				}
				//Directive switch
				switch($tokens[1]){
					case "no-overwrite":
						$replace = false;
						$line = "";
						break;
					//TODO check that directives receive all the parameters they need
					case "include":
						$tokens = explode(" ", $line, 3);
						//Get filename of file to include
						$fti = dirname($filename) . "/" . $tokens[2];
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
							replace_contents($line, $fti);
						}
						break;
					case "constant":
						$tokens = explode(" ", $line, 3);
						//Get constant to replace
						$constant = $tokens[2];
						//Check if the constant has been defined
						if(!array_key_exists($constant, $constants)){
							echo "\t";
							warning("Undefined constant $constant (required from $filename).");
							$line = "<div style='background:#FFDDDD; display: inline-block; padding: 2px;'><code>$line</code></div>";
						}
						//Replace constant
						else{
							$line = $constants[$constant];
						}
						break;
					case "set":
						//Get variable to set
						$tokens = explode(" ", $line, 4);
						$var = $tokens[2];
						$variables[$var] = $tokens[3];
						echo "\tSet $var to " . $tokens[3] . "\n";
						$line = "";
						break;
					case "get":
						//Get variable to set
						$tokens = explode(" ", $line, 3);
						$var = $tokens[2];
						//Check if the variable has been defined
						if(!array_key_exists($var, $variables)){
							echo "\t";
							warning("Undefined variable $var (required from $filename).");
							$line = "<div style='background:#FFDDDD; display: inline-block; padding: 2px;'><code>$line</code></div>";
						}
						//Replace variable
						else{
							$line = $variables[$var];
							echo "\tLooked for $var and got " . $variables[$var] . "\n";
						}
						break;
					case "timestamp":
						$line = date("Y-m-d") . ", " . date("h:i:sa");
						break;
                    case "exec":
						$tokens = explode(" ", $line, 3);
						//Get constant to replace
						$command = $tokens[2];
						$line = exec($command);
						break;
					default:
						echo "\t";
						$ln = $linenum + 1;
						warning("Unknown directive \033[1;37m".$tokens[1]."\033[1;33m (file $filename, line $ln)");
						$line = "<div style='background:#FFDDDD; display: inline-block; padding: 2px;'><code>$line</code></div>";
						break;
				}
			}
			else $line = $oldLine;
		}
		$contents = join("\n", $lines);
		return $replace;
	}
	
	function fileistext($filename){
		// return mime type ala mimetype extension
		$finfo = finfo_open(FILEINFO_MIME);

		//check to see if the mime-type starts with 'text'
		return substr(finfo_file($finfo, $filename), 0, 4) == 'text';
	}
?>
