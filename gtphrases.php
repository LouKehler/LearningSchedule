<?php
//~ function doLogGTPhrases ($msg) {
//~ 	$year = date('Y');
//~ 	$month = date('m');
//~ 	$logFile=fopen("gtphrases.log.txt","a");
//~ 	fwrite($logFile, $msg . "\n");
//~ 	fclose($logFile);
//~ }

$folder = 'lang/';

include 'languages.php';

//~ echo "number of languages = " . count($languages) . "<br>\n";
//~ exit();

################################################################################
### CLEANUP (text_string)
################################################################################
//~ function CLEANUP (
//~ 	$str	# Text string to be cleaned up.
//~ 	) {
//~ ################################################################################
//~ ###    Removes UTF-8 BOM, line-breaks, and leading and trailing whitespace
//~ ### 	Returns cleane up text string.
//~ ################################################################################
//~ 	
//~ 	$before = $str;
//~ 	$len = strlen($str);
//~ #	$str =~ s/\R//g;		 # Remove line-breaks, regardless of type or platform using.
//~ 	$str = preg_replace("/[\n\r]/","",$str);
//~ #	str_replace(["\n\r", "\n", "\r"], '', $str);
//~ #	$str =~ s/^\x{FEFF}//;   # Remove UTF-8 BOM (ZERO WIDTH NO-BREAK SPACE (U+FEFF)) from first line if present.
//~ 	$bom = pack('H*','EFBBBF');
//~ 	$str = preg_replace("/^$bom/", '', $str);
//~ #	$str =~ s/^\s+|\s+$//g;  # Remove leading and trailing whitespace
//~ 	$str = preg_replace('/^\s+|\s+$/', '', $str);
//~ 	
//~ 	return $str;
//~ }

function create_list_of_phrases($eng_phrase_file) {

	global $folder;
	
	$str = '';
	doLog("Reading English phrase file $folder$eng_phrase_file");
	
	$infile = fopen($folder . $eng_phrase_file, "r");
	
	# READF: 
	$ln = 0;
	while (!feof($infile)){
		$line = fgets($infile);
		$ln++;
		
//~ 		if ($ln >= 120) {
//~ 			break;
//~ 		}
//~ 	    $line = CLEANUP($line);

		$cmt_ix = strpos($line, '#'); # Returns -1 if no occurrance.
#		doLog("Comment inx=$cmt_ix\n");
		if ($cmt_ix !== false && $cmt_ix == 0) { # if ($cmt_ix >= 0) allows for partial-line comments
#			doLog("Index for comment: $cmt_ix     -$line-\n");
			$line = substr($line, 0, $cmt_ix);
#			doLog("Line after stripping comment: $line\n");
		}
		
//~  		echo "$ln " . $line . "<br>\n";
		if (strlen($line) == 0) {
//~ 			doLog("Skipping blank line\n");
			continue; # Skip over blank lines or full-line comments
		}
		
#		$line =~ /<(.*)>(.*)/;
		preg_match('/<(.*)>(.*)/', $line, $matches);
		if (count($matches) == 3) {
//~ 			doLog("***** ERROR: Could not parse line " . escapeChars($line) . "\n");
//~ 			exit;
			$ltag  = $matches[1];			# Hash key
			$ltext = $matches[2];			# Hash value
//~ 			doLog("ltag=$ltag, ltext=$ltext");
			if (str_contains($ltext, 'https://')) {
				continue;
			}
			if ($ltag == 'locale' || $ltag == 'font' || $ltag == 'direction') { // ignore
				continue;
			}
//~ 			echo "$ln ltag=$ltag, ltext=$ltext<br>\n";
			$str .= '&q=' . urlencode($ltext);
		}
	}
	fclose($infile);
	return $str;
}

