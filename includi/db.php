<?php
$host="localhost"; 
$user="root"; 
$pass=""; 
$dbname="torneioldschool";

$conn = new mysqli($host,$user,$pass,$dbname);

if($conn->connect_error) { 
    die("Connessione fallita: ".$conn->connect_error); 
}

$conn->set_charset("utf8mb4");
