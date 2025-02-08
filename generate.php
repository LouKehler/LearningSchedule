<?php
//~ debug_print("Syntax OK\n"); # this is before everything as a way to know if this PHP failed syntax

$debug = true;	# Debugging
$log_file_name = 'generate.log';
$debug_to_log_file = true;

$html_out = true; # output HTML rather than plain text
$embed_stylesheet = true;

################################################################################
### awbsched.pl
### 
### Author: Carol C. Kankelborg        cckborg2@kankelborg.net
### Conversion to HTML/PHP			Sheldon Kehler 		sheldonkehler@gmail.com
###
################################################################################
### Revision History
################################################################################
#    
# 09/20/2021  	Created
# 10/30/2021	Program is essentially complete.
# 11/01/2021	Removed story title from output. (It was redundant)
# 03/30/2023	Finished a number of wish-list items, to make program more
#				generic for Hebrew and Greek, and allow for titles of videos 
#				in different languages. Removed note to wait for more quizzes
#				if there are currently none. There may not be any more and 
# 				Alpha with Angela is not generating any anyway.
# 09/05/2024	Added code to substitute the default (i.e. English) video title when
#				the target language title field is blank in the video database.
# 12/30/2024   Converted to HTML front end and PHP by Sheldon Kehler
# 12/31/2024    Changed to generate HTML output
# 01/07/2025	  Incorporated latest changes from Carol
#
################################################################################
### Wish List/To Do List
################################################################################
# Error checking for inputs (Done, 7/25/22)
# Put arguments used to generate the schedule in output file. (Done, 1/28/22)
# Use a default output file name, based on input name and arguments. (Done, 1/28/22)
# Change behavior for extra story videos which duplicate lessons. (Done)
# Split related videos into multiple days if too many. (Done)
# Check if file is already present and newer than both the video file and language strings file and if so, just serve it up 
#	- each output file should be unique for a given set of options
#
# For Alpha with Angela
# Change how bonus lessons are handled. Alpha = a, Aleph = b. Alpha had multiple for 
#      one lesson (a, b, etc.) (Done, 3/29/23 -- added column in database for suffix.)
# Handle no quizzes more elegantly. (Done, 3/29/23 -- eliminated the 
#      "watch for more quizzes message" if quiz count is 0.)
#
# Better yet, format output using html and have the URL as a link. (Done 12/31/2024)
#
#
################################################################################
### Program Description
################################################################################
#
#  usage: progname [options] arguments
#  
# Program Tree: 
# -------------
# MAIN                        - Initializes constants.  Controls program flow.
#	GET_VIDEOS
#		CLEANUP
# 	GET_PHRASES
#		CLEANUP
# 	PRINT_HEADER
#   PRINT_TIME
#	PROCESS_VIDEOS	
#		PRINT_LESSON
#			INCREMENT_COUNTERS
#           	MAKE_SECS
#			PRINT_LINE
#				PRINT_TIME
#					PRINT_TOTAL_TIME
#						MAKE_TIME
#					MAKE_TIME
#			PRINT_QUIZ
#				INCREMENT_COUNTERS
#				QUIZ_LOOP
#					PRINT_LINE
#			PRINT_TOTAL_TIME
#			B_TRANSLATE	
#			MAKE_TIME
#	VIDEO_LIST (Currently not used.)
#	MAKE_SECS
#   USAGE                    - Prints out Usage message for -h option.
#
################################################################################

################################################################################
### Initialization of flags and variables
################################################################################
# Names of files to be required
$path_delim = '/';
$src_path = "";
$src = "generate.php";
// Define the BOM for UTF-8
$bom = pack('CCC', 0xEF, 0xBB, 0xBF);
if ($debug_to_log_file) {
	if (!unlink($log_file_name)) {
		echo 'ERROR: There was an error deleting the file ' . $log_file_name;
	}
	doLog($bom);
}

################################################################################
### print the url that was used to call
################################################################################
// Check if HTTPS is enabled
if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
	$url = "https";
} else {
	$url = "http";
}

// Append the common URL characters
$url .= "://";

// Append the host (domain name or IP) to the URL
$url .= $_SERVER['HTTP_HOST'];

// Append the requested resource to the URL
$url .= $_SERVER['REQUEST_URI'];

// Print the full URL
doLog($url . "\n");


################################################################################
### Process Argument List
################################################################################
# Total number of lessons. Update to actual number when known.
$total_lessons = 999;   

# Default value of switch options.
$incl_prev = false;    # Default: including previous lesson when processing a subset
$xmode = false;    # Unit Mode, but first day is just one lesson.
$display_links = false;  # display links to videos

$course = 'Aleph'; 			# Default: Aleph with Beth
$csv_char = "\t"; 			# Default: delimiter character
$end_lesson = $total_lessons;  	# Default: the highest number lesson. 
$max_main  = 2;				# Lesson Mode, Default: 2 main videos per day
$start_lesson  = 1;				# Default: Starting Lesson Number
$max_time_strg  = "50:00";   		# if Time Mode, Default: 50 minutes of video per day
$max_unit = 0;               # if Unit Mode, 1 unit (2 lessons) per week.
$max_vid  = 3;   	 		# if Video Mode, Default: 3 videos per day
$max_day  = 6;				# Default: Number of study days in week, default
$max_unit = 1; # recommended one unit, two lessons per week

# Default File names
$video_file = 'AWBVideos.tsv';              # Default
$in_file    = "AWBEnglish.txt";  	   		# Default
$out_file   = "AWBEnglish_Schedule.txt";    # Default

# Determine Mode
# Order of precedence: Unit, Time, Video, Main, (Default = Main) 

# Mode-related constants
$main_mode  = 'M';
$time_mode  = 'T';
$unit_mode  = 'U';
$unitX_mode  = 'X';
$video_mode = 'V';

$mode = $time_mode; # default mode

### Process calling parameters
if (array_key_exists('c', $_GET)) {
	$course = $_GET['c'];
}
if (array_key_exists('m', $_GET)) {
	$mode = strtoupper($_GET['m']);
}
if (array_key_exists('x', $_GET)) {
	$mode = $unitX_mode;	   # Unit X mode - overrides mode if set
}

if (array_key_exists('l', $_GET)) {
	$language = $_GET['l'];
}
$lang2ary = array('English'=>'en', 'French'=>'fr', 'Portuguese'=>'po', 'Spanish'=>'sp');
$lang2 = $lang2ary[$language];
$lang3ary = array('English'=>'eng', 'French'=>'fr', 'Portuguese'=>'po', 'Spanish'=>'sp');
$lang3 = $lang3ary[$language];

if (array_key_exists('f', $_GET)) {
	$in_file = $_GET['f'];
}
if (array_key_exists('v', $_GET)) {
	$video_file = $_GET['v'];
}
if (array_key_exists('o', $_GET)) {
	$out_file = $_GET['o'];
} else {
 	$temp_file = explode(".", $in_file);
   	$out_file = $temp_file[0] . "_Schedule." . $temp_file[1];
	if ($html_out) {
		$out_file = substr($out_file, 0, -4) . ".htm"; // replace .txt with .htm
	}
}
debug_print("Video List File name is -$video_file-\n");
debug_print("Input Phrase File name is -$in_file-\n");
debug_print("Output File name is -$out_file-\n");

# Now we know what the filenames are, check whether the HTML file is newer than both the videos and the
# language strings file and if so, just return so that it gets loaded as is. Otherwise fall through and regenerate
# the HTML file.
$video_file_timestamp = filemtime($video_file);
$language_strings_file_timestamp = filemtime($in_file);
if (file_exists($out_file)) {
	$html_out_file_timestamp = filemtime($out_file);
	if ($html_out_file_timestamp > $video_file_timestamp && $html_out_file_timestamp > $language_strings_file_timestamp) {
		# don't need to re-generate the HTML file - so just return so that the page can load it
		debug_print("Output file $out_file already exists so just return\n");
		echo "Output file $out_file already exists so just load it\n";
		return;
	}
}

if (array_key_exists('t', $_GET)) {
	$max_time_strg = $_GET['t'];
}

if (array_key_exists('s', $_GET)) {
	$start_lesson = (int) $_GET['s'];	   # Maximum number of days/week = 7
}
if (array_key_exists('e', $_GET)) {
	$end_lesson = (int) $_GET['e'];
}

if (array_key_exists('w', $_GET)) {
	$max_day = $_GET['w'];	   # Maximum number of days/week = 7
}

################################################################################
### Error Checking of (some) inputs
################################################################################

# Files are checked when opened.
# Mode flags and values are checked below
# getopts('dhpxa:c:e:l:s:t:u:v:w:');

diet("ERROR: Course (-a) $course is not valid.\n", !(($course == 'Aleph') || ($course == 'Alpha')));

diet("ERROR: Starting lesson (-s) $start_lesson must be a positive integer.", !IS_POS_INT($start_lesson));
diet("ERROR: Ending lesson (-e) $end_lesson must be a positive integer.", !IS_POS_INT($end_lesson));
diet("ERROR: Starting lesson (-s) $start_lesson is higher than the ending lesson (-e) $end_lesson.\n", !($end_lesson >= $start_lesson));

if ($incl_prev && !$start_lesson) {
	$incl_prev = false;
	debug_print("WARNING: Include Previous lesson flag (-p) is ignored when no starting lesson is specified.\n");
}

diet("ERROR: Number of days of study per week (-w) $max_day must be an integer between 1 and 7.\n", !(($max_day > 0) && ($max_day <= 7) && IS_POS_INT($max_day)));


################################################################################
### Initialize Global Constants
################################################################################

# Estimated times for various activities
$verse_read_time  =  "2:30";
$alpha_write_time =  "5:00";
$alpha_read_time  = "10:00";
$quiz_take_time   =  "3:00";
$related_avg_time = "20:00";

# UTF-8 constants
if ($html_out) {
	$box = '<input type="checkbox">';
} else {
	$box    = "\u{25A2}"; # should be a blank check-box
}
$bullet = "\u{2022}"; # should be a bullet
$dash   = "\u{2013}"; # should be en-dash

# Character string to be replaced by video-specific text (in %phrases)
$sub_char = '&&';

