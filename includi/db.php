<?php
$host="localhost"; 
$user="torneio2_MarcoLiberti"; 
$pass="MarcoOldSchool"; 
$dbname="torneio2_torneioldschool";

$conn = new mysqli($host,$user,$pass,$dbname);

if($conn->connect_error) { 
    die("Connessione fallita: ".$conn->connect_error); 
}

$conn->set_charset("utf8mb4");
