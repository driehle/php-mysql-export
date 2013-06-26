<?php

//         **********************************************
//         ***      MySQLDBExport.class.php v2.0      ***
//         **********************************************

// -----------------------------------------------------------------------
/*
  Copyright:
  ==============
  Dieses Script wurde urspr�nglich von Dennis Riehle geschrieben - Sie d�rfen
  das Script frei verwenden, bearbeiten und weitergeben, solange dieser Copyright
  Hinweis nicht entfernt wird.
  Es erfolgt keinerlei Haftung f�r eventuell durch dieses Script entstandene
  Sch�den - die Benutzung erfolgt vollst�ndig auf eigene Gefahr.

  Beschreibung:
  ==============
  Mit dieser Klasse l�sst sich ein Dump (Export) einer MySQL Datenbank erzeugen,
  was z.B. f�r ein Backupsystem verwendet werden kann.

  Benutzung:
  ==============
  a) Bei einer bereits vorhandenen MySQL Verbindung:
      
        $export = new MySQLDBExport();    // Instanz erzeugen
        $export->set_db("Datenbank");     // Datenbank ausw�hlen
        $dump   = $export->make_dump();   // Dump erstellen
        
  b) Wenn erst noch eine Verbindung hergestellt werden muss:
  
        $export = new MySQLDBExport("Host", "User", "Passwort");
                                          // Instanz erzeugen und gleichzeitig
                                          // eine MySQL-Verbindung erstellen
        $export->set_db("Datenbank");     // Datenbank ausw�hlen
        $dump   = $export->make_dump();   // Dump erstellen
        
  Weitere Optionen:
  -----------------
  Standardm��ig wird innerhalb des Dumps ein Unix Zeilenumbruch verwendet, m�chte
  man dies �ndern, so ist vor dem Aufrufen von make_dump() folgendes zu setzen:
    $export->newline = "\r\n";
  
  MySQLDBExport erzeugt normalerweise immer einen Dump f�r alle sich innerhalb einer
  Datenbank befindlichen Tabellen, m�chte man nur eine einzelne oder nur bestimmte
  Tabellen exportieren, so l�sst sich make_dump() ein String oder ein Array der
  zu exportierenden Tabellen �bergeben:
    $export->make_dump("Tabellenname");
  Oder:
    $export->make_dump(array("Tabelle1", "Tabelle2"));

  Changelog
  ==============
  Von Version 1.0 auf Version 2.0 wurden folgende �nderungen vorgenommen:
  - Das Script wurde zu einer Klasse umgeschrieben (vielen Dank daf�r an
    Jeena Paradies <http://jeenaparadies.net>), deshalb ist Version 2.0 auch nicht
    zu Version 1.0 kompatibel!!
  - Tabellen und Spaltennamen werden nun in Backticks ausgegeben, somit werden
    auch Tabellen mit Leerzeichen im Namen unterst�tzt
  - Code Cleaning
*/

class MySQLDBExport {

  var $con     = false;
  var $db      = "";
  var $newline = "\n";

  // -----------------------------------------------------------------------
  function MySQLDBExport($host = NULL, $user = NULL, $pass = NULL) {
    if($host != NULL AND $user != NULL) {
      $con = mysql_connect($host, $user, $pass) OR die("Fehler beim Erstellen der Verbdingung: "
           . mysql_error());
      $this->con = $con;
    }
  }
  
  // -----------------------------------------------------------------------
  function set_db($db) {
    mysql_select_db($db) OR die("Fehler beim Ausw�hlen der Datenbank: " . mysql_error());
    $this->db = $db;
  }
  
  // -----------------------------------------------------------------------
  function get_tables() {
    // Liste �ber alle existierenden Tabellen in der Datenbank besorgen 
    $result = mysql_query('SHOW TABLES FROM `' . $this->db . '`;') OR die(mysql_error());
    // Array f�r Liste initialisieren
    $tables = array();
    // MySQL Ergebnisliste durchgehen und jede Tabelle in $tables hinzuf�gen
    while (list($current) = mysql_fetch_row($result)) {
      $tables[] = $current;
    }
    // Und Tabellenarray zur�ckgeben
    return $tables;
  }

  // -----------------------------------------------------------------------
  function export_table_structure($table, $drop_if_exists = true) {
    // Ausgabestring initialisieren
    $sqlstring = "";
    // Wenn DROP TABLE mit ausgegeben werden soll, dieses in den
    // Ausgabestring schreiben
    if($drop_if_exists)
    {
      $sqlstring .= "DROP TABLE IF EXISTS `$table`;" . $this->newline;
    }
    // Die CREATE TABLE Syntax per SQL Befehl besorgen oder Fehler ausgeben
    $return = mysql_query("SHOW CREATE TABLE `$table`") OR
                die("Fehler beim Exportieren der Tabellenstruktur von $table: "
                    . mysql_error()
                   );
    // Auslesen, ...
    $data = mysql_fetch_assoc($return);
    // ...in Ausgabestring schreiben ...
    $sqlstring .= str_replace("\n", $this->newline, $data['Create Table']) . ";"
               .  $this->newline
               .  $this->newline;
    // ...und diesen zur�ck geben
    return $sqlstring;
  }

