<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$uid = $_SESSION['user_id'];
$mesaj = "";
$mesaj_turu = "";

// --- 1. KULLANICI BİLGİLERİNİ GÜNCELLEME İŞLEMİ ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['guncelle_btn'])) {
    $yeni_isim = trim($_POST['isim']);
    $yeni_username = trim($_POST['username']); 
    $yeni_bio = trim($_POST['bio']);
    $yeni_sinav = $_POST['sinav_tipi'];
    
    if (empty($yeni_username)) { $yeni_username = $_SESSION['username']; }
    
    $kontrol = $db->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
    $kontrol->execute([$yeni_username, $uid]);

    if ($kontrol->rowCount() > 0) {
        $mesaj = "Bu kullanıcı adı maalesef başkası tarafından kullanılıyor. Farklı bir tane dene!";
        $mesaj_turu = "error";
    } else {
        $yeni_pp_adi = "";
        if (isset($_FILES['profil_foto']) && $_FILES['profil_foto']['error'] == 0) {
            $dosya_uzantisi = pathinfo($_FILES['profil_foto']['name'], PATHINFO_EXTENSION);
            $dosya_adi = 'pp_' . $uid . '_' . time() . '.' . $dosya_uzantisi; 
            if (move_uploaded_file($_FILES['profil_foto']['tmp_name'], 'uploads/' . $dosya_adi)) {
                $yeni_pp_adi = $dosya_adi;
            }
        }

        if ($yeni_pp_adi != "") {
            $guncelle = $db->prepare("UPDATE users SET isim=?, username=?, bio=?, sinav_tipi=?, profil_foto=? WHERE id=?");
            $basarili = $guncelle->execute([$yeni_isim, $yeni_username, $yeni_bio, $yeni_sinav, $yeni_pp_adi, $uid]);
        } else {
            $guncelle = $db->prepare("UPDATE users SET isim=?, username=?, bio=?, sinav_tipi=? WHERE id=?");
            $basarili = $guncelle->execute([$yeni_isim, $yeni_username, $yeni_bio, $yeni_sinav, $uid]);
        }

        if ($basarili) {
            $_SESSION['username'] = $yeni_username; $_SESSION['isim'] = $yeni_isim; 
            $mesaj = "Bilgilerin başarıyla güncellendi aşkım!"; $mesaj_turu = "success";
        } else {
            $mesaj = "Güncelleme sırasında bir hata oluştu."; $mesaj_turu = "error";
        }
    }
}

// --- 2. GÜNCEL BİLGİLERİ VE TOPLAM SÜREYİ ÇEKELİM ---
$sorgu = $db->prepare("SELECT * FROM users WHERE id = ?");
$sorgu->execute([$uid]);
$user = $sorgu->fetch(PDO::FETCH_ASSOC);

$sure_sorgu = $db->prepare("SELECT SUM(odak_dakika) as toplam FROM pomodoro_log WHERE user_id = ?");
$sure_sorgu->execute([$uid]);
$toplam_dakika = $sure_sorgu->fetch(PDO::FETCH_ASSOC)['toplam'] ?? 0;

if ($toplam_dakika >= 60) {
    $saat = floor($toplam_dakika / 60);
    $kalan_dk = $toplam_dakika % 60;
    $gosterilecek_sure = $saat . " sa " . ($kalan_dk > 0 ? $kalan_dk . " dk" : "");
} else {
    $gosterilecek_sure = $toplam_dakika . " dk";
}

$gorunen_isim = !empty($user['isim']) ? $user['isim'] : $_SESSION['username'];
$gosterilecek_username = !empty($user['username']) ? $user['username'] : $_SESSION['username'];

