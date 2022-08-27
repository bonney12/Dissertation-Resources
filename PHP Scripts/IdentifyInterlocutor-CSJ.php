<?php

/**
 * IdentifyInterlocutor Script - CSJ
 *
 * This script modifies the CSJ database in order to fill columns that will identify
 * the speaker and listener of a given utterance. The script will evaluate the TalkID
 * to ascertain if the channel of the utterance is L or R. Then, it will use the Talk_*
 * columns to set the correct data in the Speaker_ and Interlocutor_ columns.
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
echo "// Executing IdentifyInterlocutor - CSJ\n";
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
$results = $pdo->query('SELECT * FROM `CSJ Results`');

// Check if rows could be retrieved and if not, error
if($results->rowCount() === 0) {
  echo "[ERROR] [ROWS] No rows were retrieved. Is your table name 'CSJ Results'?";
  exit();
}

echo "[INFO] [ROWS] Rows retrieved successfully. Row count: " . $results->rowCount() . "\n";
echo "[INFO] [ROWS] Looping...\n";

// Loop over all results
foreach($results AS $result) {
  // Get end character of ClauseID - this represents the speaker (L always interviewer,
  // R always interviewee - cf. p114 of kokken124 - 日本語話し言葉コーパスの構築法)
  $channel = substr($result['ClauseID'], -1);

  $speakers = [
    'interviewer' => [
      'sex' => $result['Talk_Interviewer_Sex'],
      'age' => $result['Talk_Interviewer_AgeRange'],
      'birthplace' => $result['Talk_Interviewer_BirthPlace']
    ],

    'speaker' => [
      'sex' => $result['Talk_Speaker_Sex'],
      'age' => $result['Talk_Speaker_AgeRange'],
      'birthplace' => $result['Talk_Speaker_BirthPlace']
    ]
  ];

  if($channel == 'L') {
    $speakerData = $speakers['interviewer'];
    $interlocutorData = $speakers['speaker'];
  } elseif($channel == 'R') {
    $speakerData = $speakers['speaker'];
    $interlocutorData = $speakers['interviewer'];
  }

  echo "[INFO] [UPDATING] [L] Setting clause " . $result['ClauseID'] . " from talk " . $result['TalkID'] . " (Speaker data is " . $speakerData['sex'] . ", " . $speakerData['age'] . ", " . $speakerData['birthplace'] . ")\n";

  // Person who produced utterance is Interviewer, so prepare query and execute.
  $updateQuery = $pdo->prepare('UPDATE `CSJ Results` SET `Speaker_Sex` = ?, `Speaker_AgeRange` = ?, `Speaker_BirthPlace` = ?, `Interlocutor_Sex` = ?, `Interlocutor_AgeRange` = ?, `Interlocutor_BirthPlace` = ? WHERE `ID` = ?');
  $updateQuery->execute([
    $speakerData['sex'],
    $speakerData['age'],
    $speakerData['birthplace'],
    $interlocutorData['sex'],
    $interlocutorData['age'],
    $interlocutorData['birthplace'],
    $result['ID']
  ]);
}

echo "[INFO] Completed successfully";

?>
