<?php
function doLog ($msg) {
	$year = date('Y');
	$month = date('m');
	$logFile=fopen("buildLocalizationFile.log.txt","a");
	fwrite($logFile, $msg . "\n");
	fclose($logFile);
}

include 'languages.php';

$tags = [];
$strngs = [];
$list_for_GT = '';

function addString($tag, $str) {
	global $tags, $strngs, $list_for_GT;
	
	$tags[count($tags)] = $tag;
	$strngs[count($strngs)] = $str;
//~ 	$list_for_GT .= '&q=' . urlencode($str);
	$list_for_GT .= '&q=' . str_replace(' ', '%20', $str);
}

function create_list_of_phrases() {
	global $languages;
	
	addString('title1', 'Aleph with Beth and Alpha with Angela');
	addString('title2', 'Learning Scheduler');
	addString('language', 'Language');
	addString('Learning program', 'Learning program');
	addString('timePerDay', 'Time per day');
	addString('copy', 'Copy');
	addString('copied', 'Copied');
	addString('print', 'Print');
	addString('Aleph', 'Aleph with Beth');
	addString('Alpha', 'Alpha with Angela');
	for ($i = 0; $i < count($languages); $i++) {
//~ 	$list_for_GT = strtolower(str_replace(' ', '%20', $list_for_GT));
		addString(strtolower($languages[$i][0]), $languages[$i][0]);
	}
}

function translate_for_language($targetLangCode) {

	global $list_for_GT, $strngs;

//~ 	echo $list_for_GT . "<br>\n";
	
	include_once 'gtk.php';

	$url = 'https://www.googleapis.com/language/translate/v2?key=' . gtk('sk') . '&source=en&target=' . $targetLangCode . $list_for_GT;

//~ 	echo $url . "<br>\n";
	doLog($url);
//~ 	return $strngs;

	$handle = curl_init();

	curl_setopt($handle, CURLOPT_URL, $url);
	curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);     //We want the result to be saved into variable, not printed out
	curl_setopt($handle, CURLOPT_REFERER, 'http://leekehlerdesign.ca/LearningSchedule/buildLocalizationFile.php');
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
	
	// build array of translated strings
	$trans = [];
	for ($i = 0; $i < count($result['data']['translations']); $i++) {
		$trans[$i] = str_replace('%20', ' ', $result['data']['translations'][$i]['translatedText']);
//~ 		echo $src[$i] . "<br>\n";
	}
//~ 	print_r($ret);
//~ 	print_r(json_decode($response, true));

	return $trans;
}

function create_localization_file($localization_file_name) {

	create_list_of_phrases();
	
	global $tags, $strngs, $languages;

	$outfile = fopen('js/' . $localization_file_name, "w");
	
	fwrite($outfile, "var localization = {\n");
//~ 	fwrite($outfile, "'English': {\n");
//~ 	for ($i = 0; $i < count($strngs); $i++) {
//~ 		fwrite($outfile, "\t'" . $tags[$i] . "': '" . $strngs[$i] . "',\n");
//~ 	}
//~ 	fwrite($outfile, "\t},\n");
	for ($l = 0; $l < count($languages); $l++) {
		fwrite($outfile, "'" . $languages[$l][0] ."': {\n");
		if ($languages[$l][0] == "English") {
	 		$translated = $strngs;
		} else {
			$translated = translate_for_language($languages[$l][1]);
		}
		echo "Translated into " . $languages[$l][0] . "<br>\n";
		for ($i = 0; $i < count($translated); $i++) {
			fwrite($outfile, "\t'" . $tags[$i] . "': '" . str_replace('&#39;', "\\'", str_replace("'", "\\'", $translated[$i])) . "',\n");
		}
		fwrite($outfile, "\t'direction': '" . $languages[$l][2] . "',\n");
		fwrite($outfile, "\t'font': '" . $languages[$l][3] . "',\n");
		fwrite($outfile, "\t},\n");
	}
	fwrite($outfile, "};\n");
	
	fclose($outfile);
}

create_localization_file("localization.js");
echo "Done<br>\n";
?>