function create_new_phrase_file($eng_phrase_file, $target_phrase_file, $str) {

	global $folder;

	$infile = fopen($folder . $eng_phrase_file, "r");
	$outfile = fopen($folder . $target_phrase_file, "w");
	
	$ln = 0;
	$i = 0;
	while (!feof($infile)){
		$line = fgets($infile);
		$ln++;
		
//~ 	    $line = CLEANUP($line);

		$cmt_ix = strpos($line, '#'); # Returns -1 if no occurrance.
#		doLog("Comment inx=$cmt_ix\n");
		if ($cmt_ix !== false && $cmt_ix == 0) { # if ($cmt_ix >= 0) allows for partial-line comments
			fwrite($outfile, "$line\n");
			continue;
		}
		
		if (strlen($line) == 0) {
			fwrite($outfile, "\n");
			continue; # Skip over blank lines or full-line comments
		}
		
#		$line =~ /<(.*)>(.*)/;
		preg_match('/<(.*)>(.*)/', $line, $matches);
		if (count($matches) == 3) {
//~ 			doLog("***** ERROR: Could not parse line " . escapeChars($line) . "\n");
//~ 			exit;
			$ltag  = $matches[1];			# Hash key
			$ltext = $matches[2];			# Hash value
//~ 			doLog("ltag=$ltag, ltext=$ltext");
//~ 			echo "ltag=$ltag, ltext=$ltext<br>\n";
			if (str_contains($ltext, 'https://')) {
				fwrite($outfile, "$line\n");
				continue;
			}
			if ($ltag == 'locale' || $ltag == 'font' || $ltag == 'direction') { // ignore
				fwrite($outfile, "$line\n");
				continue;
			}
			if ($i >= count($str)) {
				echo "Ran out of translated strings<br>\n";
				exit();
			}
			fwrite($outfile, "<$ltag>" . str_replace('#39;', "'", urldecode($str[$i++]) . "\n"));
		} else {
			fwrite($outfile, "$line\n");
		}
	}
	fclose($infile);
	fclose($outfile);
}

################################################################################
### get_language_code ($targetLang)
################################################################################
//~ function get_language_code (
//~ 	$targetLang	# Target language.
//~ 	) {
//~ ################################################################################
//~ ###    Searches through the list of languages for the target language and returns the associated code for GT
//~ ### 	Returns the language code for calling Google Translate
//~ ################################################################################
//~ 	
function get_language_code($targetLang) {

	global $languages;

//~ 	doLog("Number of languages = " . count($languages));

	$sourceLangCode = "en";
	$targetLangCode = "en"; # English - if we have it at the end, then we didn't find the language
	if ($targetLang == "English") { // this should not happen
		echo "Error: Should not be calling Google Translate for English.";
		exit;
	} else {
		doLog("Scanning through list of languages");
		for ($i = 0; $i < count($languages); $i++) {
			if ($targetLang == $languages[$i][0]) {
				doLog("Found language $targetLang");
				$targetLangCode = $languages[$i][1];
				break;
			}
		}
		if ($targetLangCode == "en") {
			doLog("Unknown language $targetLang");
			echo "Error: Unknown language $targetLang<br>\n";
			exit;
		}
	}

	echo "targetLang=$targetLang, targetLangCode=$targetLangCode<br>\n";
	return $targetLangCode;
}

################################################################################
### doGT ($sourceText, $targetLang)
################################################################################
//~ function doGT (
//~ 	$sourceText	# Text string to be translated.
//~ 	$targetLang	# Target language.
//~ 	) {
//~ ################################################################################
//~ ###    Calls Google Translate to translate a list of English strings into the target language
//~ ### 	Returns the list of translated strings
//~ ################################################################################
//~ 	
function doGT($sourceText, $targetLang) {

	include_once 'gtk.php';

	$targetLangCode = get_language_code($targetLang);

	$url = 'https://www.googleapis.com/language/translate/v2?key=' . gtk('sk') . '&source=en&target=' . $targetLangCode . $sourceText;

	$handle = curl_init();

	curl_setopt($handle, CURLOPT_URL, $url);
	curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);     //We want the result to be saved into variable, not printed out
	curl_setopt($handle, CURLOPT_REFERER, 'http://leekehlerdesign.ca/LearningSchedule/gtphrases.php');
	$response = curl_exec($handle);                         
	$responseCode = curl_getinfo($handle, CURLINFO_HTTP_CODE);      //Here we fetch the HTTP response code
	curl_close($handle);

	if ($responseCode == 400) {
		echo "Failed to translate, responseCode=$responseCode<br><br>\n";
		echo "$response<br>\n";
		exit();
	}
	$result = json_decode($response, true);
