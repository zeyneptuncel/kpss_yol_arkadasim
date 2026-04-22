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
    
    // Eğer kullanıcı adını yanlışlıkla silip boş gönderdiyse, eski adını koru
    if (empty($yeni_username)) {
        $yeni_username = $_SESSION['username'];
    }
    
    // Kullanıcı adını başkası kullanıyor mu kontrolü (Kendi ID'miz hariç)
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
            $hedef_yol = 'uploads/' . $dosya_adi;

            if (move_uploaded_file($_FILES['profil_foto']['tmp_name'], $hedef_yol)) {
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
            $_SESSION['username'] = $yeni_username; 
            $_SESSION['isim'] = $yeni_isim; 
            $mesaj = "Bilgilerin başarıyla güncellendi aşkım!";
            $mesaj_turu = "success";
        } else {
            $mesaj = "Güncelleme sırasında bir hata oluştu.";
            $mesaj_turu = "error";
        }
    }
}

// --- 2. GÜNCEL BİLGİLERİ ÇEKELİM ---
$sorgu = $db->prepare("SELECT * FROM users WHERE id = ?");
$sorgu->execute([$uid]);
$user = $sorgu->fetch(PDO::FETCH_ASSOC);

// GARANTİLİ VERİLER
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
        
        /* SIDEBAR */
        .sidebar { width: 260px; background: rgba(20, 20, 25, 0.95); border-right: 1px solid rgba(255,255,255,0.05); display: flex; flex-direction: column; padding: 30px 0; position: fixed; height: 100vh; z-index: 100; transition: transform 0.3s ease;}
        .brand { text-align: center; margin-bottom: 25px; font-size: 22px; font-weight: 900; color: #d4b3ff; letter-spacing: 1px; line-height: 1.4;}
        
        /* Aktif profil widget efekti */
        .user-widget { padding: 15px; margin: 0 20px 30px 20px; background: linear-gradient(90deg, rgba(138,43,226,0.3) 0%, rgba(138,43,226,0.1) 100%); border-radius: 12px; display: flex; align-items: center; gap: 15px; border: 1px solid #8a2be2; cursor: default; box-shadow: 0 0 15px rgba(138,43,226,0.2);}
        .user-widget img { width: 45px; height: 45px; border-radius: 50%; object-fit: cover; border: 2px solid #d4b3ff; }
        .user-info-sidebar { display: flex; flex-direction: column; }
        
        .nav-menu { display: flex; flex-direction: column; gap: 5px; padding: 0 20px; }
        .nav-item { display: flex; align-items: center; gap: 15px; padding: 15px 20px; text-decoration: none; color: #a09eb5; font-size: 15px; font-weight: 600; border-radius: 12px; transition: 0.3s; }
        .nav-item:hover { background: rgba(255,255,255,0.05); color: #fff; transform: translateX(5px); }

        /* MOBİL İÇİN KARARTMA EKRANI */
        .sidebar-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); z-index: 99; backdrop-filter: blur(3px); }

        /* ANA İÇERİK */
        .main-content { flex: 1; margin-left: 260px; padding: 50px 80px; box-sizing: border-box; width: calc(100% - 260px); }
        
        /* HAMBURGER MENÜ BUTONU */
        .hamburger { display: none; background: none; border: none; color: #d4b3ff; font-size: 28px; cursor: pointer; padding: 0; margin-bottom: 20px;}

        .profile-card { background: rgba(30, 30, 40, 0.4); border-radius: 24px; border: 1px solid rgba(255,255,255,0.05); padding: 40px; max-width: 900px; display: grid; grid-template-columns: 250px 1fr; gap: 50px; box-shadow: 0 20px 50px rgba(0,0,0,0.3); }

        .pp-upload-area { text-align: center; border-right: 1px solid rgba(255,255,255,0.05); padding-right: 30px;}
        .pp-preview { width: 180px; height: 180px; border-radius: 50%; object-fit: cover; border: 4px solid #8a2be2; box-shadow: 0 0 30px rgba(138,43,226,0.3); margin-bottom: 15px; }
        
        .profile-name-display { font-size: 22px; font-weight: 800; color: #fff; margin: 0 0 5px 0; }
        .profile-username-display { font-size: 15px; color: #a09eb5; font-weight: 600; margin: 0 0 25px 0; display: flex; justify-content: center; align-items: center; gap: 5px;}
        .profile-username-display span { color: #8a2be2; }

        .custom-file-upload { background: rgba(255,255,255,0.05); color: #d4b3ff; padding: 12px 20px; border-radius: 8px; border: 1px dashed #8a2be2; cursor: pointer; display: inline-block; font-size: 13px; transition: 0.3s; width: 100%; box-sizing: border-box;}
        .custom-file-upload:hover { background: rgba(138,43,226,0.1); }
        input[type="file"] { display: none; }

        .form-area h2 { margin-top: 0; font-size: 28px; color: #fff; font-weight: 800; margin-bottom: 30px;}
        .form-group { margin-bottom: 25px; }
        .form-group label { display: block; color: #8892b0; font-size: 13px; margin-bottom: 8px; font-weight: 600; text-transform: uppercase; letter-spacing: 1px;}
        
        .input-wrapper { position: relative; display: flex; align-items: center; }
        .input-icon { position: absolute; left: 15px; color: #8a2be2; font-weight: bold; font-size: 16px;}
        .has-icon { padding-left: 40px !important; }

        .form-group input, .form-group select, .form-group textarea { width: 100%; padding: 14px 18px; background: rgba(0,0,0,0.3); border: 1px solid rgba(255,255,255,0.1); border-radius: 12px; color: #fff; font-size: 15px; outline: none; transition: 0.3s; box-sizing: border-box;}
        .form-group input:focus, .form-group select:focus, .form-group textarea:focus { border-color: #8a2be2; box-shadow: 0 0 10px rgba(138,43,226,0.2); background: rgba(0,0,0,0.5); }
        .form-group select option { background-color: #1a0b2e; color: #fff; }
        
        .save-btn { background: linear-gradient(90deg, #6a11cb 0%, #8a2be2 100%); color: white; border: none; padding: 15px 40px; border-radius: 12px; font-weight: 800; font-size: 16px; cursor: pointer; transition: 0.3s; box-shadow: 0 10px 20px rgba(106, 17, 203, 0.3); width: 100%; margin-top: 10px;}
        .save-btn:hover { transform: translateY(-3px); box-shadow: 0 15px 30px rgba(106, 17, 203, 0.5); }

        .alert { padding: 15px 20px; border-radius: 12px; margin-bottom: 25px; font-weight: 600; display: flex; align-items: center; gap: 10px;}
        .alert.success { background: rgba(46, 204, 113, 0.1); color: #2ecc71; border: 1px solid #2ecc71; }
        .alert.error { background: rgba(231, 76, 60, 0.1); color: #e74c3c; border: 1px solid #e74c3c; }

        @media (max-width: 768px) {
            .sidebar { transform: translateX(-100%); } 
            .sidebar.active { transform: translateX(0); } 
            .sidebar-overlay.active { display: block; }
            
            .hamburger { display: block; }
            
            .main-content { margin-left: 0; width: 100%; padding: 20px; }
            .profile-card { grid-template-columns: 1fr; padding: 25px; text-align: center; gap: 30px;}
            .pp-upload-area { border-right: none; padding-right: 0; border-bottom: 1px solid rgba(255,255,255,0.05); padding-bottom: 30px;}
            .form-area { text-align: left; }
        }
    </style>
</head>
<body>

    <div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>

    <div class="sidebar" id="sidebar">
        <div class="brand">🚀 KPSS<br>Yol Arkadaşım</div>
        
        <div class="user-widget">
            <img src="<?= $pp_yol ?>" alt="Profil">
            <div class="user-info-sidebar">
                <span style="font-weight: bold; font-size: 14px; color: #fff;"><?= htmlspecialchars($gorunen_isim); ?></span>
                <span style="font-size: 11px; color: #d4b3ff; margin-top:3px;"><?= htmlspecialchars($user['sinav_tipi'] ?? 'Ortaöğretim'); ?></span>
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
            <div class="alert <?= $mesaj_turu ?>">
                <?= $mesaj_turu == 'success' ? '✅' : '⚠️' ?> <?= $mesaj ?>
            </div>
        <?php endif; ?>

        <div class="profile-card">
            
            <form action="profil.php" method="POST" enctype="multipart/form-data" style="display: contents;">
                <div class="pp-upload-area">
                    <img src="<?= $pp_yol ?>" class="pp-preview" id="img-preview">
                    
                    <h3 class="profile-name-display"><?= htmlspecialchars($gorunen_isim); ?></h3>
                    <p class="profile-username-display"><span>@</span><?= htmlspecialchars($gosterilecek_username); ?></p>

                    <label class="custom-file-upload">
                        <input type="file" name="profil_foto" onchange="previewImage(this)" accept="image/*">
                        📸 Yeni Fotoğraf Seç
                    </label>
                </div>

                <div class="form-area">
                    <h2>Kişisel Bilgiler</h2>
                    
                    <div class="form-group">
                        <label>Tam Adın (Ekranda Görünen)</label>
                        <input type="text" name="isim" value="<?= htmlspecialchars($gorunen_isim); ?>" placeholder="Ekranda ne yazsın?" required>
                    </div>

                    <div class="form-group">
                        <label>Kullanıcı Adı (Sisteme Giriş İçin)</label>
                        <div class="input-wrapper">
                            <span class="input-icon">@</span>
                            <input type="text" name="username" class="has-icon" value="<?= htmlspecialchars($gosterilecek_username); ?>" placeholder="Kullanıcı adın" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Biyografi / Motivasyon Sözün</label>
                        <textarea name="bio" rows="3" placeholder="Seni ne gaza getirir?"><?= htmlspecialchars($user['bio'] ?? ''); ?></textarea>
                    </div>

                    <div class="form-group">
                        <label>Hazırlandığın Sınav</label>
                        <select name="sinav_tipi">
                            <option value="Lisans" <?= (isset($user['sinav_tipi']) && $user['sinav_tipi'] == 'Lisans') ? 'selected' : '' ?>>KPSS Lisans</option>
                            <option value="Önlisans" <?= (isset($user['sinav_tipi']) && $user['sinav_tipi'] == 'Önlisans') ? 'selected' : '' ?>>KPSS Önlisans</option>
                            <option value="Ortaöğretim" <?= (isset($user['sinav_tipi']) && $user['sinav_tipi'] == 'Ortaöğretim') ? 'selected' : '' ?>>KPSS Ortaöğretim</option>
                        </select>
                    </div>

                    <button type="submit" name="guncelle_btn" class="save-btn">Değişiklikleri Kaydet</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('active');
            document.getElementById('sidebarOverlay').classList.toggle('active');
        }

        function previewImage(input) {
            if (input.files && input.files[0]) {
                var reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('img-preview').src = e.target.result;
                }
                reader.readAsDataURL(input.files[0]);
            }
        }
    </script>
</body>
</html>