# Handle modes
if ($mode == $unit_mode) {
	# For Unit Mode, The only option is 1 or 2 units per week.
	diet("ERROR: Number of units per week (-u) $max_unit must be 1 or 2\n.", !((($max_unit == 1) || ($max_unit == 2)) && IS_POS_INT($max_unit)));
	
	debug_print("WARNING: Unit mode overrides number of study days per week (-w) $max_day.\n");
	# The days are divided up according to Main Mode (Default = 2 main videos/day).
	$mode 	  = $unit_mode;
	$max_main = 2 * $max_unit;   
	$max_day  = 7;
	debug_print("Running in Unit Mode: $max_unit\n");
} elseif ($mode 	== $unitX_mode) {
	debug_print("WARNING: UnitX mode overrides number of study days per week (-w) $max_day.\n");

	# For UnitX Mode, The only option is 1 unit per week.
	# The days are divided up according to Main Mode (Default = 2 main videos/day)
	# but the first day has only one video. That way you always watch two different videos
	# each day after that.
	# Review Game & Quizzes are separated into their own day.
	$xmode    = true;
	$mode = $unit_mode;
	$max_unit = 1; 
	$max_main = 2 * $max_unit;   
	$max_day  = 7;
	debug_print("Running in UnitX Mode: $max_unit\n");
} elseif ($mode == $time_mode) {
	# For Time Mode, the estimated sum of each day's activities and videos will not
  	# exceed the maximum time specified. 

	# Time Mode time value must be in the form mm:ss
	diet("ERROR: Time value (-t) $max_time_strg must be of the form mm:ss\n", !preg_match('/(\d+):(\d+)/', $max_time_strg));
	
	$max_time = MAKE_SECS($max_time_strg); # Specified in mm:ss, converted to seconds.

	diet("ERROR: Number of minutes of study per day (-t) $max_time_strg must be less than or equal to 4800:00.\n", ($max_time > (24*60*60)));

	debug_print("Running in Time Mode: $max_time\n");
} elseif ($mode == $video_mode) {
	# For Video Mode, the number of videos must be between 1 and 5.
	diet("ERROR: Number of videos per day (-v) $max_vid must be an integer between 1 and 5\n.", !(($max_vid > 0) && ($max_vid <= 5) && IS_POS_INT($max_vid)));
	
	if ($max_main) {
		debug_print("WARNING: Video mode overrides Main (-m) Mode if it is also set.<br>\n");
	}
	# For Video Mode, each day has no more than the specified number of videos. 
	# Review game & quizzes count as one video. Additional reading and writing exercises
	# are not included in the count.
	debug_print("Running in Video Mode: $max_vid\n");
} elseif ($max_main) {

	# For Main Mode, the number of main videos must be between 1 and 5.
	diet("ERROR: Number of main videos per day (-m) $max_main must be an integer between 1 and 5\n.", !(($max_main > 0) && ($max_main <= 5) && IS_POS_INT($max_main)));

	$mode = $main_mode;
	debug_print("Running in Main Mode: $max_main\n");
} else {
	debug_print("WARNING: No mode has been specified. Default mode is Main (-m 2) Mode.\n");
	# Default if no mode is explicitly set.
	$mode 	  = $main_mode;
	$max_main = 2;  # Default
	debug_print("Running in Default Main Mode: $max_main\n");
}

//~ print "<br>\nMode = $mode<br>\n";
debug_print("Mode = $mode\n");

debug_print("DEBUG MODE\nOptions: Debug: $debug, Prev: $incl_prev\n");
debug_print(", Ancient Lang: $course, Delim: $course, End: $end_lesson, Start: $start_lesson, Week Length: $max_day\n");
debug_print(", Lesson Mode: $max_main, Time Mode: $max_time_strg, Unit Mode: $max_unit, Video Mode: $max_vid\n");
debug_print(", Video File: $video_file, Output File: $out_file.\n");

# Default Locale code
$default_locale = 'en_US';

# Initialize indexes of @fields array.	
# The title field is of the form 'Title_' . $locale. It will be generated after the phrase file is parsed.

$type		  = "Type";
$num		  = 'Num';
$sfx          = 'Sfx';
$title 		  = 'Title';    		# Video Titles, with locale code appended.
$duration	  = 'Duration';
$quizzes      = "Quiz_Count";
$related	  = 'Related';
$verses		  = 'Read_Verses';
$url		  = 'URL';
$verses_pt	  = 'All_Verses';		# Not used by program
$paraphrases  = 'Paraphrases';		# Not used by program

$field_fmt = array("%-6s", "%6s", "%-32s", "%-12s", "%-12s", 
 					"%-12s", "%-32s", "%-50s", "%-32s", "%-32s",
					"%-50s", "%-32s", "%-32s");


### Input Error Checking is done at this point. 

### Create Argument String to output to terminal
### It will be printed out after all input error checking has been done.
### getopts('dhpxa:c:e:l:s:t:u:v:w:');

$argstr =           "Generated by $src for Language Course: $course\n";

$argstr = $argstr . "Covers Lessons $start_lesson to $end_lesson, $max_day days per week.";

if ($incl_prev) {$argstr = $argstr . " Includes Previous Lesson (" . ($start_lesson - 1) . ").";}
$argstr = $argstr . "\n";

$argstr = $argstr . "Mode: ";
if    ($mode == $unit_mode) {$argstr = $argstr . "Unit ($max_unit)";}
elseif ($xmode) {$argstr = $argstr . " UnitX";}
elseif ($mode == $time_mode) {$argstr = $argstr . "Time ($max_time_strg)";}
elseif ($mode == $vid_mode) {$argstr = $argstr . "Video ($max_vid)";}
elseif ($max_main) {$argstr = $argstr . "Main ($max_main)";}
else           {$argstr = $argstr . "Default, Main (2)";}
$argstr = $argstr . "\n";
$argstr = $argstr . "Video File: $video_file\nInput Phrase File: $in_file\nOutput File: $out_file";

debug_print("***********************\n$argstr\n***********************\n");

################################################################################
### Initialize Global Variables
################################################################################

$week_count     = 1;  	# Counts weeks 
$day_count      = 1;	# Counts days within each week

$first 			= 0;	# Flag to indicate processing first lesson
$last 			= 0;	# Flag to indicate processing last  lesson

$main_count     = 0;	# Counts number of main lesson videos for Main mode
$vid_count      = 0;	# Counts number of all  lesson videos for Video mode
$time_count     = 0;	# Counts (estimted) time required for each day. Also used in Time mode.
$lesson_count   = 0;	# Counts number of lessons (1 unit = 2 full lessons) for Unit mode.

$time_adjust    = 0;	# Holds the time required for the present activity. 
						# Needed to keep track of when to increment day due to time limit reached
						# and to report time required for  the previous day.
						
$max_secs  = 0;
$min_secs  = 99999;
$tot_time  = 0;
$tot_days  = 0;

################################################################################
### Build Databases for target-language phrases and lesson videos
################################################################################

# Generate Videos array from file. Determine starting and ending lessons.
$videos = GET_VIDEOS($video_file, $csv_char);
debug_print("Nbr videos=" . count($videos) . "\n");

### Print out array of hashes for debug purposes
if ($debug) {
	global $field_fmt;
	debug_print("*** videos array of hashes ***\n");
	# Print Field Headings
	for ($i = 0; $i < count($fields); $i++) {
		debug_print(sprintf($field_fmt[$i], "-" . $fields[$i] . "-"));
	}
	debug_print("\n");
	# Print Values
	foreach ($videos as $video) {
		for ($k = 0; $k < count($fields); $k++) {
			if (!array_key_exists($fields[$k], $video)) { # array key doesn't exist
				debug_print(sprintf($field_fmt[$k], "-undefined-"));
			} else {
				debug_print(sprintf($field_fmt[$k], "-" . $video[$fields[$k]] . "-"));
			}
		}
		debug_print("\n");
	}
}	
### End of Debug printing    

$max_video  = count($videos) - 1;			# Index for last video
$max_lesson = $videos[$max_video][$num];	# Lesson number for last video
$min_lesson = $videos[0][$num];			# Lesson number for first video

# Determine first and last lessons for schedule
if ($start_lesson < $min_lesson) {
	$start_lesson = $min_lesson;
}
if ($end_lesson  > $max_lesson) {
	$end_lesson   = $max_lesson;
}

debug_print("Starting Lesson: $start_lesson, Ending Lesson: $end_lesson, Size of Video Array: $max_video\n");
debug_print("Highest Lesson: $max_lesson, Lowest Lesson: $min_lesson\n");

# Generate Phrases and Canon hashes (Language specific)

GET_PHRASES($in_file);
$phrases = $phrase_hash; # ***** names should be changed to just $phrases and $canon in GET_PHRASES
$canon   = $canon_hash;

# Select the right Video titles, based on locale in phrase file. (English is default)

if (array_key_exists('locale', $phrases)) {
	$locale = $phrases['locale'];
} else {
	$locale = $default_locale;
}

$title = $title ."_$locale";
$title_default = $title . "en_US";


debug_print("The Locale is $locale. The Column used is $title\n");
debug_print("Highest Lesson: $max_lesson, Lowest Lesson: $min_lesson\n");


################################################################################
### Create Output file and add header text
################################################################################

$outfile = fopen($out_file, "w"); #  || die "ERROR: $! ($out_file)\n";
fwrite($outfile, $bom);

PRINT_HEADER ();

################################################################################
### Cycle Through Lessons
################################################################################

PRINT_TIME(true, 0);

debug_print("Highest Lesson: $max_lesson, Lowest Lesson: $min_lesson\n");

$firstlast = PROCESS_VIDEOS($start_lesson, $end_lesson, $max_video);
$first_vid = $firstlast[0];
$last_vid = $firstlast[1];

# Include list of videos with URLs at the end of the lesson schedule
# Not sure if this is worth including in the file.
# VIDEO_LIST($first_vid, $last_vid);

if ($html_out) {
	FINALIZE_HTML();
}

fclose($outfile);

debug_print("Done\n");

return;


################################################################################
### Daughter Subroutines
################################################################################
################################################################################
### INITIALIZE_HTML (course_type)
################################################################################
function INITIALIZE_HTML () {
################################################################################
### Generates the opening lines of the HTML Document and the <head> tag.
### 
################################################################################

	global $course, $outfile, $embed_stylesheet;
	
	fwrite($outfile, "<!DOCTYPE html>\n");
	fwrite($outfile, "<html>\n");
	
	### Head Element
	fwrite($outfile, "<head>\n");
	
	### Ensure Hebrew and other characters render correctly
	fwrite($outfile, '<meta charset="utf-8">' . "\n");
	
	### Style Sheet
	if ($embed_stylesheet) {
		$styles = file_get_contents("./css/styles.css");
		fwrite($outfile, "<style>\n");
		fwrite($outfile, $styles);
		fwrite($outfile, "</style>\n");
	} else {
		fwrite($outfile, "\t<link rel='stylesheet' type='text/css' href='./css/styles.css'>\n");
	}
	
	HTML_HEADER();

	### End Head Element
	fwrite($outfile, "</head>\n");
	
	return;
}
################################################################################
### FINALIZE_HTML (course_type)
################################################################################
function FINALIZE_HTML () {
################################################################################
### Generates the closing lines of the HTML Document.
### 
################################################################################

	global $outfile;
	
	### Terminate Body Element
	fwrite($outfile, "\t</body>\n");
	
	### Terminate HTML Element
	fwrite($outfile, "</html>\n");
	
	return;
}

################################################################################
### HTML_HEADER (course_type)
################################################################################
function HTML_HEADER () {
################################################################################
### Generates the header element in the HTML Document.
### 
################################################################################

	global $outfile, $course;

	fwrite($outfile, "<header>\n");
	
	debug_print("HTML_HEADER course=$course\n");
	
	if ($course == "Aleph") {
		fwrite($outfile, "\t\t<img style='margin: 0px; width: 100%;' src='images/Aleph with Beth logo.jpg' alt='Aleph with Beth'\n>");
	} else {
		fwrite($outfile, "\t\t<img style='margin: 0px; width: 100%;' src='images/Alpha with Angela logo.jpg' alt='Alpha with Angela'\n>");
	}
	
	fwrite($outfile, "</header>\n");
	return;
}

################################################################################
### PRINT_INTRO_SECTION (course_type)
################################################################################
function PRINT_INTRO_SECTION (
	$type # type of intro section (intro, howto, links)
	) {
################################################################################
### Generates introductory section at the beginning of the learning schedule.
### It called for intro, howto, and links sections.
### The first line in each category is considered a heading.
### Any line which contains "https" is considered a link and will be indented.
###
################################################################################

	global $phrases, $sub_char, $start_lesson, $end_lesson, $dash, $outfile, $html_out;
	
	debug_print("PRINT_INTRO_SECTION Subroutine ($type)\n");

	for ($ix = 0; $ix < count($phrases[$type]); $ix++) {
	    $str = $phrases[$type][$ix];

	    if ($ix == 0) {
			if ($type == 'intro') {
				$str = preg_replace("/$sub_char/", "$start_lesson$dash$end_lesson", $str);
			}
			if ($html_out) {
				fwrite($outfile, "<H1>$str</H1>\n\n");
			} else {
				fwrite($outfile, "*** $str ***\n\n");
			}
	    } elseif (strpos($str, 'https') !== false) { // has a link
			$posn = strpos($str, 'https');
//~ 				echo "ERROR: posn=$posn in $str<br>\n";
			$txt = substr($str, 0, $posn);
			$URL = substr($str, $posn) . "\n";
//~ 			if ($txt == '') {
//~ 				$txt = $URL;
//~ 			}
			if ($html_out) {
				fwrite($outfile, "<p class='link'>$txt<a href='$URL'>$URL</a></p>\n\n");
			} else {
				fwrite($outfile, "\t\t$str\n");
			}
	    } else {
			if ($html_out) {
				fwrite($outfile, "<p>$str</p>\n");
			} else {
				fwrite($outfile, "$str\n");
			}
	    }	    	    
 	}	
	if (!$html_out) {
		fwrite($outfile, "\n\n");
	}
	
}

