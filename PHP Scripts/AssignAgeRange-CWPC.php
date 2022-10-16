<?php

/**
 * AssignAgeRange Script - CWPC
 *
 * This script interprets the age range from the CWPC dataset and replaces it with a proper age range.
 *
 * @author Michael Bonney <michael@mbonney.uk>
 * @copyright cc-by-sa
 */

//
// Database connection
// Edit the lines below as needed to point to the database containing a table with the
// name `CWPC Dataset`.
//
$hostname = '127.0.0.1';
$database = 'ModelDatasets';        // <-- Put your database name here
$username = 'CSJ';                  // <-- Put your database username here
$password = '/g8AO6HoapiwC*]z';     // <-- Put your database password here
$charset  = 'utf8mb4';

//
// WARNING: Do not edit below this line unless familiar with coding.
//

echo "//\n";
echo "// Executing AssignAgeRange - CWPC\n";
echo "//\n\n";

$ageMapping = [
  '10-19' => [10, 11, 12, 13, 14, 15, 16, 17, 18, 19],
  '20-29' => [20, 21, 22, 23, 24, 25, 26, 27, 28, 29],
  '30-39' => [30, 31, 32, 33, 34, 35, 36, 37, 38, 39],
  '40-49' => [40, 41, 42, 43, 44, 45, 46, 47, 48, 49],
  '50-59' => [50, 51, 52, 53, 54, 55, 56, 57, 58, 59],
  '60-69' => [60, 61, 62, 63, 64, 65, 66, 67, 68, 69],
  '70-79' => [70, 71, 72, 73, 74, 75, 76, 77, 78, 79]
];

// Create DSN
 $dsn = "mysql:host=$hostname;dbname=$database;charset=$charset";

 // Prepare options array for PDO
 $options = [
     PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
     PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
     PDO::ATTR_EMULATE_PREPARES   => false,
 ];

 // Attempt connection and catch any errors
 try {
   $pdo = new PDO($dsn, $username, $password, $options);
 } catch (\PDOException $e) {
   // Error was caught; throw this.
   echo "[ERROR] [MYSQL] The MySQL connection failed with the following error:\n";
   echo "[ERROR] [MYSQL] " . $e->getMessage() . " (" . $e->getCode() . ")";
   exit();
 }

// Report successful connection to CLI
echo "[INFO] [MYSQL] MySQL connection established.\n";
echo "[INFO] [MYSQL] Retrieving appropriate rows...\n";

$noInterlocutor = [];

// Otherwise, execute queries to get the data for the two interlocutors
// Update the rows and give the data for the one that it isn't

// Create query to get TalkIDs
$results = $pdo->query('SELECT `ID`, `Speaker_OriginalAge`, `Interlocutor_OriginalAge` FROM `CWPC Dataset`');

// Check if rows could be retrieved and if not, error
if($results->rowCount() === 0) {
  echo "[ERROR] [ROWS] No rows were retrieved. Is your table name 'CWPC Dataset'?";
  exit();
}

echo "[INFO] [ROWS] Rows retrieved successfully. Row count: " . $results->rowCount() . "\n";

foreach($results AS $result) {
  $localResult = $result;
  $id = $result['ID'];

  $ages = [
    'speaker' => $result['Speaker_OriginalAge'],
    'interlocutor' => $result['Interlocutor_OriginalAge']
  ];

  $newAges = [
    'speaker' => 0,
    'interlocutor' => 0
  ];

  // Ways that ages are specified:

  // Generations: 00代
  // Approximated: 00ca
  // Ranged: 64-65
  // Exact number: 00

  foreach($ages AS $key => $age) {
    // Unset $tmpAge and $identifiedAgeRange at the beginning of each iteration
    unset($tmpAge);
    unset($identifiedAgeRange);

    // Check 1: If it is a generation
    if(mb_substr($age, -1, 1, 'UTF-8') == '代') {
      // It is, so we simply remove the generation kanji
      $tmpAge = mb_substr($age, 0, mb_strlen($age, 'UTF-8')-1, 'UTF-8');
    }

    // Check 2: If it is approximated
    if(mb_substr($age, -2, 2, 'UTF-8') == 'ca') {
      // It is, so we simply remove the generation kanji
      $tmpAge = mb_substr($age, 0, mb_strlen($age, 'UTF-8')-2, 'UTF-8');
    }

    // Check 3: If it is ranged
    if(str_contains($age, '-')) {
      // With age ranges, the subset obtained from the CWPC does not contain
      // any that cross decade boundaries (all are 64-65 or 55-59 for example),
      // so taking either number before or after will be fine for this.
      $tmpAge = explode('-', $age)[0];
    }

    // Otherwise, should be a valid number.
    // We can check this, and add in a safety check so that if one of the above
    // checks has set $tmpAge, we won't run this check (to prevent a warning)
    if(isset($tmpAge) === false) {
      if(!is_numeric($age)) {
        echo "[WARN] [NUM CHECK] Row did not pass any checks for age range and is not numeric. Skipping...";
        continue;
      }

      $tmpAge = $age;
    }

    // Now we search the $ageMapping array for the value retrieved
    // $ageMapping is an associative array where the key is the age range and
    // the value is an array of ages.

    // Loop through top-level array
    foreach($ageMapping as $ageRange => $ages) {
      // Loop through each age in that age range, comparing with $tmpAge
      foreach($ages as $ageInAgeRange) {
        if($tmpAge == $ageInAgeRange) {
          // $tmpAge and current age from array are the same, so we set $identifiedAgeRange
          $identifiedAgeRange = $ageRange;
        }
      }
      // This will end the looping if we find $identifiedAgeRange to avoid unnecessary comparisons
      if(isset($identifiedAgeRange)) {
        break;
      }
    }

    // Report info back to CLI (may help with future debugging)
    echo "[INFO] Age of row " . $id . "'s " . $key . " identified as " . $identifiedAgeRange . " (originally: " . $age . ")\n";

    // Set the new age in the array for updating
    $newAges[$key] = $identifiedAgeRange;
  }

  echo "[INFO] [ROWS] Updating row...\n";

  $rowsForTalkQuery = $pdo->prepare('UPDATE `CWPC Dataset` SET `Speaker_AgeRange` = ?, `Interlocutor_AgeRange` = ? WHERE `ID` = ?');
  $rowsForTalkQuery->execute([
    $newAges['speaker'],
    $newAges['interlocutor'],
    $id
  ]);

  echo "[INFO] [ROWS] Row updated successfully.\n";
}

echo "[INFO] [ROWS] Completed successfully.\n";

?>
