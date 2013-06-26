<?php

//         ************************************
//         ***      db_export.lib.php       ***
//         ************************************

// -----------------------------------------------------------------------
/*
	Copyright:
	==============
	Dieses Script wurde ursprünglich von Dennis Riehle geschrieben - Sie dürfen
	das Script frei verwenden, bearbeiten und weitergeben, solange dieser Copyright
	Hinweis nicht entfernt wird.
	Es erfolgt keinerlei Haftung für eventuell durch dieses Script entstandene
	Schäden - die Benutzung erfolgt auf eigene Gefahr.
	
	Beschreibung:
	==============
	Mit diesem Script lässt sich ein Dump (Export) einer MySQL Datenbank erzeugen,
	was z.B. für ein Backupsystem verwendet werden kann.
	
	Inhalt:
	==============
	- Funktion: get_tables()
	- Funktion: export_table_strukture()
	- Funktion: export_table_data()
	- Funktion: export_database()    [Hauptfunktion]
	
	Benutzung:
	==============
	Nachdem mit mysql_connect() eine Verbindung zu einer Datenbank hergestellt wurde,
	kann die Hauptfunktion wie folgt aufgerufen werden:
	
		export_database( string DB [, string/array Tabellen [, Zeilenumbruch ] ] )
	
	Für DB muss der Name der Datenbank übergeben werden, für Tabellen kann ein String
	übergeben werden um nur eine Tabelle zu exportieren oder ein Array um alle im Array
	enthaltenen Tabellen expotieren zu lassen. Wird Tabellen nicht angegeben oder ist
	es false, werden alle Tabellen in der DB exportiert. Zusätzlich kann man einen
	Zeilenumbruch angeben, der für den Dump genutzt wird - Standard ist \n.
	
	Die Funktion liefert als Rückgabewert den MySQL Dump.
*/

// -----------------------------------------------------------------------
function get_tables($database) 
{
    // Liste über alle existierenden Tabellen in der Datenbank besorgen 
	$result = mysql_query('SHOW TABLES FROM ' . $database . ';') OR die(mysql_error());
	// Array für Liste initialisieren
    $tables = array();
	// MySQL Ergebnisliste durchgehen und jede Tabelle in $tables hinzufügen
    while (list($current) = mysql_fetch_row($result)) {
        $tables[] = $current;
    }
	// Und Tabellenarray zurückgeben
    return $tables;
}

// -----------------------------------------------------------------------
function export_table_structure($db, $table, $drop_if_exists = true, $break = "\n")
{
	// Parameter für Zeilenumbruch kontrollieren, wenn ungültig auf \n setzen
	$break = ($break == "\n" OR $break == "\r\n" OR $break == "\r") ? $break : "\n";
	// Ausgabestring initialisieren
	$sqlstring = "";
	// Wenn DROP TABLE mit ausgegeben werden soll, dieses in den
	// Ausgabestring schreiben
	if($drop_if_exists)
	{
		$sqlstring .= "DROP TABLE IF EXISTS $table;$break";
	}
	// Die CREATE TABLE Syntax per SQL Befehl besorgen oder Fehler ausgeben
	$return = mysql_query("SHOW CREATE TABLE $table") OR 
				die("Fehler beim Exportieren der Tabellenstruktur von $table: "
				    . mysql_error() 
				   );
	// Auslesen, ...
	$data = mysql_fetch_assoc($return);
	// ...in Ausgabestring schreiben ...
	$sqlstring .= str_replace("\n",$break, $data['Create Table']) . ";$break$break";
	// ...und diesen zurück geben
	return $sqlstring;
}