################################################################################
### PRINT_HEADER ()
################################################################################
function PRINT_HEADER () {
################################################################################
### Generates introductory information at the beginning of the learning schedule.
### It has been generalized for any number of intro, howto, and links lines.
### The first line in each category is considered a heading.
### Any line which contains "https" is considered a link and will be indented.
###
### This routine could be simplified by moving the code for each header type
### into a subroutine and/or create a loop to process the three types.
### 
################################################################################

	global $html_out;
	
	debug_print("PRINT_HEADER Subroutine ()\n");
	
	if ($html_out) {
		INITIALIZE_HTML();
	}
	
	PRINT_INTRO_SECTION('intro', );
	
	PRINT_INTRO_SECTION('howto', );
	
	PRINT_INTRO_SECTION('links', );
	
	return;
}

################################################################################
### PROCESS_VIDEOS (starting lesson, ending lesson, index for last video)
################################################################################
function PROCESS_VIDEOS (
	$start_les, 	# Starting lesson in learning schedule
	$end_les,	# Ending   lesson in learning schedule
	$max_ix	# Index for last video
	) {
################################################################################
### Step through array of video hashes, build lesson hash for current lesson.
###	Set the $first and $last flags. Call PRINT_LESSON for each lesson.
###	Assign current lesson hash to previous lesson hash and repeat the process.
### 
### 	Return (index for starting lesson, index for ending lesson)
### 
################################################################################

	global $debug, $videos, $max_count, $first, $last, $lesson_count, $lesson_p, $incl_prev, $num;

	debug_print("PROCESS_VIDEOS Subroutine ($start_les, $end_les, $max_ix).\n");
	     
    $vid_ix   = 0;	# Index counter for Video Array    
    $start_ix = 0;   # Index of first video for first lesson
    $end_ix   = 999; # Index of last video for last lesson
    
	# Lesson arrays contain a list of every index of @videos for every video associated with
	# the previous or current lesson.
    $previous_lesson = array();   # Initialize previous lesson array.
	$current_lesson  = array();   # Initialize current  lesson array.
     

	$m     = $videos[$vid_ix][$num];	# Lesson Num
	
	# Skip over lessons before the starting lesson.
	# Need to generate previous lesson array if $incl_prev = 1;
	$iter = 0;
	while ($m < $start_les) { 
		debug_print("Skip... Index: $vid_ix Starting Lesson: $start_les, Lesson Number: $m\n");

		# Generate @previous_lesson if the previous lesson is supposed to be included.
		if (($m == $start_les - 1) && ($incl_prev)) {
			array_push($previous_lesson, $vid_ix);
		}
		$vid_ix++;
		$m = $videos[$vid_ix][$num];
		$iter = check_for_max_iterations($iter, __LINE__);
	}
		
	$start_ix = $vid_ix;   # Index of @videos for the start lesson to be processed. 
	
	# LLOOP: 
	$iter = 0;
	while ($vid_ix <= $max_ix) {		
		# Find all videos for lesson $m.
		$m_next = $videos[$vid_ix][$num];
		
		while ($m_next == $m) { 
			array_push($current_lesson, $vid_ix);
			$vid_ix++;
			if (array_key_exists($vid_ix, $videos)) {
				if (array_key_exists($num, $videos[$vid_ix])) {
					$m_next = $videos[$vid_ix][$num];
				} else {
					debug_print("***** $"."videos[$vid_ix] doesn't have a $num entry\n");
					break;
				}
			} else {
				debug_print("***** $"."videos doesn't have a $vid_ix entry\n");
				break;
			}
		}	

		$end_ix = $vid_ix;
		
		## Print the Current and Previous Lesson Index arrays
		if ($debug)	{
			debug_print("Lesson M Index Array for Lesson " . $videos[$current_lesson[0]][$num] . ": ( ");
			foreach ($current_lesson as $curr_lsn) {
				debug_print("$curr_lsn, ");
			}
			debug_print(" )\n");
			if ($previous_lesson && is_array($lesson_p) && array_key_exists(0, $lesson_p) && array_key_exists($lesson_p[0], $videos)) {  # If array is not empty
				debug_print("Lesson P Index Array for Lesson " . $videos[$lesson_p[0]][$num] . ": ( ");
				foreach ($previous_lesson as $prev_lsn) {
					debug_print("$prev_lsn, ");
				}
				debug_print(" )\n");
			} else {
				debug_print("No previous Array (" . join (", ", $previous_lesson) . ")\n");
			}
			debug_print("\n");
		}
		## End of Debug Print
	
		# Determine if $ is either first or last lesson 
		if ($m == $end_les) {
			$last = 1;
		} else {
			$last = 0;
		}
		if ($m == $start_les) {
			$first = 1;
		} else {
			$first = 0;
		}
		
		PRINT_LESSON($previous_lesson, $current_lesson);
		
		
		if ($last) { # End loop if we are on the last lesson.
			$end_ix = $vid_ix - 1; # Index of Video array of last video for ending lesson
			break; 
		} 
		
		$previous_lesson = $current_lesson;  # Current lesson array becomes previous lesson array.
		$current_lesson = array();   # Reset current lesson array.
		$m++;  # Go to the next lesson.
		
		$iter = check_for_max_iterations($iter, __LINE__);
		
	} # End LLOOP
     
	debug_print("End PROCESS_VIDEOS Subroutine S: $start_ix, E: $end_ix\n");
    return (array($start_ix, $end_ix));
}

