<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$uid = $_SESSION['user_id'];
$mesaj = "";

// --- 1. KULLANICI BİLGİLERİNİ ÇEKELİM (Sidebar İçin) ---
$kullanici_sorgu = $db->prepare("SELECT username, isim, profil_foto, sinav_tipi FROM users WHERE id = ?");
$kullanici_sorgu->execute([$uid]);
$user_info = $kullanici_sorgu->fetch(PDO::FETCH_ASSOC);

$gorunen_isim = !empty($user_info['isim']) ? $user_info['isim'] : $user_info['username'];
$pp_yol = ($user_info['profil_foto'] != 'default_pp.png' && file_exists('uploads/' . $user_info['profil_foto'])) 
            ? 'uploads/' . $user_info['profil_foto'] 
            : 'https://ui-avatars.com/api/?name=' . urlencode($gorunen_isim) . '&background=8a2be2&color=fff';

// --- 2. SİLME İŞLEMİ ---
if (isset($_GET['sil_id'])) {
    $sil_id = $_GET['sil_id'];
    
    $kontrol = $db->prepare("SELECT icerik, kategori FROM tekrar_kutusu WHERE id = ? AND user_id = ?");
    $kontrol->execute([$sil_id, $uid]);
    $silinecek = $kontrol->fetch(PDO::FETCH_ASSOC);

    if ($silinecek) {
        if ($silinecek['kategori'] == 'soru') {
            $dosya_yolu = 'uploads/' . $silinecek['icerik'];
            if (file_exists($dosya_yolu)) {
                unlink($dosya_yolu); 
            }
        }
        $sil_sorgu = $db->prepare("DELETE FROM tekrar_kutusu WHERE id = ?");
        $sil_sorgu->execute([$sil_id]);
        
        header("Location: tekrar.php"); 
        exit;
    }
}

// --- 3. YENİ EKLEME İŞLEMİ ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['ekle_btn'])) {
    $ders_adi = $_POST['ders_adi'];
    $kategori = $_POST['kategori'];
    $icerik = "";

    if ($kategori == 'not') {
        $icerik = trim($_POST['not_icerik']);
    } elseif ($kategori == 'soru') {
        if (isset($_FILES['medya']) && $_FILES['medya']['error'] == 0) {
            $dosya_adi = time() . '_' . basename($_FILES['medya']['name']);
            $hedef_yol = 'uploads/' . $dosya_adi;
            if (move_uploaded_file($_FILES['medya']['tmp_name'], $hedef_yol)) {
                $icerik = $dosya_adi;
            }
        }
    }

    if ($icerik != "") {
        $ekle = $db->prepare("INSERT INTO tekrar_kutusu (user_id, ders_adi, kategori, icerik) VALUES (?, ?, ?, ?)");
        if ($ekle->execute([$uid, $ders_adi, $kategori, $icerik])) {
            header("Location: tekrar.php");
            exit;
        }
    }
}

// --- 4. VERİLERİ ÇEKME ---
$sorgu = $db->prepare("SELECT * FROM tekrar_kutusu WHERE user_id = ? ORDER BY olusturma_tarihi DESC");
$sorgu->execute([$uid]);
$tum_icerikler = $sorgu->fetchAll(PDO::FETCH_ASSOC);

