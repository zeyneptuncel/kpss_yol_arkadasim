<?php
// Bu örnek bir dosyadır. Kendi sisteminizde kullanmak için 
// bu dosyanın adını 'db.php' olarak değiştirin ve bilgileri doldurun.

$host = 'localhost';
$dbname = 'veritabani_adiniz';
$username = 'kullanici_adiniz';
$password = 'sifreniz_buraya';

try {
    $db = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    // ...