################################################################################
### PRINT_LESSON (previous lesson array, current lesson array)
################################################################################
function PRINT_LESSON (
	$lesson_p, # List of indexes of @videos for the previous lesson
	$lesson_n # List of indexes of @videos for the current  lesson
	) {
################################################################################
### This subroutine is the brains of the schedule generation. It processes each
### type of video as needed. (See GET_VIDEOS subroutine for description of each type.)
###
###		Keys to @videos: $type, $num, $duration, $related, $quizzes, $verses, $url
### 		Time constants: $$verse_read_time, $alpha_write_time, $alpha_read_time, 
###							$quiz_take_time, $related_avg_time
###
###
### General Procedure:
### Types G, A: Listen 1x, Repeat 3x. 
### Types P, S, T: Listen 1x, Repeat 1x.
### Types X: Alert to existence.
### Types W, L: Listen & sing, 2x.
### Interleave listening and repeating lessons as well as previous with current.
### Review related videos
### Type R: End with Review Game and Quizzes of both current & previous lessons.
### 
### Here is the basic template for current lesson $n (previous lesson $p)
### 
### $n lesson listen		1
### $n lesson repeat		2
### $p story  listen			1		
### $p bonus  repeat				2
### $n alphabet write
### 
### $p lesson repeat	4	
### $p story  repeat			2
### $n bonus  listen					1
### $n lesson repeat		3
### $n script read (alphabet lesson)
### $n verse  read
### 
### $p, $n related Videos 
### $p, $n extra videos (X)
### $p, $n extra alphabet videos (L)
### 
### $p, $n review game (R)
### $p.q   quizzes
### $n.q   quizzes
### 
################################################################################

	global $debug, $videos, $num, $nhash, $phash, $first, $last, $lesson_count, $command, $incl_prev,
		$type, $num, $title, $duration, $related, $quizzes, $verses, $url, $max_unit, $mode, $unit_mode, $xmode,
		$verse_read_time, $alpha_write_time, $alpha_read_time, $quiz_take_time, $related_avg_time, $time_count, $outfile,
		$tot_time, $tot_days, $quiz_cnt, $time_count, $total_lessons, $min_secs, $max_secs, $sfx, $html_out, $display_links;

	# Initialize Variables
	
	# Hashes or arrays. Hash keys are each video type (A, G, P, etc.). 
	# The array contains all indexes of @videos for that type for the previous/current lesson
	$phash = array();
	$nhash = array();
	
	# Arrays that hold the indexes for all main videos (Types 'G' or 'A')
	$main_lessons_p = array();
	$main_lessons_n = array();
	
	# Arrays that hold the indexes for all story videos (Types 'S' or 'T')
	$main_stories_p = array();
	$main_stories_n = array();
	
	$num_p;	# Lesson Number of previous lesson
	$num_n = 0;	# Lesson Number of current  lesson
	
	## Print the Current and Previous Lesson Index arrays
	if ($debug)	{
		debug_print("***** PRINT_LESSON Subroutine *****\n"); # (" . print_r($lesson_p, true) . ", " . print_r($lesson_n, true) . ")\n");
		
		debug_print("Lesson N Index Array for Lesson " . $videos[$lesson_n[0]][$num] . ": ( ");
		foreach ($lesson_n as $lsn_n) {debug_print("$lsn_n, ");} 
		debug_print(" )\n");
		if (is_array($lesson_p) && count($lesson_p) > 0) {  # If array is not empty
			debug_print("Lesson P Index Array for Lesson " . $videos[$lesson_p[0]][$num] . ": ( ");
			foreach ($lesson_p as $lsn_p) {
				debug_print("$lsn_p, ");
			}
			debug_print(" )\n");
		} else {
			debug_print("No previous Array (" . join (", ", $lesson_p) . ")\n");
		}
		debug_print("\n");
	}
	## End of Debug Section
	
	debug_print("Separate P videos by type\n");
	debug_print("Lessons_p " . join(',', $lesson_p) . "\n");
	foreach ($lesson_p as $lsn_p) {  # Separate videos by type
		debug_print("pushing $lsn_p to $" . "phash[$"."videos[$lsn_p][$type]]\n");
		if (!array_key_exists($videos[$lsn_p][$type], $phash)) {
			$phash[$videos[$lsn_p][$type]] = array($lsn_p);
		} else {
			debug_print(print_r($phash[$videos[$lsn_p][$type]], true) . "\n");
			array_splice($phash[$videos[$lsn_p][$type]], count($phash[$videos[$lsn_p][$type]]), 0, $lsn_p);
		}
	}
	
	debug_print("Separate N videos by type\n");
	debug_print("Lessons_n " . join(',', $lesson_n) . "\n");
	foreach ($lesson_n as $lsn_n) {  # Separate videos by type
		debug_print("Lesson $lsn_n\n");
		if (!array_key_exists($videos[$lsn_n][$type], $nhash)) {
			$nhash[$videos[$lsn_n][$type]] = array($lsn_n);
		} else {
			debug_print("$" . "videos[$lsn_n][$type] = " . $videos[$lsn_n][$type] . "\n");
			array_splice($nhash[$videos[$lsn_n][$type]], count($nhash[$videos[$lsn_n][$type]]), 0, $lsn_n);
		}
	}

	## Print the Current and Previous Lesson Hashes
	if ($debug) {
		foreach ($nhash as $key => $value) {
			debug_print("nhash: -$key-" . join(", ", $value) . "\n");
		}

		foreach ($phash as $key => $value) {
			debug_print("PHASH: -$key-" . join(", ", $value) . "\n");
		}
	}
	## End of Debug Section

	# Create array of all Grammar and Alphabet lessons
	
	if (array_key_exists('G', $nhash)) {
		debug_print("push $" . "nhash['G']=" . print_r($nhash['G'], true) . " to $" . "main_lessons_n\n");
//~ 		array_push($main_lessons_n, $nhash['G']);
		array_splice($main_lessons_n, count($main_lessons_n), 0, $nhash['G']);
		debug_print("$" . "main_lessons_n=" . print_r($main_lessons_n, true) . "\n");
	}
	if (array_key_exists('A', $nhash)) {
		debug_print("push $" . "nhash['A'] to $" . "main_lessons_n\n");
		array_splice($main_lessons_n, count($main_lessons_n), 0, $nhash['A']);
		debug_print("$" . "main_lessons_n=" . print_r($main_lessons_n, true) . "\n");
	}
	if (array_key_exists('G', $phash)) {
		debug_print("push $" . "phash['G'] to $" . "main_lessons_p\n");
		array_splice($main_lessons_p, count($main_lessons_p), 0, $phash['G']);
		debug_print("$" . "main_lessons_p=" . print_r($main_lessons_p, true) . "\n");
	}
	if (array_key_exists('A', $phash)) {
		debug_print("push $" . "nhash['A'] to $" . "main_lessons_p\n");
		array_splice($main_lessons_p, count($main_lessons_p), 0, $phash['A']);
		debug_print("$" . "main_lessons_p=" . print_r($main_lessons_p, true) . "\n");
	}

	if (array_key_exists('S', $nhash)) {
		debug_print("push $" . "nhash['S'] to $" . "main_stories_n\n");
		array_splice($main_stories_n, count($main_stories_n), 0, $nhash['S']);
		debug_print("$" . "main_stories_n=" . print_r($main_stories_n, true) . "\n");
	}
	if (array_key_exists('T', $nhash)) {
		debug_print("push $" . "nhash['T'] to $" . "main_stories_n\n");
		array_splice($main_stories_n, count($main_stories_n), 0, $nhash['T']);
		debug_print("$" . "main_stories_n=" . print_r($main_stories_n, true) . "\n");
	}
	if (array_key_exists('S', $phash)) {
		debug_print("push $" . "phash['S'] to $" . "main_stories_p\n");
		array_splice($main_stories_p, count($main_stories_p), 0, $phash['S']);
		debug_print("$" . "main_stories_p=" . print_r($main_stories_p, true) . "\n");
	}
	if (array_key_exists('T', $phash)) {
		debug_print("push $" . "phash['T'] to $" . "main_stories_p\n");
		array_splice($main_stories_p, count($main_stories_p), 0, $phash['T']);
		debug_print("$" . "main_stories_p=" . print_r($main_stories_p, true) . "\n");
	}

	# Assign lesson numbers for current and previous lessons
	debug_print("Assign lesson number for current and previous lessons\n");
//~ 	debug_print("$" . "main_lessons_n=" . print_r($main_lessons_n, true) . "\n");
	if (array_key_exists(0, $main_lessons_n)) {
		debug_print("$" . "main_lessons_n[0]=" . print_r($main_lessons_n[0], true) . "\n");
		$num_n = $videos[$main_lessons_n[0]][$num];
		debug_print("$" . "num_n = $num_n\n");
	}
	
	debug_print("$" . "main_lessons_p=" . print_r($main_lessons_p, true) . "\n");
	if (array_key_exists(0, $main_lessons_p)) {
		debug_print("$" . "main_lessons_p[0]=" . print_r($main_lessons_p[0], true) . "\n");
		$num_p = $videos[$main_lessons_p[0]][$num];
		debug_print("$" . "num_p = $num_p\n");
	} else {
		$num_p = 0;
	}

	$lesson_count++;
	 
	### Current Lesson (Grammar or Alphabet): Listen, Repeat
	# MAIN_N1:
	$iter = 0;
	foreach ($main_lessons_n as $m_lsns) {
		debug_print("MAIN_N1 -$m_lsns-\n");
		$line = $videos[$m_lsns][$num] . " (" . $videos[$m_lsns][$duration] . ")";
		INCREMENT_COUNTERS (1, 1, $videos[$m_lsns][$duration], 1);
		# Force new week after max units is completed and you are on the first lesson of the new unit. 
		# i.e. Current lesson modulo (2 * number of units) = 1 (first lesson past $max_unit boundary)
		# This accounts for the starting lesson not being on a full week boundary
		debug_print("$"."max_unit=" . $max_unit . "\n");
		$force_wk = ($mode == $unit_mode) && ($num_n % (2 * $max_unit) == 1) && !$first;
		$force_dy = false; # Took out Force New Day.
		PRINT_LINE($line, 'watch_listen', 1, $videos[$m_lsns][$title], $videos[$m_lsns][$url], false, false, $force_dy, $force_wk, __LINE__);
		# Force a new day after first video if in UnitX mode unless it is the first day.
		INCREMENT_COUNTERS (1, 1, $videos[$m_lsns][$duration], 1);
		$force_dy = ($xmode && !$first && ($videos[$m_lsns][$num] %2 == 1));		
		PRINT_LINE($line, 'watch_repeat', 1, $videos[$m_lsns][$title], $videos[$m_lsns][$url], false, false, $force_dy, false, __LINE__);
		$iter = check_for_max_iterations($iter, __LINE__);
	}		
	
    # Previous Lesson Story: Listen
	# STORYP1: 
	$iter = 0;
	foreach ($main_stories_p as $m_stories_p) {
		debug_print("STORYP1 -$m_stories_p-\n");
		INCREMENT_COUNTERS (0, 1, $videos[$m_stories_p][$duration], 1);
		if ($html_out) {
			$line = "<a href='" . $videos[$m_stories_p][$url] . "'>" . $videos[$m_stories_p][$title] . "</a> (" . $videos[$m_stories_p][$duration] . ")";
			PRINT_LINE($line, 'watch_story_listen', 1, '', '', false, false, false, false, __LINE__);
		} else {
			$line = $videos[$m_stories_p][$title] . " (" . $videos[$m_stories_p][$duration] . ")";
			PRINT_LINE($line, 'watch_story_listen', 1, $videos[$m_stories_p][$url], $videos[$m_stories_p][$url], false, false, false, false, __LINE__);
		}
		$iter = check_for_max_iterations($iter, __LINE__);
	}
	
	# Previous Lesson Bonus: Repeat	
	# BONUSP: 
	$iter = 0;
	
	if (array_key_exists('P', $phash)) {
		foreach ($phash['P'] as $p_hsh) {
			debug_print("BONUSP -$p_hsh-\n");
			$line = $videos[$p_hsh][$num] . $videos[$p_hsh][$sfx] . " (" . $videos[$p_hsh][$duration] . ")";
			INCREMENT_COUNTERS (0, 1, $videos[$p_hsh][$duration], 1);
			PRINT_LINE($line, 'watch_repeat',  1, $videos[$p_hsh][$title], $videos[$p_hsh][$url], false, false, false, false, __LINE__);
			$iter = check_for_max_iterations($iter, __LINE__);
		}
	}
				
	# Current Lesson (Alphabet): Write
 	# ALPHA_WR: 
	$iter = 0;
	if (array_key_exists('A', $nhash)) {
		foreach ($nhash['A'] as $n_hsh) {
			debug_print("ALPHA_WR -$n_hsh-\n");
			INCREMENT_COUNTERS (0, 0, $alpha_write_time, 1);
			PRINT_LINE($videos[$n_hsh][$num],  'write10', 1, '', '', false, false, false, false, __LINE__);
			$iter = check_for_max_iterations($iter, __LINE__);
		}
	}
				
	### Previous Lesson (Grammar or Alphabet): Repeat
	# MAIN_P:
	$iter = 0;
	foreach ($main_lessons_p as $m_lsns_p) {
		debug_print("MAIN_P -$m_lsns_p-\n");
		$line = $videos[$m_lsns_p][$num] . " (" . $videos[$m_lsns_p][$duration] . ")";
		INCREMENT_COUNTERS (1, 1, $videos[$m_lsns_p][$duration], 1);
		PRINT_LINE($line, 'watch_repeat',	1, $videos[$m_lsns_p][$title], $videos[$m_lsns_p][$url], false, false, false, false, __LINE__);
		$iter = check_for_max_iterations($iter, __LINE__);
	}
				
	# Current Lesson Bonus: Listen	
	# BONUSN: 
	$iter = 0;
	if (array_key_exists('P', $nhash)) {
		foreach ($nhash['P'] as $n_hsh_p) {
			debug_print("BONUSN -$n_hsh_p-\n");
			$line = $videos[$n_hsh_p][$num] . $videos[$n_hsh_p][$sfx] . " (" . $videos[$n_hsh_p][$duration] . ")";
			INCREMENT_COUNTERS (0, 1, $videos[$n_hsh_p][$duration], 1);
			PRINT_LINE($line, 'watch_listen', 1, $videos[$n_hsh_p][$title], $videos[$n_hsh_p][$url], false, false, false, false, __LINE__);
			$iter = check_for_max_iterations($iter, __LINE__);
		}
	}
				
	### Current Lesson ((Grammar or Alphabet)): Repeat
	# MAIN_N2: 
	$iter = 0;
	foreach ($main_lessons_n as $m_lsns_n) {
		debug_print("MAIN_N2 -$m_lsns_n-\n");
		$line = $videos[$m_lsns_n][$num] . " (" . $videos[$m_lsns_n][$duration] . ")";
		# Special case for the first lesson. Because there is no previous lesson to repeat
		# you are left with a single video for a day's listening. Combine it with the next lesson.
		INCREMENT_COUNTERS (1, 1, $videos[$m_lsns_n][$duration], 1);
		$combine = !$incl_prev && $first && !(($mode == $unit_mode) && $xmode);
#		$force_dy = (($mode == $unit_mode) && $first && ($videos[$m_lsns_n][$num] %2 == 1));		
#		$force_dy = (($mode == $unit_mode) && $xmode && $no_prev && ($videos[$m_lsns_n][$num] %2 == 1));
		PRINT_LINE($line, 'watch_repeat', 1, $videos[$m_lsns_n][$title], $videos[$m_lsns_n][$url], $combine, false, false, false, __LINE__);
		$iter = check_for_max_iterations($iter, __LINE__);
	}
	
    # Previous Lesson Story: Repeat
	# STORYP2: 
	$iter = 0;
	foreach ($main_stories_p as $m_stories_p) {
		debug_print("STORYP2 -$m_stories_p-\n");
		INCREMENT_COUNTERS (0, 1, $videos[$m_stories_p][$duration], 1);
		if ($html_out) {
			$line = "<a href='" . $videos[$m_stories_p][$url] . "'>". $videos[$m_stories_p][$title] . "</a> (" . $videos[$m_stories_p][$duration] . ")";
			PRINT_LINE($line, 'watch_story_repeat',	1, '', '', false, false, false, false, __LINE__);
		} else {
			$line = $videos[$m_stories_p][$title] . " (" . $videos[$m_stories_p][$duration] . ")";
			PRINT_LINE($line, 'watch_story_repeat',	1, $videos[$m_stories_p][$title], $videos[$m_stories_p][$url], false, false, false, false, __LINE__);
		}
		$iter = check_for_max_iterations($iter, __LINE__);
	}
	
	# Current Lesson (Alphabet): Read Script
 	# ALPHA_RD: 
	$iter = 0;
	if (array_key_exists('A', $nhash)) {
		foreach ($nhash['A'] as $nhsh_a) {
			debug_print("ALPHA_RD -$nhsh_a-\n");
			INCREMENT_COUNTERS (0, 0, $alpha_read_time, 2);
			PRINT_LINE($videos[$nhsh_a][$num],   'read_script', 	2, $videos[$nhsh_a][$title], $videos[$nhsh_a][$url], false, false, false, false, __LINE__);
			$iter = check_for_max_iterations($iter, __LINE__);
		}
	}
		
	# Current Lesson (Grammar only): Verse Read
	# VERSE: 
	if (array_key_exists('G', $nhash)) {
		$iter = 0;
		foreach ($nhash['G'] as $nhsh_g) {
			
			if ($videos[$nhsh_g][$verses] != "") {
				$line = B_TRANSLATE($videos[$nhsh_g][$verses]);
				if ($line) {
					debug_print("VERSE -$nhsh_g- .." . $videos[$nhsh_g][$verses] . "..\n");
					$str = $line;	         # Make a copy 
					$num_verses = substr_count($str, ",") + 1;  # Number of verses = number of commas + 1.
					debug_print("$"."num_verses=$num_verses in $str\n");
					INCREMENT_COUNTERS (0, 0, $verse_read_time, 2 * $num_verses);
					PRINT_LINE($line, 'read_verse', 2, '', '', false, false, false, false, __LINE__);
				}
			}
			$iter = check_for_max_iterations($iter, __LINE__);
		}
	}
	
    # Related Review Videos only after even numbered lessons or the last lesson
	debug_print("Related Review Videos\n");
    # RELATED: 
	if (($num_n % 2 == 0) || $last) {
    	
    	# Previous lesson (Grammar or Alphabet): Related Videos to Review
    	if (($num_n % 2 == 0) && (!$last)) {
			PROCESS_RELATED($num_p, $main_lessons_p);
		}
		
		# Current Lesson (Grammar or Alphabet): Related Videos to Review
		PROCESS_RELATED($num_n, $main_lessons_n);
	}
		
	# Extra Alphabet Videos only after even numbered lessons or the last lesson
	debug_print("Extra Alphabet Videos\n");
    # XALPHA_N: 
	if (($num_n % 2 == 0) || $last) {
		# Previous Lesson: Extra Alphabet Videos	
		if (!$last) {
			if (array_key_exists('L', $phash)) {
				$iter = 0;
				foreach ($phash['L'] as $phsh_l) {
	#				debug_print("XALPHA_N -$phsh_l-\n");
					$line = "$num_p, " . $videos[$phsh_l][$title] . " (" . $videos[$phsh_l][$duration] . "),";
					if ($line) {
						INCREMENT_COUNTERS (0, 1, $videos[$phsh_l][$duration], 2);
						PRINT_LINE($line,   'alpha_extra', 	2, $videos[$phsh_l][$title], $videos[$phsh_l][$url], false, false, false, false, __LINE__);
					}
					$iter = check_for_max_iterations($iter, __LINE__);
				}
			}
		}	
		# Current Lesson: Extra Alphabet Videos	
		if (array_key_exists('L', $nhash)) {
			$iter = 0;
			foreach ($nhash['L'] as $nhsh_l) {
				$line = "$num_n, " . $videos[$nhsh_l][$title] . " (" . $videos[$nhsh_l][$duration] . "),";
				if ($line) {
					INCREMENT_COUNTERS (0, 1, $videos[$nhsh_l][$duration], 2);
					PRINT_LINE($line,   'alpha_extra', 	2, $videos[$nhsh_l][$title], $videos[$nhsh_l][$url], false, false, false, false, __LINE__);
				}
				$iter = check_for_max_iterations($iter, __LINE__);
			}
		}
	
	}

	# Extra Videos only after even numbered lessons or the last lesson.
	# This are condensed versions of stories introduced in lesson videos. 
	# For optional review. Do not count as a video.
	debug_print("Extra Videos\n");
	
    # EXTRA_N: 
	if (($num_n % 2 == 0) || $last) {
		# Previous Lesson: Extra Videos	
		if (!$last) {
			if (array_key_exists('X', $phash)) {
				$iter = 0;
				foreach ($phash['X'] as $phsh_x) {
	#				debug_print("EXTRA_N -$phsh_x-\n");
					$line = "$num_p, " . $videos[$phsh_x][$title] . " (" . $videos[$phsh_x][$duration] . "),";
					if ($line) {
						INCREMENT_COUNTERS (0, 0, 0, 1);
						if ($html_out) {
							$line = "$num_p, <a href='" . $videos[$phsh_x][$url] . "'>" . $videos[$phsh_x][$title] . "</a> (" . $videos[$phsh_x][$duration] . "),";
							PRINT_LINE($line, 'extra', 0, '', '', false, false, false, false, __LINE__);
						} else {
							PRINT_LINE($line, 'extra', 0, $videos[$phsh_x][$title], $videos[$phsh_x][$url], false, false, false, false, __LINE__);
						}
					}
					$iter = check_for_max_iterations($iter, __LINE__);
				}
			}
		}
		# Current Lesson: Extra Videos	
		if (array_key_exists('X', $nhash)) {
			$iter = 0;
			foreach ($nhash['X'] as $nhsh_x) {
				$line = "$num_n, " . $videos[$nhsh_x][$title] . " (" . $videos[$nhsh_x][$duration] . "),";
				if ($line) {
					INCREMENT_COUNTERS (0, 0, 0, 1);
					if ($html_out) {
						$line = "$num_n, <a href='" . $videos[$nhsh_x][$url] . "'>" . $videos[$nhsh_x][$title] . "</a> (" . $videos[$nhsh_x][$duration] . "),";
						PRINT_LINE($line, 'extra', 0, '', '', false, false, false, false, __LINE__);
					} else {
						PRINT_LINE($line, 'extra', 0, $videos[$nhsh_x][$title], $videos[$nhsh_x][$url], false, false, false, false, __LINE__);
					}
				}
				$iter = check_for_max_iterations($iter, __LINE__);
			}
		}
	
	}

    # Current Lesson: Worship Videos	
	debug_print("Worship Videos\n");
	# SONG: 
	if (array_key_exists('W', $nhash)) {
		$iter = 0;
		foreach ($nhash['W'] as $nhsh_w) {
	#		debug_print("SONG -$nhsh_w-\n");
			INCREMENT_COUNTERS (0, 1, $videos[$nhsh_w][$duration], 1);
			if ($html_out) {
				$line = "<a href='" . $videos[$nhsh_w][$url] . "'>" . $videos[$nhsh_w][$title] . "</a> (" . $videos[$nhsh_w][$duration] . ")";
				PRINT_LINE($line, 'worship', 1, '', '', false, false, false, false, __LINE__);
			} else {
				$line = $videos[$nhsh_w][$title] . " (" . $videos[$nhsh_w][$duration] . ")";
				PRINT_LINE($line, 'worship', 1, $videos[$nhsh_w][$title], $videos[$nhsh_w][$url], false, false, false, false, __LINE__);
			}
			$iter = check_for_max_iterations($iter, __LINE__);
		}
	}
    

	#### Current Lesson: Review Game [Assumes covers 2 lessons, current & (current - 1)]
	debug_print("Review Game\n");
	
	# REVIEW: 
	if (array_key_exists('R', $nhash)) {
		$iter = 0;
		foreach ($nhash['R'] as $nhsh_r) {
	#		debug_print("REVIEW -$nhsh_r-\n");
			INCREMENT_COUNTERS (0, 1, $videos[$nhsh_r][$duration], 1);
			$prev = $videos[$nhsh_r][$num] - 1;
			if ($html_out) {
				$review_n = "<a href='" . $videos[$nhsh_r][$url] . "'>" . $videos[$nhsh_r][$title] . "</a> (" . $videos[$nhsh_r][$duration] . ")";
				PRINT_LINE($review_n, 'review_game', 1, '', '', false, false, false, false, __LINE__);
			} else {
				$review_n = $videos[$nhsh_r][$title] . " (" . $videos[$nhsh_r][$duration] . ")";
				PRINT_LINE($review_n, 'review_game', 1, $videos[$nhsh_r][$title], $videos[$nhsh_r][$url], false, false, false, false, __LINE__);
			}
			$iter = check_for_max_iterations($iter, __LINE__);
		}
	}
	

	# Current & Previous Lesson (Grammar or Alphabet): Quizzes
	debug_print("Current & Previous Lesson Quizzes\n");
	$quiz_cnt_p = 0;
	# QUIZ_P: 
	$iter = 0;
	foreach ($main_lessons_p as $m_lsns_p) {
		$quiz_cnt_p = $quiz_cnt + $videos[$m_lsns_p][$quizzes];
		$iter = check_for_max_iterations($iter, __LINE__);
	}
	
	$quiz_cnt_n = 0;
	# QUIZ_N: 
	$iter = 0;
	foreach ($main_lessons_n as $m_lsns_n) {
		$quiz_cnt_n = $quiz_cnt + $videos[$m_lsns_n][$quizzes];
		$iter = check_for_max_iterations($iter, __LINE__);
	}	


	# PRINT_QUIZ prints quizzes after even numbered lessons and after last lesson.
	PRINT_QUIZ ($num_n, $quiz_cnt_n, $num_p, $quiz_cnt_p);
    		   
	# If there more lessons to come (HOW DO I KNOW THIS?), say so.
	if ($last) {
		PRINT_TOTAL_TIME($time_count);
		fwrite($outfile, "\n");
	}

	if ($last && ($videos[$lesson_n[0]][$num] < $total_lessons)) {
		PRINT_LINE("", 'more', 99, '', '', 0, 1, false, false, false, false, __LINE__);
		fwrite($outfile, "\n");
	} 
	
	if ($last) {
		PRINT_LINE(MAKE_TIME($max_secs, false), 'longest', 99, '', '', false, true, false, false, __LINE__);
		PRINT_LINE(MAKE_TIME($min_secs, false), 'shortest', 99, '', '', false, true, false, false, __LINE__);
		PRINT_LINE(MAKE_TIME((int)($tot_time/$tot_days), false), 'average', 99, '', '', false, true, false, false, __LINE__);
		PRINT_LINE($tot_days, 'total_days', 99, '', '', false, true, false, false, __LINE__);
		PRINT_LINE(MAKE_TIME($tot_time, true), 'total_time', 99, '', '', false, true, false, false, __LINE__);
		
		fwrite($outfile, "\n");
		fwrite($outfile, $command);  # Print options used to generate file and time stamp.
	}
	
	# Reset $first flag
	$first = 0;
	
	debug_print("***** End of PRINT_LESSON *****\n");
	
return;

}