//~ 	print_r($result);
//~ 	exit();
	
//~ 	$text = $result['data']['translations'][0]['translatedText']; //->translations[0]->translatedText;

	$language_strings = array(); # create array of translated strings
	for ($i = 0; $i < count($result['data']['translations']); $i++) {
		$language_strings[$i] = str_replace('&amp;', '&', str_replace('%20', ' ', $result['data']['translations'][$i]['translatedText']));
//~ 		$tr[$i] = str_replace("۔","", trim($tr[$i])); // remove Urdu fullstop
//~ 		$ret .= ($i != 0 ? '<br>' : '') . $language_strings[$i] . ',' . $tr[$i];
//~ 		echo $language_strings[$i] . "<br>\n";
	}
//~ 	print_r($ret);
//~ 	print_r(json_decode($response, true));

//~ 	exit();
	return $language_strings;
}

################################################################################
### create_language_phrase_file ($lang_phrase_file)
################################################################################
//~ function create_language_phrase_file (
//~ 	$lang_phrase_file	# File name of the list of phrases in the given language
//~ 	) {
//~ ################################################################################
//~ ###    Calls create_list_of_phrases to read the list of phrases from the English phrase file
//~ ###		Calls doGT to translate these into the target language
//~ ###		Calls create_new_phrase_file to write these translated strings to the target language phrase file
//~ ### 	Returns nothing
//~ ###		
//~ ###		Caller: generate.php if it detects that the language phrase file doesn't exist
//~ ################################################################################
//~ 	
function create_language_phrase_file($lang_phrase_file) {

	doLog("About to create language phrase file $lang_phrase_file");
	$targetLang = substr($lang_phrase_file, 3, strlen($lang_phrase_file)-7);
	doLog("Target language $targetLang");
	$eng_phrase_file = substr($lang_phrase_file, 0, 3) . "English.txt";
	doLog("English language phrase file $eng_phrase_file");
	
	echo "English phrase file=$eng_phrase_file<br>\n";
	echo "$targetLang phrase file=$lang_phrase_file<br><br>\n";
	
	// read the English phrase file and compile a list of 
	
	$sourceText = create_list_of_phrases($eng_phrase_file);
//~ 	echo $sourceText . "<br>\n";
//~ 	$sourceText = strtolower(str_replace(' ', '%20', $sourceText));
	
//~ 	$sourceText = str_replace(' ', '%20', $sourceText);

	$language_strings = doGT($sourceText, $targetLang);

	// now write entries to suggestedGlosses file
	create_new_phrase_file($eng_phrase_file, $lang_phrase_file, $language_strings);
}

################################################################################
### getVideoNames ($lang_phrase_file)
################################################################################
//~ function getVideoNames (
//~ 	$videos	# File name of the list of phrases in the given language
//~ 	$targetLang	# File name of the list of phrases in the given language
//~ 	$course	# File name of the list of phrases in the given language
//~ 	) {
//~ ################################################################################
//~ ###    Using the list of videos passed to it, it reads through the file containing the list of translated video names
//~ ###    creating the list of translated names
//~ ###		If there are names that have not been translated then it builds a list for doGT
//~ ###		Calls doGT to translate any these into the target language
//~ ### 	Replace the names in the videos array with the ones from the target language
//~ ### 	Returns nothing
//~ ###		
//~ ###		Caller: generate.php to get the translated names of videos in the target language
//~ ################################################################################
//~ 	

//~ function doGTDummy($sourceText, $targetLang) {
//~ 	$strgs = explode('&q=', $sourceText);
//~ 	return $strgs;
//~ }