  // -----------------------------------------------------------------------
  function export_table_data($table, $leave_out_fields = false) {
    // Alle Felder aus der Tabelle auslesen, bei Fehler abbrechen
    $sql = "SELECT * FROM `" . $table . "`";
    $return = mysql_query($sql) OR die(mysql_error());
    // Ausgabestring initialisieren
    $sqlstring = "";
    // Alle Ergebniszeilen abarbeiten...
    while($data = mysql_fetch_assoc($return))
    {
      // Arrays zum Sammeln der Key und der Value Werte initialisieren
      $keys = array();
      $values = array();
      foreach($data as $key => $value) {
        // Wenn dieses Feld ausgelassen werden soll, fahre mit
        // n�chster Schleife fort
        if(is_array($leave_out_fields) AND in_array($key, $leave_out_fields)) {
          continue;
        }
        // Sonst f�ge den aktuellen Key in das "Keysammelarray" hinzu
        $keys[] = "`" . $key . "`";
        // Wenn das Value NULL ist, in den String NULL umwandeln
        if($value === NULL) {
          $value = "NULL";
        }
        // Wenn das Value leer oder False ist, ein "" als Value nehmen
        elseif($value === "" OR $value === false) {
          $value = '""';
        }
        // Wenn das Value nicht numerisch ist, es mit mysql_real_escape_string()
        // escapen und zwischen " setzen
        elseif(!is_numeric($value)) {
          $value = mysql_real_escape_string($value);
          $value = "\"$value\"";
        }
        // In allen anderen F�llen ist das Value numerisch, kann belassen
        // werden wie es ist und direkt in das "Valuesammelarray" hinzugef�gt
        // werden
        $values[] = $value;
      }
      // Aus den Sammelarrays jetzt einen INSERT INTO SQL-Befehl erstellen und diesen
      // an die Ausgabe anh�ngen
      $sqlstring .= "INSERT INTO `$table` ( "
                 .  implode(", ",$keys)
                 .    " ){$this->newline}\tVALUES ( "
                 .  implode(", ",$values)
                 .    " );" . $this->newline;
    }
    // Ausgabestring zur�ckliefern
    return $sqlstring;
  }

  // -----------------------------------------------------------------------
  function make_dump($tables_input = NULL)
  {
    // Ausgabestring f�r den Datenbank Dump initialisieren mit einer ersten Kommentarzeile
    $exportstring = "-- ------------------------------------------" . $this->newline;
    // Array f�r alle zu exportierenden Tabellen initialisieren
    // Jeder Wert in diesem Array wird sp�ter als Tabellennamen aufgefasst und es wird
    // versucht einen MySQL DUMP dieser Tabelle zu erstellen
    $tables = array();
    // Wenn f�r $tables_input ein Array �bergeben wurde die Kopfzeile f�r einen indivi-
    // duellen Datenbankexport erzeugen und alle Eintr�ge aus dem Array in das Tabellen-
    // array kopieren, sodass nur die im Array �bergebenen Tabellen exportiert werden
    if(is_array($tables_input))
    {
      $tables = $tables_input;
      $exportstring .= "-- INDIVIDUAL DATABASE EXPORT              --" . $this->newline;
    }
    // Ansonsten, wenn $tables_input ein einfacher String ist, diesen als eine Tabelle
    // auffassen und nur diese eine Tabelle exportieren, als Single Database Export
    elseif(!is_array($tables_input) AND $tables_input != NULL)
    {
      $tables[0] = $tables_input;
      $exportstring .= "-- SINGLE DATABASE EXPORT                  --" . $this->newline;
    }
    // Wurde der Parameter $tables_input gar nicht mit �bergeben oder ist dieser False, so
    // wird per $this->get_tables() herausgefunden welche Tabellen alle existieren und es werden
    // alle Tabellen exportiert => Full Database Export
    else
    {
      $tables = $this->get_tables();
      $exportstring .= "-- FULL DATABASE EXPORT                    --".$this->newline;
    }
    // In den Ausgabestring die Kopfzeilen schreiben, welche den Namen der Datenbank in
    // der die Tabellen liegen, das Datum zu dem der Dump erzeugt wurde sowie den Typ des
    // Exports (s.o.) enth�lt
    $exportstring .= "-- ------------------------------------------" . $this->newline
                  .  "-- Database: " . $this->db.str_repeat(" ",30 - strlen($this->db)) . "--" 
                                                                     . $this->newline
                  .  "-- Build: " . date("d.m.Y, H:i")."                --" . $this->newline
                  .  "-- Script by Dennis Riehle                 --" . $this->newline
                  .  "-- http://tutorial.riehle-web.com/scripts/ --" . $this->newline
                  .  "-- ------------------------------------------" . $this->newline 
                                                                     . $this->newline;
    // Gehe alle Tabellen in $tables durch und exportiere nacheinander von jeder Tabelle
    // die Struktur, sowie die Daten.
    foreach($tables as $table)
    {
      $exportstring .= "-- Table: $table" . $this->newline
                    .  "-- ------------------------------------------" . $this->newline
                    .  $this->export_table_structure($table, true)
                    .  $this->export_table_data($table, false)
                    .  $this->newline;
    }
    // F�ge dem Exportstring noch ein Ende Kennzeichen hinzu...
    $exportstring .= "-- ------------------------------------------" . $this->newline
                  .  "-- Export End                              --" . $this->newline
                  .  "-- ------------------------------------------" . $this->newline;
    // ...und liefere den kompletten String zur�ck.
    return $exportstring;
  }
}
// -----------------------------------------------------------------------
/*
    ENDE
*/

?>
