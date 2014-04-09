<?php

/*
 * -----------------------------------------------------------------------------------------------------------------------------------------------------------------------
 * 
 * Author: Andrew Odendaal <hsmoore.com>
 * Date: 20 August 2013
 * 
 * - Task ----------------------------------------------------------------------------------------------------------------------------------------------------------------
 * Develop a program that, given a series of points (latitude,longitude,timestamp) for a cab journey from A-B, will disregard potentially erroneous points.
 * Try to demonstrate a knowledge of Object Oriented concepts in your answer. Your answer must be returned as a single PHP file which can be run against the PHP 5.3 CLI. 
 * The attached dataset is provided as an example, with a png of the 'cleaned' route as a guide.
 * 
 * -----------------------------------------------------------------------------------------------------------------------------------------------------------------------
 */


class DataPoint {

    // class vars
        public $latitude = 0;
        public $longitude = 0;
        public $timestamp = 0;
    // --
    
    // class constructor
        public function __construct($row) {
            $this->latitude = $row[0];
            $this->longitude = $row[1];
            $this->timestamp = $row[2];
        }
    // --

    // conversion method - degrees to radians
        public function deg2rad($input) {
            return $input * pi() / 180;
        }
    // --

    // calculate speed/distance methods
        public function calculateSpeedTo(DataPoint $point) {
            // average speed in km/hour between the 2 points
            $time = $point->timestamp - $this->timestamp;
            if ($time === 0) return 0;
            // metres/second
            $speed = $this->calculateDistanceTo($point) / $time;
            // km/hour
            return $speed * ((60 * 60) / 1000);
        }
        public function calculateDistanceTo(DataPoint $point) {            
            // this method's calculations from -> http://stackoverflow.com/questions/27928/how-do-i-calculate-distance-between-two-latitude-longitude-points
            
            $dLat = $this->deg2rad($point->latitude - $this->latitude);
            $dLon = $this->deg2rad($point->longitude - $this->longitude);

            $lat1 = $this->deg2rad($this->latitude);
            $lat2 = $this->deg2rad($point->latitude);

            $a =
                sin($dLat / 2) * sin($dLat / 2) +
                sin($dLon / 2) * sin($dLon / 2) * cos($lat1) * cos($lat2)
            ;
            $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

            return abs(6378100 * $c); // multiply by earth's radius in metres ;)
        }
    // --
}

class Helpers {
    
    // CSV helper methods
        public static function fromCSV($file) {
            $points = array();
            while ($row = fgetcsv($file)) {
                array_push($points, new DataPoint($row));
            }

            usort($points, function($a, $b) {
                $ts1 = $a->timestamp;
                $ts2 = $b->timestamp;
                if ($ts1 > $ts2) return 1;
                else if ($ts1 < $ts2) return -1;
                else return 0;
            });

            return $points;
        }
        public static function toCSV(array $points) {
            $output = "";
            foreach ($points as $point) {
                $output .= implode(",", array($point->latitude, $point->longitude, $point->timestamp))."\n";
            }
            return $output;
        }
        public static function writeCSV($filename, $data) {
            $fp = fopen($filename, "w");
            fwrite($fp, $data);
            fclose($fp);
        }
    // --
    
    // CLI text colour changer
        public static function toColour($string, $colour="red") {
            switch ($colour) {
                case "red":
                    $colour = "41";
                    break;
                case "green":
                    $colour = "42";
                    break;
                case "blue":
                    $colour = "44";
                    break;
            }
            return "\033[".$colour."m".$string."\033[0m";
        }
    // --
}

class DataPointsWorker {
    
    // class access calls
        public function showValid(array $points) {
            return $this->worker($points, false);
        }
        public function showInvalid($points) {
            return $this->worker($points, true);
        }
    // --
    
    // main class worker method
        private function worker($points, $invalids = false) {
            $output = array();
            $previous_point = null;
            $current_point = null;
            $speeds = array();

            foreach($points as $current_point) {
                if (is_null($previous_point)) $previous_point = $current_point;
                if ($previous_point->calculateDistanceTo($current_point) === 0) continue; // cab doesn't seem to be moving..
                array_push($speeds, $previous_point->calculateSpeedTo($current_point));
                $previous_point = $current_point;
            }

            if (!count($speeds)) return $invalids ? array() : $points;

            $average_speed = array_sum($speeds) / sizeof($speeds);
            $invalid_radius = $average_speed * 1.2;

            $previous_point = null; // reset

            for ($i = 0; $i < count($points); $i++) {
                $current_point  = $points[$i];
                $previous_point = isset($points[$i - 1]) ? $points[$i - 1] : null;
                $next_point = isset($points[$i + 1]) ? $points[$i + 1] : null;

                $last_invalid = is_null($previous_point); // get boolean
                $next_invalid = is_null($next_point); // get boolean

                if (!$last_invalid) {
                    if ($previous_point->calculateSpeedTo($current_point) > $invalid_radius) $last_invalid = true;
                }
                if (!$next_invalid) {
                    if ($current_point->calculateSpeedTo($next_point) > $invalid_radius) $next_invalid = true;
                }

                if ($last_invalid && $next_invalid) {
                    if ($invalids) array_push($output, $current_point);
                } else if (!$invalids) array_push($output, $current_point);
            }

            return $output;
        }
    // --
}

/*
 * Main program execution begins
 */

if (PHP_SAPI != "cli") {
    die("You aren't using the command line!<br/>Try again in the PHP CLI as the question stated.");
}

echo Helpers::toColour("\n\nPHP Code Test - We filter through csv points to determine which points are valid or invalid.\nFollow the prompts below to get started.\n", "blue");


$filename = "points.csv";

echo "\nDo you want to use the default CSV file ($filename)?\nPress 'return' to continue / type another filename: ";
$handle = fopen ("php://stdin","r");
$line = fgets($handle);
if(trim($line) != '') $filename = trim($line);

$src = @fopen($filename, 'r') or die("File '$filename' cannot be read.\n");
$points = Helpers::fromCSV($src);
fclose($src);


// Inform user of what is about to be displayed to them
echo "\nFirst we will list all the valid points, press 'return' to continue..";
$handle = fopen ("php://stdin","r");
$line = fgets($handle);



// Initialise worker class to use for data points lists below
$worker = new DataPointsWorker();
$valid_points = (array) $worker->showValid($points);
$valid_points_csv = Helpers::toCSV($valid_points);
$invalid_points = (array) $worker->showInvalid($points);
$invalid_points_csv = Helpers::toCSV($invalid_points);


// Show all valid points
echo "\n".Helpers::toColour("Valid Points", "red")."\n";
echo $valid_points_csv;
Helpers::writeCSV("valid_points.csv", $valid_points_csv);
echo Helpers::toColour("Written to local file 'valid_points.csv'", "green")."\n";


echo "\nNow we will show all the erroneous/invalid points, press 'return' to continue..";
$handle = fopen ("php://stdin","r");
$line = fgets($handle);

// Show all erroneous points
echo "\n".Helpers::toColour("Erroneous Points", "red")."\n";
echo $invalid_points_csv;
Helpers::writeCSV("invalid_points.csv", $invalid_points_csv);
echo Helpers::toColour("Written to local file 'invalid_points.csv'", "green")."\n";


// Print 'EndOfFile' after all data is displayed
echo "\n\n-EOF-\n";