// -----------------------------------------------------------------------
function export_table_data($db, $table, $leave_out_fields = false, $break = "\n")
{
    // Parameter für Zeilenumbruch kontrollieren, wenn ungültig auf \n setzen
	$break = ($break == "\n" OR $break == "\r\n" OR $break == "\r") ? $break : "\n";
	// Alle Felder aus der Tabelle auslesen, bei Fehler abbrechen
	$sql = "SELECT * FROM " . $table;
	$return = mysql_query($sql) OR die(mysql_error());
	// Ausgabestring initialisieren
	$sqlstring = "";
	// Alle Ergebniszeilen abarbeiten...
	while($data = mysql_fetch_assoc($return))
	{
		// Arrays zum Sammeln der Key und der Value Werte initialisieren
		$keys = array();
		$values = array();
		foreach($data as $key => $value)
		{
			// Wenn dieses Feld ausgelassen werden soll, fahre mit
			// nächster Schleife fort
			if(is_array($leave_out_fields) AND in_array($key, $leave_out_fields))
			{
				continue;
			}
			// Sonst füge den aktuellen Key in das "Keysammelarray" hinzu
			$keys[] = $key;
			// Wenn das Value NULL ist, in den String NULL umwandeln
			if($value === NULL)
			{
				$value = "NULL";
			}
			// Wenn das Value leer oder False ist, ein "" als Value nehmen
			elseif($value === "" OR $value === false)
			{
				$value = '""';
			}
			// Wenn das Value nicht numerisch ist, es mit mysql_real_escape_string()
			// escapen und zwischen " setzen
			elseif(!is_numeric($value)) 
			{
				$value = mysql_real_escape_string($value);
				$value = "\"$value\"";
			}
			// In allen anderen Fällen ist das Value numerisch, kann belassen
			// werden wie es ist und direkt in das "Valuesammelarray" hinzugefügt
			// werden
			$values[] = $value;
		}
		// Aus den Sammelarrays jetzt einen INSERT INTO SQL-Befehl erstellen und diesen
		// an die Ausgabe anhängen
		$sqlstring .= "INSERT INTO $table ( ";
		$sqlstring .= implode(", ",$keys);
		$sqlstring .= 	" )$break\tVALUES ( ";
		$sqlstring .= implode(", ",$values);
		$sqlstring .=  	" );$break";
	}
	// Ausgabestring zurückliefern
    return $sqlstring;
}

// -----------------------------------------------------------------------
function export_database($db, $tables_input = false, $break = "\n")
{
	// MySQL Datenbank auswählen
	mysql_select_db($db) OR die("Fehler beim Auswählen der Datenbank: ".mysql_error());
	// Parameter für Zeilenumbruch kontrollieren, wenn ungültig auf \n setzen
	$break = ($break == "\n" OR $break == "\r\n" OR $break == "\r") ? $break : "\n";
	// Ausgabestring für den Datenbank Dump initialisieren mit einer ersten Kommentarzeile
	$exportstring = "-- -----------------------------------$break";
	// Array für alle zu exportierenden Tabellen initialisieren
	// Jeder Wert in diesem Array wird später als Tabellennamen aufgefasst und es wird
	// versucht einen MySQL DUMP dieser Tabelle zu erstellen
	$tables = array();
	// Wenn für $tables_input ein Array übergeben wurde die Kopfzeile für einen indivi-
	// duellen Datenbankexport erzeugen und alle Einträge aus dem Array in das Tabellen-
	// array kopieren, sodass nur die im Array übergebenen Tabellen exportiert werden
	if(is_array($tables_input))
	{
		$tables = $tables_input;
		$exportstring .= "-- INDIVIDUAL DATABASE EXPORT       --$break";
	}
	// Ansonsten, wenn $tables_input ein einfacher String ist, diesen als eine Tabelle
	// auffassen und nur diese eine Tabelle exportieren, als Single Database Export
	elseif(!is_array($tables_input) AND $tables_input)
	{
		$tables[0] = $tables_input;
		$exportstring .= "-- SINGLE DATABASE EXPORT           --$break";
	}
	// Wurde der Parameter $tables_input gar nicht mit übergeben oder ist dieser False, so
	// wird per get_tables() herausgefunden welche Tabellen alle existieren und es werden
	// alle Tabellen exportiert => Full Database Export
	else
	{
		$tables = get_tables($db);
		$exportstring .= "-- FULL DATABASE EXPORT             --$break";
	}
	// In den Ausgabestring die Kopfzeilen schreiben, welche den Namen der Datenbank in
	// der die Tabellen liegen, das Datum zu dem der Dump erzeugt wurde sowie den Typ des
	// Exports (s.o.) enthält
	$exportstring .= "-- -----------------------------------$break";
	$exportstring .= "-- Database: ".$db.str_repeat(" ",23 - strlen($db))."--$break";
	$exportstring .= "-- Build: ".date("d.m.Y, H:i")."         --$break";
	$exportstring .= "-- Script by Dennis Riehle          --$break";
	$exportstring .= "-- -----------------------------------$break$break";
	// Gehe alle Tabellen in $tables durch und exportiere nacheinander von jeder Tabelle
	// die Struktur, sowie die Daten.
	foreach($tables as $table)
	{
		$exportstring .= "-- Table: $table$break";
		$exportstring .= "-- -----------------------------------$break";
		$exportstring .= export_table_structure($db, $table, true, $break);
		$exportstring .= export_table_data($db, $table, false, $break);
		$exportstring .= "$break";
	}
	// Füge dem Exportstring noch ein Ende Kennzeichen hinzu...
	$exportstring .= "-- -----------------------------------$break";
	$exportstring .= "-- Export End                       --$break";
	$exportstring .= "-- -----------------------------------$break";
	// ...und liefere den kompletten String zurück.
	return $exportstring;
}

// -----------------------------------------------------------------------
/*
	ENDE
*/
?>