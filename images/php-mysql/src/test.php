<?php

// Import PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require '/home/support/PHPMailer/src/Exception.php';
require '/home/support/PHPMailer/src/PHPMailer.php';
require '/home/support/PHPMailer/src/SMTP.php';

// Configure PHPMailer

$mail = new PHPMailer();
$mail->isSMTP();
$mail->Host = 'smtp.office365.com';
$mail->SMTPAuth = true;
$mail->Username = 'it-systembenachrichtigung@mfg.at';
$mail->Password = '9#mfg*9#mfg*';
$mail->SMTPSecure = 'starttls';
$mail->Port = 587;
$mail->CharSet = 'UTF-8';
$mail->setFrom('it-systembenachrichtigung@mfg.at', 'Mailer');
$mail->addAddress('support@mfg.at', 'IT-Support');
$mail->AddCC('florian.unger@mfg.at');
//$mail->AddCC('florian.dornigg@mfg.at');


class Gpio
{

        public $pin;
        public $gpioPath;
        public $mode;
        public $logFile;
        public $type;

        public function __construct (int $pin, string $mode, string $type)
        {
                $this->pin = $pin;
                $this->mode = $mode;
                $this->logFile = "/var/opt/scripts/log.txt";
                $this->type = $type;
                $this->gpioPath = "/sys/class/gpio/gpio" . $this->pin . "/value";
                if (!is_dir("/sys/class/gpio/gpio" . $this->pin))
                {
                        $this->initialize();
                }
        }

        protected function writeToFile(string $filePath, string $content, int $fileAppend )
        {
                if ($fileAppend === 0)
                {
                        $file = fopen($filePath, "w");
                        if (!$file)
                        {
                                throw new Exception("File open failed.");
                        }
                        fwrite($file, $content);
                        fclose($file);
                }
                else
                {
                        file_put_contents($filePath, $content, FILE_APPEND);
                }
                echo "Writing to file: " . $filePath . " << ";
                echo $content . "\n";
        }

        //Function for writing a given array into a given .csv-file
        protected function writeToCsv(string $filepath, array $content)
        {
                $file = fopen($filepath, 'a');
                if(!$file)
                {
                        throw new Exception("File open failed.");
                }
                fputcsv($file,$content);
                fclose($file);
        }

        //Function to get when motorpin was turned off and on both in timestamp and date.
        //calculates hours the motorpin was turned off and writes that after Off-Date and On-Date into an csv.
        protected function turnedOffTime()
        {
                $lastTurnedOffFile = "/var/opt/scripts/lastTurnedOff.txt";
                $lastTurnedOnFile = "/var/opt/scripts/lastTurnedOn.txt";
                $turnedOffTimeFile = "/var/opt/scripts/turnedOffTime.csv";
                $lastTurnedOffDateFile = "/var/opt/scripts/lastTurnedOffDate.txt";

                $lastTurnedOffDate = $this->readFromFile($lastTurnedOffDateFile);
                $lastOff = $this->readFromFile($lastTurnedOffFile);
                $lastOn = $this->readFromFile($lastTurnedOnFile);

                $timeTurnedOff = ($lastOn - $lastOff)/3600;
                $timeTurnedOff_dez = number_format($timeTurnedOff, 2,',','');
                $date = new DateTime('now');
                $content = array($lastTurnedOffDate,$date->format('d.m.Y H:i:s'),$timeTurnedOff_dez);
                $this->writeToCsv($turnedOffTimeFile, $content);
        }

        protected function readFromFile(string $filePath)
        {
                if (!file_exists($filePath))
                {
                        throw new Exception("Unable to open file.");
                }
                $file = fopen($filePath, "r");
                if (!$file)
                {
                        throw new Exception("File open failed.");
                }
                $content = fread($file, filesize($filePath));
                fclose($file);
                return $content;
        }

        protected function initialize()
        {
                $initializePath = "/sys/class/gpio/export";
                if (file_exists($initializePath))
                {
                        $initializePin = $this->pin;
                        $this->writeToFile($initializePath, $initializePin, 0);
                        $this->changeMode();
                }
                else
                {
                        throw new Exception($initializePath . " doesn't exist on the system!\n");
                }
        }

        protected function changeMode()
        {
                $modePath = "/sys/class/gpio/gpio" . $this->pin . "/direction";
                $modeCommand = $this->mode;
                $this->writeToFile($modePath, $modeCommand, 0);
        }

