<?php
/*
 * This will return DB connection, while keeping password for DB out of variables.
 * Of course not in case of Exception ;)
 */

function getDb()
{
    return new PDO('mysql:host=localhost;dbname=wordtool', 'wordtooluser', 'wordtoolpassword');
}


//this var be made global for tuning.
$frequencyLimit = 5;