$pp_yol = ($user['profil_foto'] != 'default_pp.png' && file_exists('uploads/' . $user['profil_foto'])) 
            ? 'uploads/' . $user['profil_foto'] 
            : 'https://ui-avatars.com/api/?name=' . urlencode($gorunen_isim) . '&background=8a2be2&color=fff';
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profil Ayarları - KPSS Yol Arkadaşım</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #0b0b12; color: #fff; margin: 0; display: flex; min-height: 100vh; overflow-x: hidden;}
        .sidebar { width: 260px; background: rgba(20, 20, 25, 0.95); border-right: 1px solid rgba(255,255,255,0.05); display: flex; flex-direction: column; padding: 30px 0; position: fixed; height: 100vh; z-index: 100; transition: transform 0.3s ease;}
        .brand { text-align: center; margin-bottom: 25px; font-size: 22px; font-weight: 900; color: #d4b3ff; letter-spacing: 1px; line-height: 1.4;}
        .user-widget { padding: 15px; margin: 0 20px 30px 20px; background: linear-gradient(90deg, rgba(138,43,226,0.3) 0%, rgba(138,43,226,0.1) 100%); border-radius: 12px; display: flex; align-items: center; gap: 15px; border: 1px solid #8a2be2; box-shadow: 0 0 15px rgba(138,43,226,0.2);}
        .user-widget img { width: 45px; height: 45px; border-radius: 50%; object-fit: cover; border: 2px solid #d4b3ff; }
        .user-info-sidebar { display: flex; flex-direction: column; }
        .nav-menu { display: flex; flex-direction: column; gap: 5px; padding: 0 20px; }
        .nav-item { display: flex; align-items: center; gap: 15px; padding: 15px 20px; text-decoration: none; color: #a09eb5; font-size: 15px; font-weight: 600; border-radius: 12px; transition: 0.3s; }
        .nav-item:hover { background: rgba(255,255,255,0.05); color: #fff; transform: translateX(5px); }
        .main-content { flex: 1; margin-left: 260px; padding: 50px 80px; box-sizing: border-box; width: calc(100% - 260px); }
        .hamburger { display: none; background: none; border: none; color: #d4b3ff; font-size: 28px; cursor: pointer; padding: 0; margin-bottom: 20px;}
        .profile-card { background: rgba(30, 30, 40, 0.4); border-radius: 24px; border: 1px solid rgba(255,255,255,0.05); padding: 40px; max-width: 900px; display: grid; grid-template-columns: 250px 1fr; gap: 50px; box-shadow: 0 20px 50px rgba(0,0,0,0.3); }
        .pp-upload-area { text-align: center; border-right: 1px solid rgba(255,255,255,0.05); padding-right: 30px; display: flex; flex-direction: column; align-items: center;}
        .pp-preview { width: 180px; height: 180px; border-radius: 50%; object-fit: cover; border: 4px solid #8a2be2; margin-bottom: 15px; }
        
        .stats-container { background: rgba(138, 43, 226, 0.1); border: 1px solid rgba(138, 43, 226, 0.3); padding: 12px; border-radius: 15px; width: 100%; margin-top: 15px; }
        .stats-label { display: block; font-size: 10px; color: #a09eb5; text-transform: uppercase; font-weight: bold; margin-bottom: 4px; }
        .stats-value { font-size: 18px; font-weight: 900; color: #fff; text-shadow: 0 0 10px rgba(138, 43, 226, 0.5); }

        .custom-file-upload { background: rgba(255,255,255,0.05); color: #d4b3ff; padding: 12px 20px; border-radius: 8px; border: 1px dashed #8a2be2; cursor: pointer; display: inline-block; font-size: 13px; width: 100%; box-sizing: border-box; transition: 0.3s; margin-top: 30px;}
        .custom-file-upload:hover { background: rgba(138,43,226,0.1); }
        input[type="file"] { display: none; }

        .alert { padding: 15px 20px; border-radius: 12px; margin-bottom: 25px; font-weight: 600; display: flex; align-items: center; gap: 12px; animation: slideDown 0.4s ease; }
        .alert.success { background: rgba(46, 204, 113, 0.1); color: #2ecc71; border: 1px solid #2ecc71; }
        .alert.error { background: rgba(231, 76, 60, 0.15); color: #ff4757; border: 1px solid #ff4757; box-shadow: 0 0 15px rgba(231,76,60,0.2); }
        @keyframes slideDown { from { transform: translateY(-20px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }

        .form-area h2 { margin-top: 0; font-size: 28px; color: #fff; font-weight: 800; margin-bottom: 30px;}
        .form-group { margin-bottom: 25px; }
        .form-group label { display: block; color: #8892b0; font-size: 13px; margin-bottom: 8px; font-weight: 600; text-transform: uppercase;}
        .form-group input, .form-group select, .form-group textarea { width: 100%; padding: 14px 18px; background: rgba(0,0,0,0.3); border: 1px solid rgba(255,255,255,0.1); border-radius: 12px; color: #fff; font-size: 15px; outline: none; transition: 0.3s; box-sizing: border-box;}
        .save-btn { background: linear-gradient(90deg, #6a11cb 0%, #8a2be2 100%); color: white; border: none; padding: 15px 40px; border-radius: 12px; font-weight: 800; font-size: 16px; cursor: pointer; transition: 0.3s; width: 100%; box-shadow: 0 10px 20px rgba(106, 17, 203, 0.3);}
        .save-btn:hover { transform: translateY(-3px); box-shadow: 0 15px 30px rgba(106, 17, 203, 0.5); }
        .danger-zone { margin-top: 40px; padding: 30px; border: 1px dashed rgba(231, 76, 60, 0.4); border-radius: 20px; background: rgba(231, 76, 60, 0.05); text-align: center; max-width: 900px;}
        .delete-btn { background: transparent; color: #e74c3c; border: 1px solid #e74c3c; padding: 12px 25px; border-radius: 10px; font-weight: bold; cursor: pointer; transition: 0.3s; }
        .delete-btn:hover { background: #e74c3c; color: #fff; }

        /* --- MODAL CSS RESTORED (EKSİKLER TAMAMLANDI) --- */
        .modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.85); backdrop-filter: blur(10px); display: none; justify-content: center; align-items: center; z-index: 1000; }
        .modal-overlay.active { display: flex; }
        .modal-content { background: #1a1a25; padding: 40px; border-radius: 30px; text-align: center; max-width: 400px; border: 1px solid #e74c3c; box-shadow: 0 0 50px rgba(231,76,60,0.2); }
        .modal-icon { font-size: 50px; margin-bottom: 20px; }
        .modal-title { font-size: 24px; color: #fff; margin-bottom: 15px; font-weight: 800; }
        .modal-desc { color: #a09eb5; margin-bottom: 30px; line-height: 1.6; font-size: 15px; }
        .modal-btns { display: flex; gap: 15px; justify-content: center; }
        .btn-cancel { background: rgba(255,255,255,0.05); color: #fff; border: none; padding: 12px 25px; border-radius: 10px; cursor: pointer; font-weight: bold; transition: 0.3s; }
        .btn-confirm-delete { background: #e74c3c; color: #fff; border: none; padding: 12px 25px; border-radius: 10px; cursor: pointer; font-weight: bold; transition: 0.3s; }
        .btn-confirm-delete:hover { transform: scale(1.05); box-shadow: 0 5px 15px rgba(231,76,60,0.4); }

        @media (max-width: 768px) {
            .sidebar { transform: translateX(-100%); } .hamburger { display: block; }
            .main-content { margin-left: 0; width: 100%; padding: 20px; }
            .profile-card { grid-template-columns: 1fr; padding: 25px; }
            .pp-upload-area { border-right: none; border-bottom: 1px solid rgba(255,255,255,0.05); padding-bottom: 30px;}
        }
    </style>
</head>
<body>

    <div class="sidebar" id="sidebar">
        <div class="brand">🚀 KPSS<br>Yol Arkadaşım</div>
        <div class="user-widget">
            <img src="<?= $pp_yol ?>" alt="Profil">
            <div class="user-info-sidebar">
                <span style="font-weight: bold; font-size: 14px; color: #fff;"><?= htmlspecialchars($gorunen_isim); ?></span>
                <span style="font-size: 11px; color: #d4b3ff; margin-top:3px;"><?= htmlspecialchars($user['sinav_tipi'] ?? 'Lisans'); ?></span>
            </div>
        </div>
        <div class="nav-menu">
            <a href="dashboard.php" class="nav-item"><span>🏠</span> Ana Sayfa</a>
            <a href="pomodoro.php" class="nav-item"><span>🍅</span> Pomodoro & Skor</a>
            <a href="tekrar.php" class="nav-item"><span>⚠️</span> Tekrar Duvarı</a>
            <a href="logout.php" class="nav-item" style="color: #e74c3c; margin-top: 10px;"><span>🚪</span> Çıkış Yap</a>
        </div>
    </div>

    <div class="main-content">
        <button class="hamburger" onclick="toggleSidebar()">☰</button>

        <?php if($mesaj != ""): ?>
            <div class="alert <?= $mesaj_turu == 'success' ? 'success' : 'error' ?>"> 
                <?= $mesaj_turu == 'success' ? '✅' : '⚠️' ?> <?= $mesaj ?> 
            </div>
        <?php endif; ?>

        <div class="profile-card">
            <form action="profil.php" method="POST" enctype="multipart/form-data" style="display: contents;">
                <div class="pp-upload-area">
                    <img src="<?= $pp_yol ?>" class="pp-preview" id="img-preview">
                    <h3 style="margin: 0; font-size: 22px;"><?= htmlspecialchars($gorunen_isim); ?></h3>
                    <p style="color: #8a2be2; font-weight: 600; margin: 5px 0;">@<?= htmlspecialchars($gosterilecek_username); ?></p>
                    <label class="custom-file-upload">
                        <input type="file" name="profil_foto" onchange="previewImage(this)" accept="image/*">
                        📸 Fotoğraf Değiştir
                    </label>
                    <div class="stats-container">
                        <span class="stats-label">Toplam Odaklanma</span>
                        <span class="stats-value">⏱️ <?= $gosterilecek_sure ?></span>
                    </div>
                </div>
                <div class="form-area">
                    <h2>Kişisel Bilgiler</h2>
                    <div class="form-group"><label>Tam Adın</label><input type="text" name="isim" value="<?= htmlspecialchars($gorunen_isim); ?>" required></div>
                    <div class="form-group"><label>Kullanıcı Adı</label><input type="text" name="username" value="<?= htmlspecialchars($gosterilecek_username); ?>" required></div>
                    <div class="form-group"><label>Biyografi / Motivasyon</label><textarea name="bio" rows="3"><?= htmlspecialchars($user['bio'] ?? ''); ?></textarea></div>
                    <div class="form-group">
                        <label>Sınav Tipi</label>
                        <select name="sinav_tipi">
                            <option value="Lisans" <?= ($user['sinav_tipi'] == 'Lisans') ? 'selected' : '' ?>>KPSS Lisans</option>
                            <option value="Önlisans" <?= ($user['sinav_tipi'] == 'Önlisans') ? 'selected' : '' ?>>KPSS Önlisans</option>
                            <option value="Ortaöğretim" <?= ($user['sinav_tipi'] == 'Ortaöğretim') ? 'selected' : '' ?>>KPSS Ortaöğretim</option>
                        </select>
                    </div>
                    <button type="submit" name="guncelle_btn" class="save-btn">Değişiklikleri Kaydet</button>
                </div>
            </form>
        </div>

        <div class="danger-zone">
            <h4 style="color: #e74c3c; margin: 0 0 10px 0; font-weight: 800;">Hesabı Kapat</h4>
            <p style="color: #a09eb5; font-size: 14px; margin-bottom: 20px;">Tüm verileriniz <b>kalıcı olarak silinecektir.</b></p>
            <button class="delete-btn" onclick="openDeleteModal()">Hesabımı Sil</button>
        </div>
    </div>

    <div class="modal-overlay" id="deleteModal">
        <div class="modal-content">
            <div class="modal-icon">🥺</div>
            <div class="modal-title">Emin misin <?= htmlspecialchars($gorunen_isim); ?>?</div>
            <div class="modal-desc">Hesabını sildiğinde tüm KPSS emeklerin sonsuza kadar kaybolacak. Gitmeni hiç istemeyiz ama son kararınsa saygı duyarız...</div>
            <div class="modal-btns">
                <button class="btn-cancel" onclick="closeDeleteModal()">Vazgeçtim, Kalıyorum</button>
                <button class="btn-confirm-delete" onclick="window.location.href='hesap_sil.php'">Evet, Sil</button>
            </div>
        </div>
    </div>

    <script>
        function toggleSidebar() { document.getElementById('sidebar').classList.toggle('active'); }
        function previewImage(input) {
            if (input.files && input.files[0]) {
                var reader = new FileReader();
                reader.onload = function(e) { document.getElementById('img-preview').src = e.target.result; }
                reader.readAsDataURL(input.files[0]);
            }
        }
        function openDeleteModal() { document.getElementById('deleteModal').classList.add('active'); }
        function closeDeleteModal() { document.getElementById('deleteModal').classList.remove('active'); }
        window.onclick = function(e) { if (e.target == document.getElementById('deleteModal')) closeDeleteModal(); }
    </script>
</body>
</html>