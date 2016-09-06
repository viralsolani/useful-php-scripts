<?php
// Report all errors
ini_set('display_errors',1);

// Set database parameters
define("DB_HOST", 'localhost');
define("DB_USER", 'root');
define("DB_PASSWORD", 'root');

// set output directory
$outputDir = '/var/www/html/backup/'.time();
$old_umask = umask(0);
mkdir($outputDir, 0777);
umask($old_umask);


list_database(DB_HOST, DB_USER, DB_PASSWORD,$outputDir);

/**
 * fetch all the available database
 *
 * @param  $host
 * @param  $user
 * @param  $pass
 * @param  $outputDir
 * @return mix
 */
function list_database($host,$user,$pass,$outputDir)
{
    $link = mysql_connect($host,$user,$pass);
    $result = mysql_query(' select s.SCHEMA_NAME, group_concat(t.TABLE_NAME)
                            FROM information_schema.SCHEMATA s
                            LEFT JOIN information_schema.TABLES t on s.SCHEMA_NAME=t.TABLE_SCHEMA
                            GROUP BY s.SCHEMA_NAME'
                        );

    while($row = mysql_fetch_row($result))
    {
        $database[] = $row[0];
    }

    foreach ($database as $name)
    {
        if($name!="mysql" && $name !='phpmyadmin' && $name!='information_schema')
        {
            backup_tables($host,$user,$pass,$name,$outputDir);
        }
    }
}

/**
 * Backup all the table of the database
 *
 * @param  $host
 * @param  $user
 * @param  $pass
 * @param  $name
 * @param  $outputDir
 * @param  $tables
 * @return mix
 */
function backup_tables($host,$user,$pass,$name,$outputDir,$tables = '*')
{

    $link = mysql_connect($host,$user,$pass);
    mysql_select_db($name,$link);

    //get all of the tables
    if($tables == '*')
    {
        $tables = array();
        $result = mysql_query('SHOW TABLES');
        while($row = mysql_fetch_row($result))
        {
            $tables[] = $row[0];
        }
    }
    else
    {
        $tables = is_array($tables) ? $tables : explode(',',$tables);
    }
    $return = '';
    //cycle through
    foreach($tables as $table)
    {
        $result = mysql_query('SELECT * FROM '.$table);
        $num_fields = mysql_num_fields($result);

        $row2 = mysql_fetch_row(mysql_query('SHOW CREATE TABLE '.$table));
        $return .= "\n\n".$row2[1].";\n\n";

        for ($i = 0; $i < $num_fields; $i++)
        {
            while($row = mysql_fetch_row($result))
            {
                $return.= 'INSERT INTO '.$table.' VALUES(';
                for($j=0; $j < $num_fields; $j++)
                {
                    $row[$j] = addslashes($row[$j]);
                    $row[$j] = ereg_replace("\n","\\n",$row[$j]);
                    if (isset($row[$j])) { $return.= '"'.$row[$j].'"' ; } else { $return.= '""'; }
                    if ($j < ($num_fields-1)) { $return.= ','; }
                }
                $return.= ");\n";
            }
        }
        $return.="\n\n\n";
    }

    //save file
    $handle = fopen($outputDir.'/'.$name.'-db-backup-'.time().'-'.(md5(implode(',',$tables))).'.sql','w+');
    fwrite($handle,$return);
    fclose($handle);
}