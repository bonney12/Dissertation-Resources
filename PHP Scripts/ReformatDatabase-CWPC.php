<?php

/**
 * Reformat Database Script - CWPC
 *
 * This script takes in raw CWPC data from Chūnagon and re-formats it so that it can
 * be coded for inclusion in the statistical model.
 *
 * The script iterates over the database rows, carrying out the following functions:
 * - Cuts from the last hashtag of the preceding context to the next hashtag of the following context,
 *   stitching them together alongside the verb;
 * - Inserts this data into a new table called 'CWPC Reformatted'.
 *
 * The table of original data should be called 'CWPC Original'.
 * A table with the same structure but 'Utterance' instead of 'PrecedingContext', 'Verb' and 'FollowingContext'
 * should exist under the name of 'CWPC Reformatted'.
 *
 * @author Michael Bonney <michael@mbonney.uk>
 * @copyright cc-by-sa
 */

//
// Database connection
// Edit the lines below as needed to point to the database containing a table with the
// name `CSJ Results` which contains the data from the SELECT query provided.
//
$hostname = '127.0.0.1';
$database = '';        // <-- Put your database name here
$username = '';        // <-- Put your database username here
$password = '';        // <-- Put your database password here
$charset  = 'utf8mb4';

//
// WARNING: Do not edit below this line unless familiar with coding.
//

echo "//\n";
echo "// Executing ReformatDatabase - CWPC\n";
echo "//\n\n";

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

// Create query to get all rows
$results = $pdo->query('SELECT * FROM `CWPC Original`');

// Check if rows could be retrieved and if not, error
if($results->rowCount() === 0) {
  echo "[ERROR] [ROWS] No rows were retrieved. Is your table name 'CWPC Original'?";
  exit();
}

echo "[INFO] [ROWS] Rows retrieved successfully. Row count: " . $results->rowCount() . "\n";


echo "[INFO] [ROWS] Looping...\n";

// Loop over all results
foreach($results AS $result) {
  $localResult = $result;

  // Step 1: Check preceding context

  // To prevent errors, we set PrecedingContext to an empty string if null
  if($localResult['PrecedingContext'] === null) {
    $localResult['PrecedingContext'] = '';
  }

  // Check for last instance of hashtag in the string.
  $pos = strrpos($localResult['PrecedingContext'], "#");

  // If there is no hashtag, default to '0' (i.e. beginning of string)
  // This will be the case for early rows in the database
  if($pos === false) {
    $pos = 0;
  } else {
    // If $pos is set, add 1 to it so that we exclude the hashtag from the phrase.
    $pos++;
  }

  // Cut the preceding part
  $precedingContext = substr($localResult['PrecedingContext'], $pos);

  // Step 2: Check following context

  // To prevent errors, we set FollowingContext to an empty string if null
  if($localResult['FollowingContext'] === null) {
    $localResult['FollowingContext'] = '';
  }

  // Check for first instance of hashtag in the string - this is our end boundary.
  $pos = strpos($localResult['FollowingContext'], "#");

  // If there is no hashtag, default to 'null' (i.e. the end of the string)
  if($pos === false)
    $pos = null;

  $followingContext = substr($localResult['FollowingContext'], 0, $pos);

  if($localResult['Sex'] === '男')
    $localResult['Sex'] = 'M';
  elseif($localResult['Sex'] === '女')
    $localResult['Sex'] = 'F';

    echo "[INFO] [INSERT] Checking if utterance '" . $precedingContext . $localResult['Verb'] . $followingContext .  "' for talk " . $localResult['TalkID'] . " exists... ";

    $checkQuery = $pdo->prepare('SELECT COUNT(`TalkID`) AS `Count` FROM `CWPC Reformatted` WHERE `Utterance` = ?');
    $checkQuery->execute([
      $precedingContext . $localResult['Verb'] . $followingContext
    ]);

    $checkResult = $checkQuery->fetch();

    if($checkResult['Count'] !== 0) {
      echo "Exists. Skipping...\n";
      continue;
    }

    echo "Does not exist. Inserting...\n";

    echo "[INFO] [INSERT] Inserting clause starting at " . $localResult['StartPosition'] . " for talk " . $localResult['TalkID'] . "\n";

  // Person who produced utterance is Speaker, so prepare query and execute.
  $updateQuery = $pdo->prepare('INSERT INTO `CWPC Reformatted` (`Corpus`, `TalkID`, `StartPosition`, `ConsecutiveNumber`, `Utterance`, `LexemeReading`, `Lexeme`, `LexemeSubclassification`, `WordType`, `POS`, `ConjugationType`, `ConjugationForm`, `PronunciationForm`, `JapaneseOrChineseOrigin`, `OriginalString`, `MorningOrMeetingOrBreak`, `Subcategory`, `SurveyDate`, `Location`, `ConversationParticipants`, `SpeakerID`, `Sex`, `AgeRange`, `Occupation`, `OccupationType`, `Post`, `Origin`, `LongestPlaceOfResidence`) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
  $updateQuery->execute([
    $localResult['Corpus'],
    $localResult['TalkID'],
    $localResult['StartPosition'],
    $localResult['ConsecutiveNumber'],
    $precedingContext . $localResult['Verb'] . $followingContext,
    $localResult['LexemeReading'],
    $localResult['Lexeme'],
    $localResult['LexemeSubclassification'],
    $localResult['WordType'],
    $localResult['POS'],
    $localResult['ConjugationType'],
    $localResult['ConjugationForm'],
    $localResult['PronunciationForm'],
    $localResult['JapaneseOrChineseOrigin'],
    $localResult['OriginalString'],
    $localResult['MorningOrMeetingOrBreak'],
    $localResult['Subcategory'],
    $localResult['SurveyDate'],
    $localResult['Location'],
    $localResult['ConversationParticipants'],
    $localResult['SpeakerID'],
    $localResult['Sex'],
    $localResult['AgeRange'],
    $localResult['Occupation'],
    $localResult['OccupationType'],
    $localResult['Post'],
    $localResult['Origin'],
    $localResult['LongestPlaceOfResidence']
  ]);
}

echo "[INFO] Completed successfully";

?>