################################################################################
### PRINT_LINE (text to substitute, phrase key, number of checkboxes, text to append,
###             combine day, block new day, force new day, force new week, caller line number)
################################################################################
function PRINT_LINE (
	$n,  # Text to substitute
	$keyx,  # Phrase to use
	$boxnum,  # Number of checkboxes
	$append_text, # Text to append at the end
	$append_link, # actual link as part of append
	$combine_day,  # Special case in Main Mode. Combine current line with next
							  # on the first lesson when previous is not include
	$block_day,  # Block a new day, used when printing Quiz lines.
	$force_day,  # Force a new day (Used in Unit Mode before Review Game/Quizzes)
	$force_week,  # Force a new week (Used in Unit Modes when starting lesson is even or if max number of lessons is reached)
	$caller_line_number  # Line number of the caller for debugging
	) {
################################################################################
### Prints a line in the output Schedule file for a video or activity. 
### Determines Week and Day boundaries.
### 
###  Modes: M $max_main main videos per day plus any additional material
### 		V $max_vid  videos per day, regardless of type
### 		L $max_unit lessons per week
### 		T $max_time duration of videos per day, regardless of type
### 
################################################################################

	global $phrases, $max_count, $max_main, $vid_count, $max_vid, $lesson_count, $main_count,
		$time_count, $max_time, $box, $sub_char, $mode,
		$main_mode, $video_mode, $unit_mode, $time_mode, $outfile, $bullet, $html_out, $display_links;

	debug_print("PRINT_LINE Subroutine (caller line number=$caller_line_number)\n"); # $n, $keyx, $boxnum, $append, $combine_day, $block_day, $force_day, $force_week)\n";
	
	# Initialize Variables
//~ 	if ($boxnum <= 0) {
		$boxnum = 1;  # just always put out one box
//~ 	}
	$line  = $phrases[$keyx];
	$boxes = "";		
	$i     = 0;
		
#		fwrite($outfile, "Block Day: $block_day, Mode: $mode, Main Count: $main_count, Force Week: $force_week, Force Day: $force_day\n");
		
	# Based on Mode, call PRINT_TIME.
	if (!$block_day) {
		debug_print("Not block_day\n");
		debug_print("Mode=$mode\n");
//~ 		for ($mode) {
			if (strpos($mode, $main_mode) !== false) {
				if (($main_count > $max_main) || $force_week || $force_day)  {
					PRINT_TIME(false, $force_week);
					if ($combine_day) { # Special case for second day, first week, no previous.
						$main_count = 0;	
					} elseif ($force_day){  # Day forced before Review/Quiz.
						$main_count = $max_main;  # Trick it into starting a new day after Review/Quiz.
					} else {
						$main_count = 1;	# Accounts for Related Videos.
					}
					if ($force_week) {
						$lesson_count = 1;
					}
				}
				

			} elseif (strpos($mode, $video_mode) !== false) {
				if ($vid_count > $max_vid) {
					PRINT_TIME(false, 0);
					$vid_count = $vid_count - $max_vid; # Accounts for Related Videos.
				}	
						
			} elseif (strpos($mode, $unit_mode) !== false) { # Unit Mode works like Main mode, except Week ends after quizzes.
				if ((($main_count > $max_main) || $force_week || $force_day) && !$block_day)  {
					PRINT_TIME(false, $force_week);
					if ($combine_day) { # Special case for first lesson, no previous.
						$main_count = 0;	
					} else {
						$main_count = 1;
					}
					if ($force_week) {
						$lesson_count = 1;
					}
				}
							
			} elseif (strpos($mode, $time_mode) !== false) {
			#	debug_print(OUTFILE "Time Mode: Time Count = $time_count, Max Time = $max_time\n");
				debug_print("Time Mode: Time Count = $time_count, Max Time = $max_time\n");
				# Don't print time if printing quizzes. 
				if (($time_count > $max_time) && !$block_day) {
					PRINT_TIME(false, 0);
				}
			
			} else {
			   debug_print("Unknown Schedule Mode $mode\n");
			   exit;			
			}
//~ 		}
	}
		
	if ($boxnum == 99) {
		$boxes = "";
	} elseif ($boxnum == 0) {
		$boxes = "$bullet" . "\t";
	} else {
		while ($i < $boxnum) {
			$boxes = $boxes . "$box ";
			$i++;
		}
		$boxes = $boxes . "\t";
	}
	
	# Make the substitution
//~ 	debug_print("***** Replacing $sub_char with $n in $line\n");
	if ($html_out) {
		if ($keyx == 'quiz_yes') { # handle quizzes differently make the quiz number the link
			$line = preg_replace('/' . $sub_char . '/', "<a href='$append_link'>$n</a>", $line);
		} else {
			$line = preg_replace('/' . $sub_char . '/', $n, $line);
		}
	} else {
		$line = preg_replace('/' . $sub_char . '/', $n, $line);
	}
	
	debug_print( "Key: $keyx, MD $mode, CT $main_count, MX $max_main<br>\n$line\n");
	
	if ($append_text == '') {
		$append_text = $append_link;
	}
	$time_str = MAKE_TIME($time_count, false);
	if ($html_out) {
		if ($keyx == 'quiz_yes') { # handle quizzes differently
			$append = "";
		} else {
			if ($append_text != '' || $append_link != '') {
				if ($display_links) {
					$append = " ($append_text:<br>\n\t<a href='$append_link'>$append_link</a>)";
				} else {
					$append = " (<a href='$append_link'>$append_text</a>)";
				}
			} else { # nothing to append
				$append = '';
			}
		}
		fwrite($outfile, "<p class='$keyx'>$boxes$line$append</p>");
		debug_print("PRINT_LINE outputting: <p class='$keyx'>$boxes$line$append</p>");
	} else { // text out
		fwrite($outfile, "$boxes$line$append_text");
	}
	debug_print("PRINT_LINE $"."boxes=$boxes, $"."line=$line, $"."append=$append_text, $"."main_count=$main_count, $"."vid_count=$vid_count, $"."lesson_count=$lesson_count, $"."time_str=$time_str\n");
	
	fwrite($outfile, "\n");
	
	return;

}

