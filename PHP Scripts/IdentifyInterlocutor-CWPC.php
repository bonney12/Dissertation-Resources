<?php

/**
 * IdentifyInterlocutor Script - CWPC
 *
 * This script identifies interlocutors in the CWPC database and adds them to the appropriate rows.
 *
 * The script iterates over the database rows, carrying out the following functions:
 * - Identifies talks that do not actually have 2 participants (where NoOfParticipants != SpeakerID count)
 *   and skips them;
 * - Identifies the interlocutor and inserts the required information
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
echo "// Executing IdentifyInterlocutor - CWPC\n";
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

$noInterlocutor = [];

// Otherwise, execute queries to get the data for the two interlocutors
// Update the rows and give the data for the one that it isn't

// Create query to get TalkIDs
$results = $pdo->query('SELECT UNIQUE(`TalkID`) FROM `CWPC Reformatted`');

// Check if rows could be retrieved and if not, error
if($results->rowCount() === 0) {
  echo "[ERROR] [ROWS] No rows were retrieved. Is your table name 'CWPC Reformatted'?";
  exit();
}

echo "[INFO] [ROWS] Rows retrieved successfully. Row count: " . $results->rowCount() . "\n";

foreach($results AS $result) {
  $localResult = $result;

  // Create query to get TalkIDs
  $rowsForTalkQuery = $pdo->prepare('SELECT UNIQUE(`SpeakerID`) FROM `CWPC Reformatted` WHERE `TalkID` = ?');
  $rowsForTalkQuery->execute([
    $result['TalkID']
  ]);

  // Check if we got a different number of unique speakers for the talks than expected (i.e. 2)
  if($rowsForTalkQuery->rowCount() !== 2) {
    // We did, so we skip the row and save its ID in an array for output later.
    echo "[INFO] [ITEM] Got " . $rowsForTalkQuery->rowCount() . " items for talk " . $result['TalkID'] . ". Skipping... \n";
    $noInterlocutor[] = $result['TalkID'];
    // Skip the rest of the code below and move to next iteration
    continue;
  }

  // Report that we are continuing with execution
  echo "[INFO] [ITEM] Got " . $rowsForTalkQuery->rowCount() . " items for talk " . $result['TalkID'] . ". Going ahead... \n";

  // Retrieve both SpeakerIDs as an array
  $speakerIDs = $rowsForTalkQuery->fetchAll(PDO::FETCH_COLUMN, 0);

  // Create holder array for speaker data
  $speakers = [];

  // Loop over speakers, executing a query to select relevant speaker data from the database
  // This uses the speakerIDs known to the database, so both definitely exist.
  foreach($speakerIDs AS $speaker) {
    $query = "SELECT
              `Sex`,
              `AgeRange`,
              `Occupation`,
              `OccupationType`,
              `Post`,
              `Origin`,
              `LongestPlaceOfResidence`
               FROM `CWPC Reformatted` WHERE `SpeakerID` = ? LIMIT 1";
    $speakerQuery = $pdo->prepare($query);
    $speakerQuery->execute([
      $speaker
    ]);

    // Report this back to the CLI and add it to our $speakers holder array
    if($speakerQuery->rowCount() === 1) {
      echo "[INFO] [ITEM] [" . $result['TalkID'] . "] Speaker " . $speaker . " retrieved.\n";
      $speakerData = $speakerQuery->fetch();

      $speakers[$speaker] = $speakerData;
    }
  }

  // Speakers retrieved, now to set interlocutor rows...

  // Retrieve the Row ID and the SpeakerID for all rows with an individual TalkID
  // As this is inside of a foreach loop that is going through all talks, this
  // will mean the same actions are carried out for all speakers on all talks.
  // We will compare this information with the $speakers array to determine who
  // the interlocutor is by process of elimination - if SpeakerID is X, then the
  // interlocutor must be Y (and there are only two items in the array).
  $speakerRowQuery = $pdo->prepare("SELECT `ID`, `SpeakerID` FROM `CWPC Reformatted` WHERE `TalkID` = ?");
  $speakerRowQuery->execute([
    $result['TalkID']
  ]);

  // Fetch the row data (ID + SpeakerID)
  $rows = $speakerRowQuery->fetchAll();

  // Loop over the rows
  foreach($rows AS $row) {
    // Filter the array to remove the item where the key is the SpeakerID
    // This leaves $nonSpeaker as an array with only one item - the InterlocutorID
    // which itself is an array of information about hte interlocutor.
    $nonSpeaker = array_filter($speakers, function($value) use ($row) {
      return $value !== $row['SpeakerID'];
    }, ARRAY_FILTER_USE_KEY);

    // Set variables to simplify using the data
    $speakerID = $row['SpeakerID'];
    // Get interlocutorID by retrieving array of keys for $nonSpeaker and selecting 1st item
    $interlocutorID = array_keys($nonSpeaker)[0];

    // Helper variables
    $speakerInformation = $speakers[$speakerID];
    $interlocutorInformation = $speakers[$interlocutorID];

    // SpeakerIDs or InterlocutorIDs that end in 男 or 女 are missing key demographic
    // information, so if this is the case, we skip it.
    $speakerLastChar = mb_substr($speakerID, -1, 1, 'UTF-8');
    $interlocutorLastChar = mb_substr($interlocutorID, -1, 1, 'UTF-8');

    if($speakerLastChar == '男' || $speakerLastChar == '女') {
      echo '[WARN] [ITEM] [' . $result['TalkID'] . '] [Row ' . $row['ID'] . "] Speaker ID ends in 男/女 so is missing key demographics, skipping... \n";
      $noInterlocutor[] = $result['TalkID'];
      continue;
    }

    if($interlocutorLastChar == '男' || $interlocutorLastChar == '女') {
      echo '[WARN] [ITEM] [' . $result['TalkID'] . '] [Row ' . $row['ID'] . "] Interlocutor ID ends in 男/女 so is missing key demographics, skipping... \n";
      $noInterlocutor[] = $result['TalkID'];
      continue;
    }

    // Report to CLI the information found (for debugging)
    echo '[INFO] [ITEM] [' . $result['TalkID'] . '] [Row ' . $row['ID'] . '] Speaker is ' . $speakerID . ', non-speaker is ' . $interlocutorID . "\n";

    // Execute UPDATE query to set interlocutor data where the ID is the Row ID.
    $updateQuery = $pdo->prepare("UPDATE `CWPC Reformatted` SET `InterlocutorID` = ?, `Interlocutor_Sex` = ?, `Interlocutor_AgeRange` = ?, `Interlocutor_Occupation` = ?, `Interlocutor_OccupationType` = ?, `Interlocutor_Post` = ?, `Interlocutor_Origin` = ?, `Interlocutor_LongestPlaceOfResidence` = ? WHERE `ID` = ?");
    $updateQuery->execute([
      $interlocutorID,
      $interlocutorInformation['Sex'],
      $interlocutorInformation['AgeRange'],
      $interlocutorInformation['Occupation'],
      $interlocutorInformation['OccupationType'],
      $interlocutorInformation['Post'],
      $interlocutorInformation['Origin'],
      $interlocutorInformation['LongestPlaceOfResidence'],
      $row['ID']
    ]);

    // Report update to CLI
    echo '[INFO] [ITEM] [' . $result['TalkID'] . "] [Row " . $row['ID'] . "] Interlocutor information updated.\n";

  }
}

echo "[INFO] [ROWS] Completed successfully.\n";

// Report back any skipped rows with a total number of unique talks.
echo "[INFO] [ROWS] Skipped talks: " . implode(", ", array_unique($noInterlocutor)) . ' (' . count(array_unique($noInterlocutor)) . ')';

?>
