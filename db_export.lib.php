<?php

// ============================================
// Konfiguration
// ============================================

// Mysql Zugangsdaten definieren
define('MYSQL_HOST',     'localhost' );
define('MYSQL_USER',     'root'      );
define('MYSQL_PASS',     ''          );
define('MYSQL_DATABASE', 'testdb'    );

// Soll eine Backup Datei angelegt werden?
$make_backup = true; 

// Wenn ja, wie lautet der Pfad zum Backupordner (mit Slash am Ende)?
$backuppath = "../backup/";


// ============================================
// Script
// ============================================

//Verbindung herstellen und Datenbank auswählen
mysql_connect(MYSQL_HOST, MYSQL_USER, MYSQL_PASS) OR die("Bei dem Verbindungsaufbau mit der Datenbank ist ein Fehler aufgetreten.<br>MySQL hat folgende Fehlermeldung ausgegeben: <tt>".mysql_error()."</tt><br>Bitte probieren Sie es später noch einmal.");
mysql_select_db(MYSQL_DATABASE) OR die("Die Verbindung mit der Datenbank konnte zwar hergestellt werden, jedoch gab es Probleme beim Auswählen der Datenbank.<br>MySQL hat folgende Fehlermeldung ausgegeben: <tt>".mysql_error()."</tt><br>Bitte Probieren Sie es später noch einmal.");

//Settings aus der DB laden:
$sql = "SELECT
            name,
            wert
        FROM
            settings
        ";
$tmp_return = mysql_query($sql) OR die(mysql_error());

//Arrays initialisieren
$_tmp_settings = array();
$_settings = array();

//Alle Daten auslesen und als name => wert in $_settings ablegen
while($_data = mysql_fetch_assoc($tmp_return))
{
    $_tmp_settings[] = $_data;
}
foreach($_tmp_settings as $arr => $set)
{
    $_settings[ $set['name'] ] = $set['wert'];
}

//Temponäre Daten löschen
mysql_free_result($tmp_return);
unset($tmp_return);
unset($_tmp_settings);

$tables = explode(",",$_settings['backup_tables']);
$tmp_left_out = explode(",",$_settings['backup_left_out']);
$left_out = array();
foreach($tmp_left_out as $value)
{
	$left_out[$value] = true;
}
$make_truncate = $_settings['backup_make_truncate'];

$strings[0] = "###############################################################\n#\n# Backup der Tabellen:\n# ";
foreach($tables as $table) $strings[0] .= $table . ", ";
$strings[0] .= "\n# Stand: ".date("Y-m-d, h:i:s")."\n#\n###############################################################\n";

foreach($tables as $table)
{
	$sql = "SELECT 
				*
			FROM
				$table
			";
	$return = mysql_query($sql);
	
	$strings[] = "\n# ----------------------------------------\n# BACKUP TABELLE $table\n# ----------------------------------------";
	if(!$return) 
	{
		$strings[] = "# Es ist ein Fehler aufgetreten (MySQL): \n# ".mysql_error();
		continue;
	}
	if($make_truncate)
	{
		$strings[] = "TRUNCATE TABLE $table;";
	}
	
	while($_data = mysql_fetch_assoc($return))
	{
		$keys = array();
		$values = array();
		
		foreach($_data as $key => $value)
		{
			if(isset($left_out[$table.".".$key])) continue;
			$keys[] = $key;
			if(!$value) $value = "NULL";
			elseif(!is_numeric($value)) 
			{
				$value = addslashes($value);
				$value = "\"$value\"";
			}
			$values[] = $value;
		}
		$strings[] = "INSERT INTO $table ( ".implode(", ",$keys).")\n     VALUES ( ".implode(", ",$values).");";
	}
}

$backup = implode("\r\n\r\n",$strings);

//Wenn ein Backup gemacht werden soll
if($make_backup)
{
	$filepath = $backuppath.MYSQL_DATABASE."_d".date("Ymd")."_t".date("hi").".txt";
	
	if(!$fp = @fopen($filepath,"w")) die("<b>Fatal Error:</b> Die Datei $filepath konnte entweder nicht angelegt oder für den Schreibvorgang geöffnet werden.");	
	$ok = @flock($fp, LOCK_EX);
	
	for($x = 0; $x <= 5; $x++)
	{
		if($ok) break;
		$ok = @flock($fp, LOCK_EX);
	}
	
	if(!$ok) die("<b>Fatal Error:</b> Die Datei $filepath konnte nicht für den Schreibvorgang gesperrt werden.");
	
	if(!@fwrite($fp, $backup, strlen($backup))) die("<b>Fatal Error:</b> Das Backup konnte nicht in die Datei $filepath geschrieben werden.");
	
	fclose($fp);
}

// ============================================
// Ende des Scriptes, es folgt die Ausgabe
// ============================================
?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">

<html>
<head>
	<title>Backup der Datenbank <?php echo MYSQL_DATABASE; ?></title>
</head>

<body>

<?php

echo "<h2>Backup erfolgreich</h2\n";

if($make_backup)
{
	echo "<p>Das nachfolgende Backup wurde erfolgreich in die Datei <b>$filepath</b> geschrieben und steht jetzt für weitere Verwendung zur Verfügung.</p>\n";
}
else
{
	echo "<p>Das Backup wird nun nachfolgend angezeigt:</p>\n";
}
echo "<hr>\n";

echo "<pre>";
echo stripslashes($backup)."<br>";
echo "</pre>";

?>

</body>
</html>