################################################################################
### PRINT_TIME (Skip printing total time flag, force new week flag)
################################################################################
function PRINT_TIME (
	$skip_total,	# Don't PRINT_TOTAL_TIME at the beginning of the schedule.
	$force_new_week # Force a new week even if $mode does not require it
	) {
################################################################################
###	Prints the new week or day headings as well as calling PRINT_TOTAL_TIME to
### print the total time for the day's assignment.
###
################################################################################

	global $time_count, $time_adjust, $day_count, $week_count, $phrases, $outfile, $max_day, $html_out;
	
	debug_print("PRINT_TIME Subroutine($skip_total, $force_new_week)\n");
   	
   	# Print Total count for previous day
	if (!$skip_total) {
		PRINT_TOTAL_TIME($time_count - $time_adjust);
	}
//~ 	debug_print("Type of $" . "time_count=" . gettype($time_count) . "\n");
   	$time_count = $time_adjust;
//~ 	debug_print("Type of $" . "time_count=" . gettype($time_count) . "\n");

   	
   	if (($day_count == 1) || $force_new_week) {
   		# Time for a new week. Previous Week is full.
		$day_count = 1;
		debug_print("Reset Day count: $day_count, $week_count<br>\n");
		if ($html_out) {
			fwrite($outfile, "\n\n<H2 class='week'>" . $phrases['week'] . " $week_count</H2>\n");
			fwrite($outfile, "\n<H3 class='day'>" . $phrases['day'] . " $day_count</H3>\n");
		} else {
			fwrite($outfile, "\n\n*** " . $phrases['week'] . " $week_count ***\n");
			fwrite($outfile, "\n" . $phrases['day'] . " $day_count\n");
		}
		$week_count++;		
   	} else {
   		# Start a new day.
		if ($html_out) {
			fwrite($outfile, "\n<H3 class='day'>" . $phrases['day'] . " $day_count</H3>\n");
		} else {
			fwrite($outfile, "\n" . $phrases['day'] . " $day_count\n");
		}
   	}
    
	if ($day_count < $max_day){
		$day_count++;
	} else {
		$day_count = 1;
	}

    return;

}

################################################################################
### PRINT_TOTAL_TIME (time in seconds)
################################################################################
function PRINT_TOTAL_TIME ($t) {
################################################################################
### Prints the approximate total time it will take to complete a day's videos
### and activities.
### 
################################################################################

	global $phrases, $sub_char, $max_secs, $min_secs, $tot_time, $tot_days, $outfile, $html_out;

	$n = MAKE_TIME($t, false);
	
	debug_print("PRINT_TOTAL_TIME Subroutine ($n)\n");

	## Update constants for keeping track of time numbers for bookkeeping ##
	$max_secs = max($max_secs, $t);
	$min_secs = min($min_secs, $t);
	$tot_time += $t;
	$tot_days ++;

	$line = $phrases['time'];
	$line = preg_replace('/' . $sub_char . '/', $n, $line);
	
	if ($html_out) {
		fwrite($outfile, "<p class='total_time'>$line</p>\n");
	} else {
		fwrite($outfile, "\t$line\n");
	}
	
	return;
   	
}

################################################################################
### PRINTQUIZ (Current Lesson num, current quiz count, 
###					Prev lesson num, Prev quiz count)
################################################################################
function PRINT_QUIZ (
	$n,  # Current Lesson Number
	$nqz,  # Current Lesson number of quizzes
	$p,  # Previous Lesson Number
	$pqz  # Previous Lesson number of quizzes
	) {
################################################################################
###  Prints one line in the learning schedule per quiz for the previous and current
###  lessons when the current lesson is an even number. 
###		If the first lesson is even, then print the first lesson quizzes. 
###		If the last  lesson is odd,  then print the last  lesson quizzes. 
###
################################################################################
	
	global $first, $last, $outfile, $skip_quiz, $incl_prev;
	
	if ($skip_quiz) {
		return;
	}
	
	debug_print("PRINT_QUIZ Subroutine ($n, $nqz, $p, $pqz)\n");
	
	fwrite($outfile, "\n");
	if (0 == $n % 2) {  #Even numbered lesson
		if (!$first || ($first && $incl_prev)) {
			QUIZ_LOOP($p, $pqz);
		}
		QUIZ_LOOP($n, $nqz);
		fwrite($outfile, "\n");
		
	}  else {  # Odd numbered lesson	
	 	if ($last) {
			QUIZ_LOOP($n, $nqz);	 	
	 	}
	}
	
	return;
		
}

################################################################################
### QUIZ_LOOP (lesson_number, quiz_count)
################################################################################
function QUIZ_LOOP (
	$lnum,  # Lesson Nunber
	$qcnt  # Number of quizzes for lesson
	) {
################################################################################
### Loop to print the correct number of quiz lines.
################################################################################
	
	global $mode, $time_mode, $quiz_take_time, $display_links, $videos, $url, $reverse_index_quiz;
	
	# Keep all quizzes on the same day unless you are in time_mode.
	$block_day = $mode != $time_mode;
	
	debug_print("QUIZ_LOOP Subroutine ($lnum, $qcnt)\n");
	
#		if ($qcnt == 0) { # No quizzes exist for this lesson (yet)
#			PRINT_LINE($lnum, 'quiz_no', 0, '', '', false, false, false, false, __LINE__);
#		} else {
		if ($qcnt > 0) { # Quizzes exist for this lesson (yet)
			$q = 1;
			# QZ: 
			while ($q <= $qcnt){
				INCREMENT_COUNTERS (0, 0, $quiz_take_time, 1);
				PRINT_LINE("$lnum.$q", 'quiz_yes', 1, $videos[$reverse_index_quiz["$lnum.$q"]][$url], $videos[$reverse_index_quiz["$lnum.$q"]][$url], false, $block_day, false, false, __LINE__);
				$q++;
				continue;
			}		
		}
}

################################################################################
### PROCESS_RELATED (lesson_number, Array_of_videos)
################################################################################
function PROCESS_RELATED (
	$num,
	$main_lessons
	) {
	
	global $mode, $unit_mode, $videos, $related_array, $related, $related_avg_time, $display_links, $title, $url, $reverse_index_main, $display_links;
	
	$block_day = $mode == $unit_mode;

//~ 	foreach ($main_lessons as $m_lsns) {
//~ 		debug_print("MAIN_N1 -$m_lsns-\n");
//~ 		debug_print("video number " . $videos[$m_lsns][$num] . "\n");
//~ 	}

	foreach ($main_lessons as $m_lsns) {
		if ($videos[$m_lsns][$related]) {
			$related_list = $videos[$m_lsns][$related];
			debug_print("PROCESS_RELATED $related_list\n");
			$related_array = explode(', ', $related_list);
			foreach ($related_array as $rltd_video) {
//~ 				debug_print("PROCESS_RELATED type of $main_lessons = " . gettype($main_lessons) . "\n");
//~ 				debug_print("$main_lessons=" . print_r($main_lessons, true) . "\n");
				$main_vid = $reverse_index_main[$rltd_video];
				debug_print("PROCESS_RELATED $rltd_video -> $main_vid\n");
				# Keep related videos on one day when in unit mode.
				INCREMENT_COUNTERS (1, 1, $related_avg_time, 1);
				PRINT_LINE($rltd_video, 'related', 1, $videos[$main_vid][$title], $videos[$main_vid][$url], false, $block_day, false, false, __LINE__);
			}
		}
	}
	

}

