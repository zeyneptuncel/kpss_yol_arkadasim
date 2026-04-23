<?php
session_start();
require 'db.php';

// Kullanıcı giriş yapmamışsa doğrudan ana sayfaya at
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$uid = $_SESSION['user_id'];

// 1. Varsa kullanıcının yüklediği profil fotoğrafını sunucudan (uploads klasöründen) sil
$sorgu = $db->prepare("SELECT profil_foto FROM users WHERE id = ?");
$sorgu->execute([$uid]);
$user = $sorgu->fetch(PDO::FETCH_ASSOC);

if ($user && !empty($user['profil_foto']) && $user['profil_foto'] != 'default_pp.png') {
    $foto_yolu = 'uploads/' . $user['profil_foto'];
    if (file_exists($foto_yolu)) {
        unlink($foto_yolu); // Dosyayı fiziksel olarak siler
    }
}

// 2. Kullanıcının pomodoro geçmişini sil
$sil_pomodoro = $db->prepare("DELETE FROM pomodoro_log WHERE user_id = ?");
$sil_pomodoro->execute([$uid]);

// 3. Kullanıcının ana hesabını sil
$sil_user = $db->prepare("DELETE FROM users WHERE id = ?");
$sil_user->execute([$uid]);

// 4. Oturumu tamamen kapat ve ana sayfaya yönlendir
session_destroy();
header("Location: index.php");
exit;
?>