function getVideoNames($videos, $targetLang, $course) {

	global $folder;
	
	doLog("");
	doLog("Getting video names in $targetLang for $course course\n");
	
	$targetVideoNamesFile = $folder . ($course == 'Aleph' ? 'AWB' : 'AWA') . $targetLang . 'VideoNames.txt';
	doLog("$targetLang video names file=$targetVideoNamesFile\n");
	
	$sourceText = array(); // for calling GT
	$names = array();
	
	if (file_exists($targetVideoNamesFile)) {
		// read the file into array
		doLog("Reading video names file $targetVideoNamesFile\n");
		$infile = fopen($targetVideoNamesFile, "r");
		$ln = 0;
		while (!feof($infile)) {
			$line = fgets($infile);
			if (!feof($infile)) { // ignore the last line
				$line = preg_replace("/[\n\r]/","",$line); # remove all returns
				$names[$ln++] = $line;
				doLog("Read video name $ln $line\n");
			}
		}
		fclose($infile);
	} else { // the target language video names file doesn't exist
		// just fall through and translate all the names and write the file
	}
	doLog("Size of names = " . count($names) . "\n");
	if (count($names) < count($videos)) { # if there are any strings to translate
		doLog("number of names in the target language = " . count($names)
			. " number of videos = " . count($videos) . "\n");
		// create the list of English names for calling GT
		$sourceText = '';
		$nbrNames = 0;
		for ($i = count($names); $i < count($videos); $i++) {
			doLog("Video $i '" . $videos[$i]['Title_en_US'] . "'\n");
			if ($videos[$i]['Title_en_US'] != '') {
				$sourceText .= '&q=' . urlencode($videos[$i]['Title_en_US']);
//~ 				doLog("Video title $i still " . $videos[$i]['Title_en_US'] . "\n");
				$nbrNames++;
				if ($nbrNames >= 100) { // call GT to translate
					doLog("\nCalling doGT() for 100 names\n");
					$new_names = doGT($sourceText, $targetLang);
					$sourceText = '';
					$nbrNames = 0;
					// append the translated names to the array
					$j = 0;
					for ($k = count($names); $j < 100 && $k < count($videos); $k++) {
//~ 						doLog("Video title $k still " . $videos[$k]['Title_en_US'] . "\n");
						if ($videos[$k]['Title_en_US'] == '') {
							doLog("Video $k ''\n");
							$names[$k] = '';
						} else {
//~ 							doLog("Video title $k still " . $videos[$k]['Title_en_US'] . "\n");
							$names[$k] = $new_names[$j++];
							doLog("Video[$k] '" . $videos[$k]['Title_en_US'] . "' -> names[$k] '" . $names[$k] . "'\n");
						}
					}
					doLog("\n");
				}
			}
		}
//~ 		doLog("number of names left to translate remaining $nbrNames names\n");
//~ 		exit();
		
		// call GT to translate the remaining list of names
		if ($sourceText != '') { // more to translate
			doLog("\nCalling doGT() for $nbrNames names\n");
			$new_names = doGT($sourceText, $targetLang);
			// append the translated names to the array
			$j = 0;
			for ($i = count($names); $i < count($videos); $i++) {
				if ($videos[$i]['Title_en_US'] == '') {
					doLog("Video $i ''\n");
					$names[$i] = '';
				} else {
					$names[$i] = $new_names[$j++];
					doLog("Video[$i] '" . $videos[$i]['Title_en_US']  . "' -> names[$i] '" . $names[$i] . "'\n");
				}
			}
		}
		// write all values to the file
		doLog("Writing video names file $targetVideoNamesFile\n");
		$outfile = fopen($targetVideoNamesFile, "w");
//~ 		fwrite($outfile, "Title line\n");
		
		for ($i = 0; $i < count($names); $i++) {
			fwrite($outfile, $names[$i] . "\n");
		}
		fclose($outfile);
	}
	
	// now replace the entries in the $videos array
	for ($i = 0; $i < count($names); $i++) {
		$videos[$i]['Title_en_US'] = $names[$i];
	}
	return $videos;
}

//~ create_language_phrase_file("AWBGreek.txt");
//~ echo "Done<br>\n";
?>