################################################################################
### VIDEO_LIST (start_index, end_index)
################################################################################
function VIDEO_LIST (
	$first_ix, # Index for first video in array.
	$last_ix # Index for last  video in array.	
	) {
################################################################################
### Print list of Videos using specified range with URLs and titles at the 
### end of the Learning Schedule.
################################################################################

	global $videos, $outfile;

    $headings = array('   Video', 'URL',   'Title');
    $head_fmt = array("%-13s",    "%-52s", "%-50s");
    
	# Print Field Headings
	foreach ($headings as $k => $key) {
		fprintf($outfile, $head_fmt[$k], "$key");
	} 
	fwrite($outfile, "\n");
	
	for ($vix = $first_ix; $vix < $last_ix; $vix++) {
		$vd = $videos[$vix];
	
		$number = sprintf("%3s", $vd['Num']);  # Fornat Lesson Number
		
		$lesson_type = array(
			'G'=>'Lesson',
			'A'=>'Lesson',
			'P'=>'Bonus',
			'R'=>'Review',
			'H'=>'Song',
			'Q'=>'Quiz',
			'S'=>'Story',
			'T'=>'Story',
			'L'=>'Alpha',
			'A'=>'Extra'
		);
		
//~ 		foreach ($vd['Type'] as $tp) {
//~ 			if (strpos($tp, 'G')) {
//~ 				$lesson_type = "$number (Lesson)";
//~ 			}
//~ 			if (strpos($tp, 'A')) {
//~ 				$lesson_type = "$number (Lesson)";
//~ 			}
//~ 			if (strpos($tp, 'P')) {
//~ 				$lesson_type = "$number (Bonus)";
//~ 			}
//~ 			if (strpos($tp, 'R')) {
//~ 				$lesson_type = "$number (Review)";
//~ 			}
//~ 			if (strpos($tp, 'H')) {
//~ 				$lesson_type = "$number (Song)";
//~ 			}
//~ 			if (strpos($tp, 'Q')) {
//~ 				$lesson_type = "$number (Quiz)";
//~ 			}
//~ 			if (strpos($tp, 'S')) {
//~ 				$lesson_type = "$number (Story)";
//~ 			}
//~ 			if (strpos($tp, 'T')) {
//~ 				$lesson_type = "$number (Story)";
//~ 			}
//~ 			if (strpos($tp, 'L')) {
//~ 				$lesson_type = "$number (Alpha)";
//~ 			}
//~ 			if (strpos($tp, 'X')) {
//~ 				$lesson_type = "$number (Extra)";
//~ 			}
//~ 		}
				
//~ 		fprintf($outfile, $head_fmt[0], $lesson_type);
		fprintf($outfile, $head_fmt[0], "$number(" . $lesson_type[$vd['Type']] . ")");
		fprintf($outfile, $head_fmt[1], $vd[$headings[1]]);
		fprintf($outfile, $head_fmt[2], $vd[$headings[2]]);
		fprint ($outfile, "\n");
		
	} # End VLOOP
     
   return;
}

################################################################################
### GET_VIDEOS ($video_file)
################################################################################
function GET_VIDEOS (
	$vid_file	# Input CSV (TSV) file of videos.
	) {
################################################################################
###  Builds an Array of hashes for all videos from input file.
###       Return: @videos
### 
### Syntax of Video Input File: (UTF-8, Tab-separated Values)
### First line is the list of fields. Valid fields are
###		Type		Kind of video. Determines how it is processed.
###			A: Alphabet Lesson
###			G: Grammar Lesson
###			L: aLphabet learning videos (special case)
###			P: suPplemental (a.k.a. Bonus) Lesson. (Not 'B' so that G & A will sort to the top)
###			R: Review Game Video
###			S: Easy Hebrew Story Video
###			T: Easy Bible (Tanakh) Story Video -- Treated the same as S type (Story)
###			W: Worship Song Video
###			X: Extra Video -- Many of these are stories explained in a lesson video.
###			
###		Num				Lesson Number each video is associated with
###		Title				Title of YouTube video
###		Duration		Length of video in MM:SS
###		Quiz_Count	Number of Quizzes for lesson
###		Related			Lesson videos related to the current lesson. Good for a refresher.
###		Read_Verses	Complete verses presented in Grammar and Bonus videos. 
###							For Bible Story and Extra videos, the Bible chapter(s) covered by the story.
###		URL				URL in YouTube of video
###		All_Verses		Partial verses presented in Grammar and Bonus videos. Not used.
###		Paraphrases	Passages paraphrased in Grammar and Bonus videos. Not used.
###
### There is usually only one main lesson video (Grammar or Alphabet type) but the code can handle 
### multiple videos of any type.
###
################################################################################

	global $csv_char, $fields, $reverse_index_main, $reverse_index_quiz, $num, $type, $sfx;

	$vids      = array();   # Array of Hashes of Videos. 
	$variables = array();   # Array of comma-separated values for current video
	
	debug_print("GET_VIDEOS Subroutine\n");
	
	if (!file_exists($vid_file)) {
		die("ERROR: Missing video file $vid_file<br>\n");
	}
	$vidfile = fopen($vid_file, "r") or die("ERROR: $! ($vid_file)\n");	
	
	# First line contains field titles. Assign to fields variable.
	$line = fgets($vidfile);
	
	$line = CLEANUP($line);
	
	debug_print("$line\n");
	$fields = explode($csv_char, $line);	# Split line into fields using delimiter
#	debug_print("Nbr flds=" . count($fields) . "\n");
	
	$ln = 0;				# Keep track of index for array (line nunber).
	
	# READV: 
	while (!feof($vidfile)){
		$line = fgets($vidfile);
		
		$line = CLEANUP($line);
		if ($line != '') {
			    
			$variables = explode($csv_char, $line);
					
			# Build hash for current line in video file.
			$ix = 0;
			foreach ($variables as $v) {
				# Strip off leading & trailing whitespace and quotation marks
				# Quotation marks are used when a value contains commas.
				$v = preg_replace('/^[\"\'\s]+|[\"\'\s]+$/', '', $v);  # Remove leading and trailing whitespace and quotation marks

				# Test for blank title here? If blank, replace with English title. 
				# Then in phrase file, you can keep locale accurate.

				$vids[$ln][$fields[$ix]] = $v;
				$ix++;
			}
			if ($vids[$ln][$type] == 'G' || $vids[$ln][$type] == 'A') { # main lesson
				$reverse_index_main[$vids[$ln][$num]] = $ln; # create a reverse index for main videos back to the videos array
			}
			if ($vids[$ln][$type] == 'Q') { # quiz
				$reverse_index_quiz[$vids[$ln][$num] . '.' . $vids[$ln][$sfx]] = $ln; # create a reverse index for main videos back to the videos array
			}
	    }
	    
		$ln++;
	} # End of READV
					
	fclose($vidfile);

	debug_print("End of GET_VIDEOS\n");
	
	return ($vids);	

} # End of GET_VIDEOS	

################################################################################
### GET_PHRASES (input_file)
################################################################################
function GET_PHRASES (
	$lang_file
	) {
################################################################################
### Creates the hash of phrases and hash of Bible book names from the input file. 
### 
###       Return:  %phrase_hash, %canon
### 
### Syntax of Phrases Input File:
### 
### 	Default:
### 		<tag>Text for that tag with && character to mark where .
### 
### 		The text following the tag will be assigned to that tag in the program.
###			'&&' will be substituted with the lesson number or other specific information.
###			Some of the text is a URL. The correct link may be different for different
###				 languages.
### 		
### 
### 	Special Cases: 
###
###         <introN>, <howtoN>, and <linksN> are for the content at the 
###                beginning of the learning schedule. N will increment from 0. 
###				   the number of lines is flexible.
###                
### 		<Bible>
### 		<Book-of-the-Bible-tag>Book-of-the-Bible-name-in-target-language
###			</Bible>
### 
### 
### 		The tags <Bible> and </Bible> mark the beginning and end of   
### 			book of the bible tag/text pairs. 
### 
### 
### Comment Character: #
### 	Comments can only be full line. (One of the URLs contains a '#')
### 	Leave the Commented English template for each tag so that the translation  
### 		can be compared to the original.
### 
### Comments and blank lines will be ignored.
### 

################################################################################
	
	global $debug, $phrase_hash, $canon_hash, $bible_book_to_index;
	
	$phrase_hash = array();
	$canon_hash = array();
	$ln          = 0; 	# Keep track of line number for input file.
	$build_canon = 0;	# Flag to keep track of Bible Books vs. normal tags
	$line        = "";
	$bible_book_to_index = array(); # used to build bible book name to index array
	$bible_book_index = 1;
	
	debug_print("GET_PHRASES Subroutine.\n");

	$infile = fopen("$lang_file", "r"); # || die "ERROR: $! ($lang_file).\n";
	
	# READF: 
	while (!feof($infile)){
		$line = fgets($infile);
		$ln++;
		
	    $line = CLEANUP($line);
	    	
		# Check for comments and remove. Only full-line comments are allowed.
//~ 		debug_print("Line: " . escapeChars($line) . "\n");
		$cmt_ix = strpos($line, '#'); # Returns -1 if no occurrance.
#		debug_print("Comment inx=$cmt_ix\n");
		if ($cmt_ix !== false && $cmt_ix == 0) { # if ($cmt_ix >= 0) allows for partial-line comments
#			debug_print("Index for comment: $cmt_ix     -$line-\n");
			$line = substr($line, 0, $cmt_ix);
#			debug_print("Line after stripping comment: $line\n");
		}
		
//~ 		debug_print("line len=" . strlen($line) . "\n");
		if (strlen($line) == 0) {
//~ 			debug_print("Skipping blank line\n");
			continue; # Skip over blank lines or full-line comments
		}
		
#		$line =~ /<(.*)>(.*)/;
		preg_match('/<(.*)>(.*)/', $line, $matches);
		if (count($matches) != 3) {
			debug_print("***** ERROR: Could not parse line " . escapeChars($line) . "\n");
			exit;
		}
		$ltag  = $matches[1];			# Hash key
		$ltext = $matches[2];			# Hash value
		
//~ 		debug_print("Hash key=$ltag, hash value=$ltext\n");
		if (strpos($ltag, 'intro') === 0) {
//~ 			debug_print("Found intro\n");
			preg_match('/(intro)(\d+)/', $ltag, $matches);
//~ 			print_matches($matches);
			$phrase_hash[$matches[1]][$matches[2]] = $ltext;
//~			debug_print($matches[1] . "(" . $matches[2] . ") is " . $phrase_hash[$matches[1]][$matches[2]] . "\n");
		}
		
		if (strpos($ltag, 'howto') === 0) {
//~ 			debug_print("Found howto\n");
			preg_match('/(howto)(\d+)/', $ltag, $matches);
//~ 			print_matches($matches);
			$phrase_hash[$matches[1]][$matches[2]] = $ltext;
//~			debug_print($matches[1] . "(" . $matches[2] . ") is " . $phrase_hash[$matches[1]][$matches[2]] . "\n");
		}
		
		if (strpos($ltag, 'links') === 0) {
//~ 			debug_print("Found links\n");
			preg_match('/(links)(\d+)/', $ltag, $matches);
//~ 			print_matches($matches);
			$phrase_hash[$matches[1]][$matches[2]] = $ltext;
//~ 			debug_print($matches[1] . "(" . $matches[2] . ") is " . $phrase_hash[$matches[1]][$matches[2]] . "\n");
		}

		if ($ltag == 'Bible') {  # Turn on flag at start of Bible section
			$build_canon = 1;
			continue;
		}
		if ($ltag == '/Bible') { # Turn off flag at end of Bible section
			$build_canon = 0;
			continue;
		}
	
		if ($build_canon) {
			$canon_hash[$ltag] = $ltext;
			
			# build bible_book_index for English bible book name to index for use in B_TRANSLATE
			$bible_book_to_index[$ltag] = $bible_book_index;
			$bible_book_index ++;
		} else{
			$phrase_hash[$ltag] = $ltext;		
		}

	} # End of READF
					
	fclose($infile);
	
	if ($debug) {
		debug_print("\nContents of phrase_hash:\n");
		foreach ($phrase_hash as $key => $value) {
			if (is_array($value)) {
				debug_print(sprintf("%-20s", $key) . " ********\n");
				foreach ($value as $k => $v) {
					debug_print(sprintf("  %-20s", "$key"));
					debug_print("-$v-\n");
				}
				debug_print(" ********\n");
			} else {
				debug_print(sprintf("%-20s", $key) . "-$value-\n");
			}
		}
				
//~ 		debug_print("<\nContents of canon_hash:\n");
//~ 		foreach ($canon_hash as $key => $value) {
//~ 			if (is_array($value)) {
//~ 				debug_print(sprintf("%-20s", $key) . " ********\n");
//~ 				foreach ($value as $k => $v) {
//~ 					debug_print(sprintf("  %-20s", "$key"));
//~ 					debug_print("-$v-\n)";
//~ 				}
//~ 				debug_print(" ********\n");
//~ 			} else {
//~ 				debug_print(sprintf("%-20s", $key) . "-$value-\n");
//~ 			}
//~ 		}
//~ 		debug_print("\n");
	}
			
#	return (\$phrase_hash, \$canon_hash);

} # End of GET_PHRASES