        public function changeState(int $state)
        {
                $this->writeToFile($this->gpioPath, $state, 0);
                if ($state === 0 && $this->type === 'engine')
                {
                        $lastTurnedOffFile = "/var/opt/scripts/lastTurnedOff.txt";
                        $timeStamp = time();
                        $this->writeToFile($lastTurnedOffFile, $timeStamp, 0);

                        $lastTurnedOffDateFile = "/var/opt/scripts/lastTurnedOffDate.txt";
                        $date = new DateTime('now');
                        $content = $date->format('d.m.Y H:i:s');
                        $this->writeToFile($lastTurnedOffDateFile, $content, 0);
                }
                else
                {
                        $lastTurnedOnFile = "/var/opt/scripts/lastTurnedOn.txt";
                        $timeStamp = time();
                        $this->writeToFile($lastTurnedOnFile, $timeStamp, 0);
                }
        }


        //Function to implement a deadtime when turning off(1h delay) but not when turning on
        //State is composed like this:
        // State that is multiplied by 10
        // State that should added
        public function changeStateDelayed(int $state_should)
        {
                global $mail;

                $deadTimeFile = "/var/opt/scripts/deadTimeFile.txt";
                $currentTime = time();
                $stateIs = $this->readFromFile("/sys/class/gpio/gpio20/value");
                $state = ((int)$stateIs * 10) + $state_should;


                echo "\r\n STATE = ".$state."\r\n";
                switch($state)
                {
                        case 0:
                                break;
                        case 1:
                                $this->changeState(1);
                                $lastMailState = file_get_contents("/var/opt/scripts/lastMailState.txt");
                                $test = '0';
                                var_dump($lastMailState);
                                if ($lastMailState == $test)
                                {
                                        $this->turnedOffTime();
                                        $mail->isHTML(true);
                                $mail->Subject = 'Die Lüftung wurde eingeschalten!';
                                $mail->Body    = 'Die Lüftung wurde eingeschalten!';
                                        $mail->send();
                                        echo 'Mail has been sent';

                                        $f=fopen('/var/opt/scripts/lastMailState.txt','w');
                                        fwrite($f,'1');
                                        fclose($f);
                                }
                                break;
                        case 11:
                                if(file_exists($deadTimeFile))
                                {
                                        unlink($deadTimeFile);
                                }
                                break;
                        case 10:
                                if(file_exists($deadTimeFile))
                                {
                                        $oldTime = $this->readFromFile($deadTimeFile);
                                }

                                if(!file_exists($deadTimeFile))
                                {
                                        $this->writeToFile($deadTimeFile, $currentTime, 0);
                                }
                                elseif($currentTime - $oldTime > 3600)
                                {
                                        $this->changeState(0);
                                        unlink($deadTimeFile);
                                        $lastMailState = file_get_contents("/var/opt/scripts/lastMailState.txt");
                                        $test2 = '1';
                                        var_dump($lastMailState);
                                        if ($lastMailState == $test2)
                                        {

                                                $mail->isHTML(true);
                                        $mail->Subject = 'Die Lüftung wurde ausgeschalten!';
                                        $mail->Body    = 'Die Lüftung wurde ausgeschalten!';
                                                $mail->send();
                                                echo 'Mail has been sent';

                                                $f=fopen('/var/opt/scripts/lastMailState.txt','w');
                                                fwrite($f,'0');
                                                fclose($f);
                                        }
                                }

                }


        }

        // Funktion die bei allen txt files angewandt wird, damit immer ein Datum vor der jeweiligen Meldung steht.
        protected function logError(string $errorMessage)
        {
                $date = new DateTime('now');
                $content = $date->format('d.m.Y H:i:s') . ' | ' . $errorMessage . "\r\n";
                $this->writeToFile($this->logFile, $content, 1);
        }
}

class Engine extends Gpio
{
        // DB Abfrage:
        // es wird verglichen in welchen Departments der Status der jeweiligen Personalnummer auf K (anwesend) ist.
        // Departments Vorstufe (5), Endfertigung (6), Offetdruck (7), Kartenproduktion (13)
        public function checkPresence(mysqli $mysqli)
        {
                $mysqli = new mysqli("192.168.63.11", "timeuser", "qwertz", "time");
                $mysqli->set_charset("utf8");

                $sql = "SELECT department, internalStatus, display_name
                FROM t_presence as p
                LEFT JOIN t_user as u
                ON p.pers_nr =  u.pers_nr
                WHERE p.internalStatus = '1' AND u.department IN (5, 6, 7, 13) OR p.internalStatus ='0' AND u.department IN (5, 6, 7, 13)
                ORDER BY `p`.`status` ASC";

                $names = $mysqli->query($sql);

                foreach($names as $user){

                        $active_file = '/var/opt/scripts/userlog.txt';
                        $date = new DateTime('now');
                $user_list = implode("\t|\t", $user);
                $content = $date->format('d.m.Y H:i:s') . '   |  ' . $user_list ."\r\n";
                $this->writeToFile($active_file, $content,1);
                }


                $sql = "SELECT p.pers_nr
                                FROM t_presence as p
                                LEFT JOIN t_user as u
                                ON p.pers_nr =  u.pers_nr
                                WHERE p.internalStatus = '1' AND u.department IN (5, 6, 7, 13)";


                // alle Treffer werden in result reingeschrieben
                $result = $mysqli->query($sql);
                $anzahl_K1 = $result->num_rows;  // Anzahl der Treffer wegspeichern
                //sleep(120); //120 Sekunden warten, um Bungs vorzubeugen
                $result = $mysqli->query($sql); // ... und Abfrage nochmal ausführen
                $anzahl_K2 = $result->num_rows;  // Anzahl der Treffer der zweiten Abfrage wegspeichern

                // $lastMailStateFile = 'lastMailState.txt';
                // $LastMailState = $this->readFromFile($lastMailStateFile);
                //Wenn beide Abfragen das gleiche Ergebnis liefern, ist die Abfrage gültig und es wird gehandelt
                printf ("%s \n", "Anzahl: " . $anzahl_K1 . "/" . $anzahl_K2);
                if ($anzahl_K1 == $anzahl_K2)
                {
                        // Wenn es einen Treffer gibt also wenn result größer 0 dann wird die Luft eingeschalten
                        // ansonsten ausgeschalten
                        if ($anzahl_K1 > 0)
                        {
                                $this->changeStateDelayed(1);
                        }
                        else
                        {
                                $this->changeStateDelayed(0);
                        }
                }
        }
}