// PASTEL RENKLER (Gerçek post-it renkleri)
$pastel_renkler = [
    "Matematik" => "#FFB7B2", // Pastel Pembe
    "Türkçe" => "#AEC6CF",    // Pastel Mavi
    "Tarih" => "#fdfd96",     // Pastel Sarı
    "Coğrafya" => "#B1F2B5",  // Pastel Yeşil
    "Vatandaşlık" => "#CBAACB" // Pastel Mor
];
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tekrar Duvarı - KPSS Yol Arkadaşım</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #0b0b12; color: #fff; margin: 0; display: flex; min-height: 100vh; overflow-x: hidden;}
        
        /* SAAS YAN MENÜ (SIDEBAR) */
        .sidebar { width: 260px; background: rgba(20, 20, 25, 0.95); border-right: 1px solid rgba(255,255,255,0.05); display: flex; flex-direction: column; padding: 30px 0; position: fixed; height: 100vh; z-index: 100; transition: transform 0.3s ease; box-shadow: 5px 0 20px rgba(0,0,0,0.5);}
        .brand { text-align: center; margin-bottom: 25px; font-size: 22px; font-weight: 900; color: #d4b3ff; letter-spacing: 1px; line-height: 1.4;}
        
        /* Profil Widget */
        .user-widget { padding: 15px; margin: 0 20px 30px 20px; background: rgba(0,0,0,0.3); border-radius: 12px; display: flex; align-items: center; gap: 15px; border: 1px solid rgba(255,255,255,0.05); cursor: pointer; transition: 0.3s;}
        .user-widget:hover { background: #8a2be2; border-color: #8a2be2; transform: translateY(-3px);}
        .user-widget img { width: 45px; height: 45px; border-radius: 50%; object-fit: cover; border: 2px solid #8a2be2; }
        .user-info-sidebar { display: flex; flex-direction: column; }
        
        .nav-menu { display: flex; flex-direction: column; gap: 5px; padding: 0 20px; }
        .nav-item { display: flex; align-items: center; gap: 15px; padding: 15px 20px; text-decoration: none; color: #a09eb5; font-size: 15px; font-weight: 600; border-radius: 12px; transition: 0.3s; }
        .nav-item:hover { background: rgba(255,255,255,0.05); color: #fff; transform: translateX(5px); }
        .nav-item.active { background: linear-gradient(90deg, rgba(138,43,226,0.2) 0%, transparent 100%); color: #fff; border-left: 4px solid #8a2be2; }
        .sidebar-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); z-index: 99; backdrop-filter: blur(3px); }

        /* ANA İÇERİK ALANI */
        .main-content { flex: 1; margin-left: 260px; padding: 40px 60px; box-sizing: border-box; width: calc(100% - 260px); }
        
        .hamburger { display: none; background: none; border: none; color: #d4b3ff; font-size: 28px; cursor: pointer; padding: 0; margin-bottom: 20px;}

        .header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 30px; flex-wrap: wrap; gap: 20px;}
        .header-title-area h2 { margin: 0 0 5px 0; font-size: 28px; font-weight: 800; }
        .header-title-area p { margin: 0; color: #a09eb5; font-size: 14px; }

        /* Filtreleme Barları */
        .toolbar { display: flex; flex-direction: column; gap: 15px; width: 100%; margin-bottom: 30px; }
        .toolbar-top { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px; }
        .filters { display: flex; gap: 10px; overflow-x: auto; padding-bottom: 5px; }
        
        .filter-btn, .type-btn { background: rgba(255,255,255,0.05); border: none; color: #a09eb5; padding: 10px 18px; border-radius: 20px; cursor: pointer; font-weight: 600; transition: 0.3s; white-space: nowrap; }
        .filter-btn:hover, .filter-btn.active, .type-btn:hover, .type-btn.active { background: #fff; color: #111; }
        .type-btn.active { background: #8a2be2; color: #fff; } 

        .add-btn { background: linear-gradient(90deg, #6a11cb 0%, #2575fc 100%); color: white; border: none; padding: 12px 25px; border-radius: 25px; font-weight: bold; cursor: pointer; transition: 0.3s; box-shadow: 0 5px 15px rgba(106, 17, 203, 0.4); display: flex; align-items: center; gap: 8px;}
        .add-btn:hover { transform: translateY(-3px); box-shadow: 0 8px 20px rgba(106, 17, 203, 0.6); }

        /* KUTULARIN ÜST ÜSTE BİNMESİNİ ENGELLEYEN YENİ GRID AYARLARI */
        .wall-grid { column-count: 4; column-gap: 25px; padding-top: 25px; width: 100%; }
        .grid-item { 
            break-inside: avoid; 
            page-break-inside: avoid; 
            -webkit-column-break-inside: avoid; 
            margin-bottom: 30px; 
            position: relative; 
            display: inline-block; /* Çakışmayı Kesin Önler */
            width: 100%; 
        }

        /* SİLME BUTONU */
        .delete-btn { position: absolute; top: 8px; right: 8px; background: rgba(0, 0, 0, 0.6); color: white; border: none; border-radius: 50%; width: 28px; height: 28px; display: flex; align-items: center; justify-content: center; font-size: 13px; font-weight: bold; transition: 0.3s; z-index: 10; opacity: 0; cursor: pointer;}
        .grid-item:hover .delete-btn { opacity: 1; }
        .delete-btn:hover { background: #e74c3c; transform: scale(1.1); }

        /* bant (Washi Tape) Efekti */
        .washi-tape {
            position: absolute;
            top: -15px; /* Kutu dışına taşmasın diye ayarlandı */
            left: 50%;
            transform: translateX(-50%) rotate(-3deg);
            width: 70px;
            height: 25px;
            background-color: rgba(255, 255, 255, 0.4);
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            z-index: 5;
            backdrop-filter: blur(2px);
        }

        /* GERÇEKÇİ POST-İT TASARIMI (NOTLAR İÇİN) */
        .post-it { 
            padding: 25px 20px 20px 20px; 
            border-radius: 2px 2px 20px 2px; 
            position: relative; 
            color: #2c3e50; 
            box-shadow: 3px 5px 15px rgba(0,0,0,0.3); 
            transition: 0.3s ease; 
        }
        
        .grid-item:nth-child(even) .post-it { transform: rotate(2deg); }
        .grid-item:nth-child(odd) .post-it { transform: rotate(-1deg); }
        .grid-item:nth-child(3n) .post-it { transform: rotate(3deg); }
        
        .post-it:hover { transform: translateY(-5px) scale(1.02) rotate(0deg); z-index: 10;}
        
        .post-it-text { 
            font-family: 'Comic Sans MS', 'Chalkboard SE', sans-serif; 
            font-size: 15px; 
            line-height: 1.6; 
            font-weight: 600;
            white-space: pre-wrap;
            margin-top: 10px;
        }

        .post-it .lesson-tag {
            font-size: 11px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 1px;
            opacity: 0.6;
            margin-bottom: 5px;
        }

        /* POLAROID TASARIMI (SORULAR İÇİN) */
        .question-card { 
            background: #fff; 
            padding: 12px 12px 25px 12px; 
            border-radius: 4px; 
            box-shadow: 3px 5px 15px rgba(0,0,0,0.3); 
            position: relative;
            transition: 0.3s;
        }
        
        .grid-item:nth-child(even) .question-card { transform: rotate(-2deg); }
        .grid-item:nth-child(odd) .question-card { transform: rotate(1deg); }
        
        .question-card:hover { transform: translateY(-5px) scale(1.02) rotate(0deg); z-index: 10; }
        
        .question-img { 
            width: 100%; 
            height: auto; 
            display: block; 
            cursor: pointer; 
            object-fit: cover; 
            background: #f0f0f0;
            border: 1px solid #eee;
        }
        .polaroid-footer { 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            margin-top: 15px;
        }
        .polaroid-tag { font-size: 13px; font-weight: bold; color: #555; }
        .date-text { font-size: 11px; color: #999; }

        /* YAZISI BÖLÜNMEYEN BOŞ MESAJ STİLİ */
        .empty-message {
            color: #a09eb5; 
            text-align: center; 
            width: 100%; 
            padding: 50px 20px;
            font-size: 16px;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 10px;
        }

        /* MODALLAR */
        .modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.7); backdrop-filter: blur(5px); display: none; justify-content: center; align-items: center; z-index: 1000; }
        .modal-overlay.active { display: flex; }
        .modal-content { background: #11111a; width: 90%; max-width: 500px; border-radius: 20px; padding: 30px; position: relative; border: 1px solid rgba(255,255,255,0.1); box-shadow: 0 25px 50px rgba(0,0,0,0.5);}
        
        .close-btn { position: absolute; top: 20px; right: 20px; background: none; border: none; color: #a09eb5; font-size: 24px; cursor: pointer; transition: 0.2s; }
        .close-btn:hover { color: #fff; }
        
        /* Özel Silme Modalı */
        #customDeleteModal .modal-content { max-width: 380px; text-align: center; padding: 40px 30px; border-top: 5px solid #e74c3c; }
        .delete-icon-bg { width: 60px; height: 60px; background: rgba(231, 76, 60, 0.1); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 30px; margin: 0 auto 20px auto; color: #e74c3c;}
        .btn-group { display: flex; gap: 15px; justify-content: center; margin-top: 25px; }
        .btn-cancel { padding: 12px 25px; border-radius: 8px; border: none; font-weight: bold; cursor: pointer; background: rgba(255,255,255,0.05); color: #a09eb5; transition: 0.3s; }
        .btn-cancel:hover { background: rgba(255,255,255,0.1); color: #fff; }
        .btn-confirm { padding: 12px 25px; border-radius: 8px; border: none; font-weight: bold; cursor: pointer; background: #e74c3c; color: #fff; text-decoration: none; transition: 0.3s; box-shadow: 0 5px 15px rgba(231, 76, 60, 0.4);}
        .btn-confirm:hover { background: #c0392b; transform: translateY(-2px); }

        /* Form Elemanları */
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; color: #a09eb5; font-size: 14px; }
        select, textarea, input[type="file"] { width: 100%; padding: 14px; background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); color: #fff; border-radius: 8px; outline: none; box-sizing: border-box;}
        select option { background-color: #11111a; color: #fff; }
        select:focus, textarea:focus { border-color: #8a2be2; }
        .submit-btn { width: 100%; padding: 15px; background: #8a2be2; color: white; border: none; border-radius: 8px; font-weight: bold; font-size: 16px; cursor: pointer; transition: 0.3s; margin-top: 10px;}
        .submit-btn:hover { background: #6a11cb; }
        #dosya_alani { display: none; }
        
        /* Tam Ekran Fotoğraf */
        .lightbox { position: fixed; top:0; left:0; width:100%; height:100%; background: rgba(0,0,0,0.9); z-index: 2000; display: none; justify-content: center; align-items: center; }
        .lightbox.active { display: flex; }
        .lightbox img { max-width: 90%; max-height: 90%; border-radius: 8px; box-shadow: 0 0 30px rgba(0,0,0,0.8); }
        .lightbox-close { position: absolute; top: 30px; right: 40px; color: white; font-size: 40px; cursor: pointer; }

        @media (max-width: 1200px) { .wall-grid { column-count: 3; } }
        @media (max-width: 900px) { .wall-grid { column-count: 2; } }
        @media (max-width: 768px) {
            .sidebar { transform: translateX(-100%); } 
            .sidebar.active { transform: translateX(0); } 
            .sidebar-overlay.active { display: block; }
            .hamburger { display: block; }
            .main-content { margin-left: 0; width: 100%; padding: 20px; }
            .wall-grid { column-count: 1; }
        }
    </style>
</head>
<body>

    <div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>

    <div class="sidebar" id="sidebar">
        <div class="brand">🚀 KPSS<br>Yol Arkadaşım</div>
        
        <div class="user-widget" onclick="window.location.href='profil.php'">
            <img src="<?= $pp_yol ?>" alt="Profil">
            <div class="user-info-sidebar">
                <span style="font-weight: bold; font-size: 14px; color: #fff;"><?= htmlspecialchars($gorunen_isim); ?></span>
                <span style="font-size: 11px; color: #d4b3ff; margin-top:3px;"><?= htmlspecialchars($user_info['sinav_tipi'] ?? 'Ortaöğretim'); ?></span>
            </div>
        </div>

        <div class="nav-menu">
            <a href="dashboard.php" class="nav-item"><span>🏠</span> Ana Sayfa</a>
            <a href="pomodoro.php" class="nav-item"><span>🍅</span> Pomodoro & Skor</a>
            <a href="tekrar.php" class="nav-item active"><span>⚠️</span> Tekrar Duvarı</a>
            <a href="logout.php" class="nav-item" style="color: #e74c3c; margin-top: 10px;"><span>🚪</span> Çıkış Yap</a>
        </div>
    </div>

    <div class="main-content">
        
        <div class="header">
            <div class="header-title-area">
                <button class="hamburger" onclick="toggleSidebar()">☰</button>
                <div>
                    <h2>⚠️ Tekrar Duvarı</h2>
                    <p>Unutmaman gereken notlar ve yapamadığın sorular.</p>
                </div>
            </div>
        </div>

        <div class="toolbar">
            <div class="toolbar-top">
                <div class="filters">
                    <button class="type-btn active" onclick="filterType('tumu', this)">📚 Tümü</button>
                    <button class="type-btn" onclick="filterType('not', this)">📝 Notlar</button>
                    <button class="type-btn" onclick="filterType('soru', this)">📸 Sorular</button>
                </div>
                <button class="add-btn" onclick="openModal()">+ Yeni Ekle</button>
            </div>
            
            <div class="filters" style="padding-top: 5px; border-top: 1px solid rgba(255,255,255,0.05);">
                <button class="filter-btn active" onclick="filterLesson('tumu', this)">Tüm Dersler</button>
                <button class="filter-btn" onclick="filterLesson('Matematik', this)">Matematik</button>
                <button class="filter-btn" onclick="filterLesson('Türkçe', this)">Türkçe</button>
                <button class="filter-btn" onclick="filterLesson('Tarih', this)">Tarih</button>
                <button class="filter-btn" onclick="filterLesson('Coğrafya', this)">Coğrafya</button>
                <button class="filter-btn" onclick="filterLesson('Vatandaşlık', this)">Vatandaşlık</button>
            </div>
        </div>

        <?php if(empty($tum_icerikler)): ?>
            <div class="empty-message">
                <span style="font-size: 40px; margin-bottom: 10px;">👻</span>
                Henüz hiç içerik yüklemedin. Duvarın bomboş!
            </div>
        <?php else: ?>
            <div class="wall-grid" id="wall">
                <?php foreach($tum_icerikler as $item): 
                    $ders = $item['ders_adi'];
                    $tip = $item['kategori']; 
                    $renk = isset($pastel_renkler[$ders]) ? $pastel_renkler[$ders] : "#fff";
                    $tarih = date('d.m.Y', strtotime($item['olusturma_tarihi']));
                ?>
                    <div class="grid-item <?= $ders ?> <?= $tip ?>">
                        
                        <?php if($tip == 'not'): ?>
                            <div class="post-it" style="background: <?= $renk ?>;">
                                <div class="washi-tape"></div>
                                <button class="delete-btn" onclick="openDeleteModal(<?= $item['id'] ?>)">✕</button>
                                
                                <div class="lesson-tag"><?= $ders ?></div>
                                <div class="post-it-text"><?= nl2br(htmlspecialchars($item['icerik'])) ?></div>
                            </div>
                        
                        <?php elseif($tip == 'soru'): ?>
                            <div class="question-card">
                                <div class="washi-tape"></div>
                                <button class="delete-btn" onclick="openDeleteModal(<?= $item['id'] ?>)">✕</button>
                                <img src="uploads/<?= $item['icerik'] ?>" class="question-img" onclick="openLightbox(this.src)" alt="Soru">
                                <div class="polaroid-footer">
                                    <span class="polaroid-tag" style="color: <?= $renk ?>;"><?= $ders ?></span>
                                    <span class="date-text"><?= $tarih ?></span>
                                </div>
                            </div>
                        <?php endif; ?>

                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <div id="js-empty-msg" class="empty-message" style="display: none;">
            <span style="font-size: 40px; margin-bottom: 10px;">🔍</span>
            Bu filtreye uygun içerik bulunamadı.
        </div>

    </div>

    <div class="modal-overlay" id="addModal">
        <div class="modal-content">
            <button class="close-btn" onclick="closeModal()">✕</button>
            <h2 style="margin-top: 0; color: #fff;">Duvara Ekle</h2>
            
            <form action="tekrar.php" method="POST" enctype="multipart/form-data">
                <div class="form-group">
                    <label>Hangi Ders?</label>
                    <select name="ders_adi" required>
                        <option value="Matematik">Matematik</option>
                        <option value="Türkçe">Türkçe</option>
                        <option value="Tarih">Tarih</option>
                        <option value="Coğrafya">Coğrafya</option>
                        <option value="Vatandaşlık">Vatandaşlık</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Ne Ekleyeceksin?</label>
                    <select name="kategori" id="kategori_secici" onchange="checkTip()" required>
                        <option value="not">📝 Kısa Not / Püf Noktası (Post-it)</option>
                        <option value="soru">📸 Yapamadığım Soru (Fotoğraf)</option>
                    </select>
                </div>

                <div class="form-group" id="metin_alani">
                    <label>Notun:</label>
                    <textarea name="not_icerik" rows="5" placeholder="Örn: Asal sayılarda 2'yi unutma!"></textarea>
                </div>

                <div class="form-group" id="dosya_alani">
                    <label>Fotoğraf Seç:</label>
                    <input type="file" name="medya" accept="image/*">
                </div>

                <button type="submit" name="ekle_btn" class="submit-btn">Duvara Yapıştır</button>
            </form>
        </div>
    </div>

    <div class="modal-overlay" id="customDeleteModal">
        <div class="modal-content">
            <div class="delete-icon-bg">🗑️</div>
            <h2 style="margin-top: 0; color: #e2e8f0;">Silme Onayı</h2>
            <p style="color: #a09eb5; margin-bottom: 10px;">Bu içeriği duvardan tamamen kaldırmak istediğine emin misin?</p>
            
            <div class="btn-group">
                <button class="btn-cancel" onclick="closeDeleteModal()">İptal Et</button>
                <a href="#" id="confirmDeleteLink" class="btn-confirm">Evet, Sil</a>
            </div>
        </div>
    </div>

    <div class="lightbox" id="lightbox" onclick="closeLightbox()">
        <span class="lightbox-close">✕</span>
        <img id="lightbox-img" src="" alt="">
    </div>

    <script>
        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('active');
            document.getElementById('sidebarOverlay').classList.toggle('active');
        }

        function checkTip() {
            var tip = document.getElementById('kategori_secici').value;
            if (tip === 'not') {
                document.getElementById('metin_alani').style.display = 'block';
                document.getElementById('dosya_alani').style.display = 'none';
            } else {
                document.getElementById('metin_alani').style.display = 'none';
                document.getElementById('dosya_alani').style.display = 'block';
            }
        }

        // Modallar
        function openModal() { document.getElementById('addModal').classList.add('active'); }
        function closeModal() { document.getElementById('addModal').classList.remove('active'); }
        
        function openDeleteModal(id) {
            document.getElementById('confirmDeleteLink').href = "tekrar.php?sil_id=" + id;
            document.getElementById('customDeleteModal').classList.add('active');
        }
        function closeDeleteModal() {
            document.getElementById('customDeleteModal').classList.remove('active');
        }

        window.onclick = function(event) {
            if (event.target == document.getElementById('addModal')) closeModal();
            if (event.target == document.getElementById('customDeleteModal')) closeDeleteModal();
        }

        function openLightbox(src) {
            document.getElementById('lightbox-img').src = src;
            document.getElementById('lightbox').classList.add('active');
        }
        function closeLightbox() {
            document.getElementById('lightbox').classList.remove('active');
        }

        // AKILLI FİLTRE SİSTEMİ
        let currentType = 'tumu';
        let currentLesson = 'tumu';

        function filterType(tip, btnElement) {
            document.querySelectorAll('.type-btn').forEach(btn => btn.classList.remove('active'));
            btnElement.classList.add('active');
            currentType = tip;
            applyFilters();
        }

        function filterLesson(ders, btnElement) {
            document.querySelectorAll('.filter-btn').forEach(btn => btn.classList.remove('active'));
            btnElement.classList.add('active');
            currentLesson = ders;
            applyFilters();
        }

        function applyFilters() {
            let items = document.querySelectorAll('.grid-item');
            let visibleCount = 0;
            
            items.forEach(item => {
                let matchType = (currentType === 'tumu') || item.classList.contains(currentType);
                let matchLesson = (currentLesson === 'tumu') || item.classList.contains(currentLesson);
                
                if (matchType && matchLesson) {
                    item.style.display = 'inline-block'; // Sorunu çözen inline-block!
                    visibleCount++;
                } else {
                    item.style.display = 'none';
                }
            });

            // Eğer duvarda hiç öğe kalmadıysa uyarı ver
            let jsEmptyMsg = document.getElementById('js-empty-msg');
            if(items.length > 0) { // Ana veritabanı boş değilse JS uyarısı çalışsın
                jsEmptyMsg.style.display = (visibleCount === 0) ? 'flex' : 'none';
            }
        }
    </script>
</body>
</html>