################################################################################
### INCREMENT_COUNTERS (incr_main, incr_vid, adjust_secs, adjust_factor)
################################################################################
function INCREMENT_COUNTERS (
		$counter_one,
		$counter_two,
		$counter_three,
		$counter_four
	) {

	global $main_count, $vid_count, $time_adjust, $time_count, $outfile;

//~ 	debug_print("INCREMENT_COUNTERS Subroutine ($counter_one, $counter_two, $counter_three, $counter_four)\n");

	$main_count    = $main_count + $counter_one;
	$vid_count 	   = $vid_count  + $counter_two;
	$time_adjust   = MAKE_SECS($counter_three) * $counter_four;
//~ 	debug_print("Type of $" . "time_count=" . gettype($time_count) . "\n");
//~ 	debug_print("Type of $" . "time_adjust=" . gettype($time_adjust) . "\n");
	$time_count    = $time_count + $time_adjust;
	
//~ 	debug_print("INCREMENT_COUNTERS $main_count, $vid_count, $time_adjust, $time_count\n");
	return;
}

################################################################################
### B_TRANSLATE (Bible Verses)
################################################################################
function B_TRANSLATE ($ref_str) {
################################################################################
### Substitutes uses %canon to replace default Bible book names with those from  
###	target language. 
###
###  Return: Text String with replaced Bible book names and including links
################################################################################

	global $canon, $lang2, $lang3, $bible_book_to_index;
	
	debug_print("B_TRANSLATE subroutine ($ref_str)\n");
	
	$text_str = "";
	
	# parse the list of references
	$refs = explode("; ", $ref_str);
	foreach ($refs as $ref_str) {
		# strip off book name
		$last_space = strrpos($ref_str, " ");
		$book_name = substr($ref_str, 0, $last_space);
		$chap_verse = substr($ref_str, $last_space);
		$parts = explode(':', $chap_verse);
		$chap = $parts[0];
		$verse = $parts[1];

		# find the index of the book - could be done more efficiently with a reverse index but is it worth it?
		$book_index = $bible_book_to_index[$book_name];
		$link = "https://globalbibletools.com/$lang2/read/$lang3/" . sprintf('%02d', (int)$book_index) . sprintf('%03d', (int)$chap);
		debug_print("Book: $book_index, Chapter: $chap, Link: $link\n");
		
		$text_str .= ($text_str != '' ? '; ' : '') . "<a href='" . $link . "'>" . $canon[$book_name] . " $chap_verse</a>";
	}
	debug_print("After: $text_str\n");

	return $text_str;
}

//~ ################################################################################
//~ ### G_GET_LINK (Bible Verses)
//~ ################################################################################
//~ function G_GET_LINK ($ref_str) {
//~ ################################################################################
//~ ### Generates a link to globalbibletools.com for a biblical reference 
//~ ###
//~ ###  Return: link to globalbibletools.com
//~ ################################################################################

//~ 	global $canon, $lang2, $lang3;
//~ 	
//~ 	debug_print("G_GET_LINK subroutine ($ref_str)\n");
//~ 	
//~ 	# strip off book name
//~ 	$parts = explode(' ', $ref_str);
//~ 	$book_name = $parts[0];
//~ 	$chap_verse = $parts[1];
//~ 	$parts = explode(':', $chap_verse);
//~ 	$chap = $parts[0];
//~ 	$verse = $parts[1];
//~ 	
//~ 	$ix = 1;
//~ 	foreach ($canon as $key => $value) {
//~ 		if ($canon[$key] == $book_name) {
//~ 			$book = $ix;
//~ 			break;
//~ 		}
//~ 		$ix++;
//~ 	}
//~ 	$link = "https://globalbibletools.com/$lang2/read/$lang3/" . sprintf('%02d', (int)$book) . sprintf('%03d', (int)$chap);
//~ 	debug_print("Book: $book, Chapter: $chap, Link: $link\n");

//~ 	return $link;
//~ }

################################################################################
### CLEANUP (text_string)
################################################################################
function CLEANUP (
	$str	# Text string to be cleaned up.
	) {
################################################################################
###    Removes UTF-8 BOM, line-breaks, and leading and trailing whitespace
### 	Returns cleane up text string.
################################################################################
	
	$before = $str;
	$len = strlen($str);
#	$str =~ s/\R//g;		 # Remove line-breaks, regardless of type or platform using.
	$str = preg_replace("/[\n\r]/","",$str);
#	str_replace(["\n\r", "\n", "\r"], '', $str);
#	$str =~ s/^\x{FEFF}//;   # Remove UTF-8 BOM (ZERO WIDTH NO-BREAK SPACE (U+FEFF)) from first line if present.
	$bom = pack('H*','EFBBBF');
	$str = preg_replace("/^$bom/", '', $str);
#	$str =~ s/^\s+|\s+$//g;  # Remove leading and trailing whitespace
	$str = preg_replace('/^\s+|\s+$/', '', $str);
	
//~ 	if ($len != strlen($str)) {
//~ 		debug_print("CLEANUP before $len, after " . strlen($str) . "\n");
//~ 		debug_print("before $before\n");
//~ 		debug_print("after $str\n");
//~ 	}
	
	return $str;
}

################################################################################
### MAKE_SECS (mm:ss)
################################################################################
function MAKE_SECS ($str) {
################################################################################
### Converts text string of form mm:ss into seconds.
###		Returns number of seconds.
################################################################################
		
#	$str =~ s/(\d+):(\d+)//;
	preg_match('/(\d+):(\d+)/', $str, $matches);
//~ 	debug_print("MAKE_SECS($str) $"."matches=" . print_r($matches, true) . "\n");
	
	if (is_array($matches) && count($matches) == 3) {
		$seconds = (((int)$matches[1]) * 60) + ((int)$matches[2]);
	} else {
//~ 		debug_print("MAKE_SECS: failed match\n");
//~ 		exit;
		$seconds = 0;
	}
			
	return $seconds;

}

################################################################################
### MAKE_TIME (seconds, include_hours)
################################################################################
function MAKE_TIME (
	$secs,  # Time in seconds
	$include_hours
	) {
################################################################################
### Converts numeric value of seconds to a text string of form mm:ss 
###		Returns text string of form mmm:ss or hh:mm:ss
################################################################################
	
	debug_print("MAKE_TIME(Seconds: $secs, Include Hours: $include_hours)\n");
		
	$seconds = $secs % 60;			 # Remainder after minutes divided out
	$minutes = 0;
	$hours   = 0;
	
	
	if (!$include_hours) {
	    $minutes = ($secs - $seconds)/60;
		$result  = sprintf("%02s:%02s", $minutes, $seconds);
	} else {
 	    $hours   = (int)(($secs - $seconds)/3600);
	    $minutes = (($secs - $seconds) % 3600)/60;
		$result = sprintf("%s:%02s:%02s", $hours, $minutes, $seconds);
	}
			
	debug_print("T: $secs, H: $hours, M: $minutes, S: $seconds\n");

	return $result;

}

################################################################################
### escapeChars ($str)
################################################################################
function escapeChars ($str) {
################################################################################
### Escapes HTML characters for echoing back to webpage
###		Returns text string with HTML characters escaped
################################################################################
	
//~ 	debug_print("before $str\n");
	$str = preg_replace('/</','&lt;', $str);
	$str = preg_replace('/>/','&gt;', $str);
//~ 	debug_print("after $str\n");
	return $str;
}

################################################################################
### doLog ($msg)
################################################################################
function doLog ($msg) {
################################################################################
### Writes a message to the log file
###		Returns nothing
################################################################################
	
	global $log_file_name;
	$logFile=fopen($log_file_name,"a");
	fwrite($logFile, $msg);
	fclose($logFile);
}

################################################################################
### diet ($msg, $condition)
################################################################################
function diet ($msg, $condition) {
################################################################################
### Displays a message and then exits - similar to die function in Perl
###		Returns nothing
################################################################################

	global $debug_to_log_file;
	
	if ($condition) {
		if ($debug_to_log_file) {
			echo $msg;
		}
		debug_print($msg);
		exit;
	}
}

################################################################################
### print_matches ($matches)
################################################################################
function print_matches ($matches) {
################################################################################
### Prints the $matches array resulting from calling preg_match() for debugging purposes
###		Returns nothing
################################################################################

	for ($i = 0; $i < count($matches); $i++) {
		debug_print("match $i " . escapeChars($matches[$i]) . "\n");
	}
}

################################################################################
### debug_print($str)
################################################################################
function debug_print ($str) {
################################################################################
### Prints $str to either the log file or echos back to the webpage
###		Returns nothing
################################################################################

	global $debug, $debug_to_log_file;
	if ($debug) {
		if ($debug_to_log_file) {
			doLog($str);
		} else {
			$str = str_replace("\n", "<br>\n", $str);
			print $str;
		}
//~ 		exit;
	}
}

function check_for_max_iterations($iter, $line) {

	if ($iter++ > 200) {
		debug_print("***** ERROR: Exceeded 200 iterations at line $line\n");
		exit;
	}
	return $iter;
}

################################################################################
### IS_POS_INT (value)
################################################################################
function IS_POS_INT ($val) {
################################################################################
### Tests a variable to see if it is a positive integer.
###		Returns number of seconds.
################################################################################
		
	return (is_numeric($val) && $val > 0 && $val == round($val));
}

echo "Got to the end<br>\n";
?>