class Alert extends Gpio
{
        public function networkAlert()
        {
                $networkAlert = '/var/opt/scripts/networkAlert.txt';
                $timeStamp = time();

                global $mail;

                // Wenn networkAlert file nicht exisistiert - Meldung wird in File geschrieben
                if (!file_exists($networkAlert))
                {
                        $this->writeToFile($networkAlert, $timeStamp, 0);
                        $this->logError('Datenbankserver nicht erreichbar!');

                        $mail->isHTML(true);
                        $mail->Subject = 'Lüftung Fehler!';
                        $mail->Body = 'Lüftung -> Der Datenbankserver ist nicht erreichbar!';
                        $mail->send();
                        echo 'Mail has been sent';

                }
                // Wenn Datenbank nach 15 Minuten immer noch nicht erreichbar ist
                // wird die Luft eingeschaltet auch wenn niemand eingestempelt ist.
                $lastError = $this->readFromFile($networkAlert);
                if ($timeStamp - $lastError >= 900)
                {
                        $this->changeState(1);
                        $engine->changeState(1);
                } // Meldung wenn DB länger als 12h nicht erreichbar
                if ($timeStamp - $lastError >= 43200)
                {
                        $this->logError('Lüftung -> Datenbankserver mehr als 12 Stunden nicht erreichbar!');
                        $this->writeToFile($networkAlert, $timeStamp, 0);
                }
        }
        // checkt ob networkAlert.txt file vorhanden ist, wenn ja wir das file wieder gelöscht.
        // und LED wird wieder ausgeschalten
        public function networkConnected()
        {
                $networkAlert = '/var/opt/scripts/networkAlert.txt';
                if (file_exists($networkAlert))
                {
                        unlink($networkAlert);
                        $this->changeState(0);
                }
        }
        // checkt ob das File vorhanden ist & schreibt aktuellen Timestamp in das lastTurnedoff.txt file
        public function engineRuntime()
        {
                $timeStamp = time();
                $lastTurnedOffFile = '/var/opt/scripts/lastTurnedOff.txt';
                if (!file_exists($lastTurnedOffFile))
                {
                        $this->writeToFile($lastTurnedOffFile, $timeStamp, 0);
                }
                // aktueller Timestamp wird mit Timestamp aus dem file lastTurnedOff.txt verglichen
                // Schreibt in File wenn druckluft seit über 10 Tagen läuft
                $lastTurnedOff = $this->readFromFile($lastTurnedOffFile);
                if ($timeStamp - $lastTurnedOff >= 864000)
                {
                        $this->logError('Die Lüftung läuft seit über 10 Tagen!');
                        $this->changeState(1);
                        //mail($mailto,$mailsubject,$mailtxt,$mailheader);
                }
                else
                {
                        $this->changeState(0);
                }
        }
}

try
{
        // Schaltet Luft bzw. LED, je nachdem auf welcher Option sich changeSate befindet
        $engine = new Engine(20, "out", "engine");
        $alert = new Alert(21, "out", "led");
        // Datenbankverbindung
        $mysqli = new mysqli("192.168.63.11", "timeuser", "qwertz", "time");
        $mysqli->set_charset("utf8");
        if ($mysqli->connect_error)
        {
                $alert->networkAlert();
                throw new Exception("Failed to connect to MySQL: " . $mysqli->connect_error);
        }
        else
        {

                $alert->networkConnected();
                $alert->engineRuntime();
                $engine->checkPresence($mysqli);

        }

}
catch(Exception $e)
{
        die('Unhandled Error: ' . $e);
