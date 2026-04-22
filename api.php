<?php
session_start();
require 'db.php';
header('Content-Type: application/json');

// JavaScript'ten gelen veriyi al
$data = json_decode(file_get_contents('php://input'), true);

if (isset($data['action']) && $data['action'] == 'update_progress' && isset($_SESSION['user_id'])) {
    $uid = $_SESSION['user_id'];
    $lesson = $data['lesson'];
    $topic = $data['topic'];
    $task = $data['task']; // 0, 1 veya 2
    $status = $data['status'] ? 1 : 0;

    // 1. Bu görevin daha önce kaydı var mı kontrol et
    $check = $db->prepare("SELECT id FROM progress WHERE user_id=? AND lesson_name=? AND topic_name=? AND task_name=?");
    $check->execute([$uid, $lesson, $topic, $task]);
    
    if ($check->rowCount() > 0) {
        // Varsa güncelle
        $update = $db->prepare("UPDATE progress SET is_completed=? WHERE user_id=? AND lesson_name=? AND topic_name=? AND task_name=?");
        $update->execute([$status, $uid, $lesson, $topic, $task]);
    } else {
        // Yoksa yeni kayıt ekle
        $insert = $db->prepare("INSERT INTO progress (user_id, lesson_name, topic_name, task_name, is_completed) VALUES (?, ?, ?, ?, ?)");
        $insert->execute([$uid, $lesson, $topic, $task, $status]);
    }

    // 2. GERÇEK SERİ (STREAK) SİSTEMİ
    // Eğer Zeynep bir şeye "Tamamlandı (1)" olarak tik attıysa, bugünü aktif günlere ekle.
    if ($status == 1) {
        $today = date('Y-m-d');
        $actCheck = $db->prepare("SELECT id FROM activity_log WHERE user_id=? AND activity_date=?");
        $actCheck->execute([$uid, $today]);
        
        if ($actCheck->rowCount() == 0) {
            $actInsert = $db->prepare("INSERT INTO activity_log (user_id, activity_date) VALUES (?, ?)");
            $actInsert->execute([$uid, $today]);
        }
    }
    
    echo json_encode(["success" => true]);
}
?>