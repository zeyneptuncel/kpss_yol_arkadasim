<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$uid = $_SESSION['user_id'];

// --- MANUEL ÇALIŞMA SERİSİ EKLEME/SİLME İŞLEMİ (GÜNCELLENDİ) ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['streak_date']) && isset($_POST['streak_action'])) {
    $date = $_POST['streak_date'];
    $action = $_POST['streak_action']; // 'add' veya 'remove'
    
    // Güvenlik: Sadece "bugün" için işlem yapılabilir (Sunucu taraflı kontrol)
    if ($date == date('Y-m-d')) {
        if ($action === 'add') {
            $check = $db->prepare("SELECT id FROM activity_log WHERE user_id = ? AND activity_date = ?");
            $check->execute([$uid, $date]);
            if ($check->rowCount() == 0) {
                $insert = $db->prepare("INSERT INTO activity_log (user_id, activity_date) VALUES (?, ?)");
                $insert->execute([$uid, $date]);
            }
        } elseif ($action === 'remove') {
            $delete = $db->prepare("DELETE FROM activity_log WHERE user_id = ? AND activity_date = ?");
            $delete->execute([$uid, $date]);
        }
    }
    header("Location: dashboard.php");
    exit;
}

// KULLANICI BİLGİLERİNİ ÇEKELİM
$kullanici_sorgu = $db->prepare("SELECT username, isim, profil_foto, bio, sinav_tipi FROM users WHERE id = ?");
$kullanici_sorgu->execute([$uid]);
$user_info = $kullanici_sorgu->fetch(PDO::FETCH_ASSOC);

$gorunen_isim = !empty($user_info['isim']) ? $user_info['isim'] : $user_info['username'];
$pp_yol = ($user_info['profil_foto'] != 'default_pp.png' && file_exists('uploads/' . $user_info['profil_foto'])) 
            ? 'uploads/' . $user_info['profil_foto'] 
            : 'https://ui-avatars.com/api/?name=' . urlencode($gorunen_isim) . '&background=8a2be2&color=fff';

// --- MOTİVASYON SÖZLERİ SİSTEMİ ---
$motivasyon_sozleri = [
    ["soz" => "Başarı, her gün tekrarlanan küçük çabaların toplamıdır.", "yazar" => "Robert Collier"],
    ["soz" => "Gelecek, bugünden hazırlananlara aittir.", "yazar" => "Malcolm X"],
    ["soz" => "Zorluklar, başarının değerini artıran süslerdir.", "yazar" => "Moliere"],
    ["soz" => "Hiç kimse başarı merdivenlerini elleri cebinde tırmanmamıştır.", "yazar" => "Konfüçyüs"],
    ["soz" => "Yarınlar yorgun ve bezgin kimselere değil, rahatını terk edebilen gayretli insanlara aittir.", "yazar" => "Cicero"],
    ["soz" => "Sadece çok ileri gitme riskini alanlar ne kadar ileri gidebileceğini görür.", "yazar" => "T.S. Eliot"],
    ["soz" => "En büyük zayıflığımız pes etmektir. Başarmanın en kesin yolu her zaman bir kez daha denemektir.", "yazar" => "Thomas Edison"],
    ["soz" => "Yapabileceğinize inanın, yolun yarısını çoktan geçtiniz demektir.", "yazar" => "Theodore Roosevelt"],
    ["soz" => "Okumak bir insanı doldurur, konuşmak hazırlar, yazmak ise olgunlaştırır.", "yazar" => "Francis Bacon"],
    ["soz" => "Hayatını bugün değiştir. Gelecek üzerine kumar oynama, ertelemeden şimdi harekete geç.", "yazar" => "Simone de Beauvoir"],
    ["soz" => "Disiplin, hedefler ile başarı arasındaki köprüdür.", "yazar" => "Jim Rohn"],
    ["soz" => "Bilgiye yapılan yatırım en yüksek faizi getirir.", "yazar" => "Benjamin Franklin"],
    ["soz" => "Yorulunca dinlenmeyi öğren, bırakmayı değil.", "yazar" => "Banksy"],
    ["soz" => "Dahilik, %1 yetenek ve %99 alın terinden oluşur.", "yazar" => "Thomas Edison"],
    ["soz" => "Başkalarından daha akıllı olmak zorunda değilsiniz, başkalarından daha disiplinli olmanız yeterli.", "yazar" => "Warren Buffett"]
];

$gunun_indeksi = date('z') % count($motivasyon_sozleri); 
$secilen_soz = $motivasyon_sozleri[$gunun_indeksi];

// SINAV TARİHİ
$sinav_tarihleri = [
    "Lisans" => "2026-09-06 10:15:00",
    "Önlisans" => "2026-10-04 10:15:00",
    "Ortaöğretim" => "2026-10-25 10:15:00"
];
$hedef_tarih = isset($sinav_tarihleri[$user_info['sinav_tipi']]) ? $sinav_tarihleri[$user_info['sinav_tipi']] : "2026-10-25 10:15:00";

// İlerleme ve Aktivite Çekme
$stmt = $db->prepare("SELECT lesson_name, topic_name, task_name, is_completed FROM progress WHERE user_id = ?");
$stmt->execute([$uid]);
$db_progress = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt_act = $db->prepare("SELECT activity_date FROM activity_log WHERE user_id = ? AND activity_date >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)");
$stmt_act->execute([$uid]);
$db_activity = $stmt_act->fetchAll(PDO::FETCH_COLUMN);

// KPSS Müfredatı
$kpss_mufredat = [
    "Matematik" => [ "renk" => "linear-gradient(135deg, #ff416c, #ff4b2b)", "gölge" => "rgba(255, 65, 108, 0.4)", "konular" => ["İşlem Yeteneği", "Temel Kavramlar", "Ardışık Sayılar", "Sayı Basamakları", "Asal Sayı - Faktöriyel", "Bölme", "Bölünebilme", "OBEB - OKEK", "Rasyonel - Ondalık Sayılar", "Üslü Sayılar", "Köklü Sayılar", "Basit Eşitsizlikler", "Mutlak Değer", "Çarpanlara Ayırma", "Oran - Orantı", "Sayı ve Kesir Problemleri", "İşçi ve Havuz Problemleri", "Yaş Problemleri", "Yüzde ve Kâr - Zarar Problemleri", "Karışım Problemleri", "Hız Problemleri", "Küme Problemleri - İşlem - Mod", "Modüler Aritmetik", "Fonksiyon ve Diziler", "Grafik Problemleri", "Permütasyon - Kombinasyon", "Olasılık", "Sayısal Mantık"]],
    "Türkçe" => [ "renk" => "linear-gradient(135deg, #2193b0, #6dd5ed)", "gölge" => "rgba(33, 147, 176, 0.4)", "konular" => ["Ses Bilgisi", "Yazım Kuralları", "Noktalama İşaretleri", "Sözcükte Yapı", "Sözcük Türleri", "Fiil, Fiilimsi, Ek Fiil, Fiilde Çatı", "Cümlenin Ögeleri - Cümle Türleri", "Anlatım Bozuklukları", "Sözcükte Anlam", "Cümlede Anlam", "Paragrafta Anlam", "Paragraf Oluşturma - Paragrafta Yer Değiştirme"]],
    "Tarih" => [ "renk" => "linear-gradient(135deg, #f2994a, #f2c94c)", "gölge" => "rgba(242, 201, 76, 0.4)", "konular" => ["İlk ve Orta Çağlarda Türk Dünyası", "Türklerin İslamiyet'i Kabulü", "Yerleşme ve Devletleşme", "Beylikten Devlete Osmanlı", "Dünya Gücü Osmanlı (1453 - 1595)", "Değişen Dünya Dengeleri", "En Uzun Yüzyıl (Dağılma)", "XX. Yüzyıl Başlarında Osmanlı", "Milli Mücadele Hazırlık", "I. TBMM Dönemi", "Kurtuluş Savaşı Muharebeler", "Atatürk İlke ve İnkılapları", "Atatürk Dönemi Dış Politika", "XX. Yüzyıl Başında Dünya", "II. Dünya Savaşı", "Soğuk Savaş", "Yumuşama Dönemi", "Küreselleşen Dünya"]],
    "Coğrafya" => [ "renk" => "linear-gradient(135deg, #11998e, #38ef7d)", "gölge" => "rgba(56, 239, 125, 0.4)", "konular" => ["Türkiye'nin Coğrafi Konumu", "Komşular ve Sorunlar", "Yeryüzü Şekilleri", "Dağlar", "Dış Kuvvetler", "Depremler", "Akarsular", "Göller", "Kıyı Tipleri", "Kütle Hareketleri", "Dalga ve Akıntılar", "Karstik Şekiller", "Yeraltı Suları", "İklim", "Nüfus ve Yerleşme", "Tarım", "Hayvancılık", "Ormancılık", "Enerji Kaynakları", "Madenler", "Sanayi", "Ticaret", "Turizm", "Ulaşım", "Bölgesel Projeler"]],
    "Vatandaşlık" => [ "renk" => "linear-gradient(135deg, #8e2de2, #4a00e0)", "gölge" => "rgba(142, 45, 226, 0.4)", "konular" => ["Temel Hukuk Bilgisi", "Anayasa Hukukuna Giriş", "Temel Hak ve Ödevler", "Yasama", "Yürütme", "Yargı", "İdare Hukuku", "İnsan Hakları Hukuku"]]
];
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ana Sayfa - KPSS Yol Arkadaşım</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #0b0b12; color: #fff; margin: 0; display: flex; min-height: 100vh; overflow-x: hidden;}
        
        /* SAAS YAN MENÜ (SIDEBAR) */
        .sidebar { width: 260px; background: rgba(20, 20, 25, 0.95); border-right: 1px solid rgba(255,255,255,0.05); display: flex; flex-direction: column; padding: 30px 0; position: fixed; height: 100vh; z-index: 100; box-shadow: 5px 0 20px rgba(0,0,0,0.5); transition: transform 0.3s ease;}
        .brand { text-align: center; margin-bottom: 25px; font-size: 22px; font-weight: 900; color: #d4b3ff; letter-spacing: 1px; padding: 0 10px; line-height: 1.4;}
        
        /* PROFİL WIDGET'I (SİYAH VE ÜSTÜNE GELİNCE MOR) */
        .user-widget { padding: 15px; margin: 0 20px 30px 20px; background: rgba(138,43,226,0.1); border-radius: 12px; display: flex; align-items: center; gap: 15px; border: 1px solid #8a2be2; cursor: pointer; transition: 0.3s;}
        .user-widget:hover { background: #8a2be2; border-color: #8a2be2; transform: translateY(-3px);}
        .user-widget img { width: 45px; height: 45px; border-radius: 50%; object-fit: cover; border: 2px solid #8a2be2; transition: 0.3s; }
        .user-widget:hover img { border-color: #fff; }
        .user-info-sidebar { display: flex; flex-direction: column; }
        .u-name { font-weight: bold; font-size: 14px; color: #fff; }
        .u-badge { font-size: 11px; color: #d4b3ff; margin-top: 3px;}

        .nav-menu { display: flex; flex-direction: column; gap: 5px; padding: 0 20px; }
        .nav-item { display: flex; align-items: center; gap: 15px; padding: 15px 20px; text-decoration: none; color: #a09eb5; font-size: 15px; font-weight: 600; border-radius: 12px; transition: all 0.3s ease; }
        .nav-item:hover { background: rgba(255,255,255,0.05); color: #fff; transform: translateX(5px); }
        .nav-item.active { background: linear-gradient(90deg, rgba(138,43,226,0.2) 0%, transparent 100%); color: #fff; border-left: 4px solid #8a2be2; }

        .quote-container { background: rgba(138, 43, 226, 0.05); border-left: 4px solid #8a2be2; padding: 20px; border-radius: 0 15px 15px 0; margin-bottom: 30px; position: relative; }
        .quote-text { font-size: 17px; font-weight: 500; color: #e2e8f0; font-style: italic; margin-bottom: 8px; line-height: 1.5; }
        .quote-author { font-size: 13px; color: #8a2be2; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; }
        .quote-icon { position: absolute; right: 20px; bottom: 10px; font-size: 40px; opacity: 0.1; transform: rotate(15deg); }

        .sidebar-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); z-index: 99; backdrop-filter: blur(3px); }

        .main-content { flex: 1; margin-left: 260px; padding: 30px 40px; width: calc(100% - 260px); box-sizing: border-box;}

        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; }
        .header-title-area { display: flex; align-items: center; gap: 15px; }
        .header h2 { margin: 0; font-size: 26px; font-weight: 800; }
        
        .hamburger { display: none; background: none; border: none; color: #d4b3ff; font-size: 28px; cursor: pointer; padding: 0; }

        .top-grid { display: grid; grid-template-columns: 1.2fr 1fr 1.5fr; gap: 20px; margin-bottom: 40px; }
        .panel { background: rgba(30, 30, 40, 0.4); padding: 25px; border-radius: 20px; border: 1px solid rgba(255,255,255,0.05); box-shadow: 0 10px 30px rgba(0,0,0,0.2); display: flex; flex-direction: column; justify-content: center;}
        .panel h3 { margin-top: 0; font-size: 14px; color: #8892b0; text-transform: uppercase; letter-spacing: 1.5px; margin-bottom: 15px;}

        /* Takvim */
        .days-wrapper { display: flex; justify-content: space-between; }
        .day-box { display: flex; flex-direction: column; align-items: center; gap: 8px; opacity: 0.3; transition: 0.3s; cursor: pointer; }
        .day-box:hover { opacity: 1; transform: translateY(-3px); }
        .day-box.worked { opacity: 1; transform: scale(1.05); }
        .day-box.today { transform: scale(1.1); opacity: 0.8; }
        .day-box.today:hover { opacity: 1; }
        .day-circle { width: 32px; height: 32px; border-radius: 50%; background: rgba(255,255,255,0.05); display: flex; align-items: center; justify-content: center; font-size: 12px; border: 2px solid transparent; transition: 0.3s;}
        .day-box:hover .day-circle { border-color: rgba(255,255,255,0.2); }
        .day-box.worked .day-circle { background: rgba(46, 204, 113, 0.2); border-color: #2ecc71; color: #2ecc71; box-shadow: 0 0 10px rgba(46, 204, 113, 0.3); }
        .day-box.today .day-circle { border-color: #8a2be2; box-shadow: 0 0 15px rgba(138,43,226,0.4); } 
        .day-name { font-size: 11px; color: #a09eb5; }

        /* Dinamik Sayaç Paneli */
        .countdown-panel { background: linear-gradient(135deg, rgba(30, 25, 45, 0.8), rgba(10, 10, 15, 0.9)); border: 1px solid #8a2be2; position: relative; overflow: hidden;}
        .countdown-panel::before { content: ''; position: absolute; top: -50%; left: -50%; width: 200%; height: 200%; background: radial-gradient(circle, rgba(138,43,226,0.1) 0%, transparent 70%); animation: pulse 4s infinite; pointer-events: none;}
        @keyframes pulse { 0% { transform: scale(1); opacity: 0.5; } 50% { transform: scale(1.1); opacity: 1; } 100% { transform: scale(1); opacity: 0.5; } }
        
        .timer-wrapper { display: flex; justify-content: space-between; text-align: center; gap: 8px; z-index: 1; position: relative;}
        .time-box { background: rgba(0,0,0,0.5); padding: 12px 5px; border-radius: 12px; flex: 1; border: 1px solid rgba(255,255,255,0.05); }
        .time-num { font-size: 24px; font-weight: 900; color: #fff; text-shadow: 0 0 10px #8a2be2; display: block;}
        .time-label { font-size: 10px; color: #a09eb5; text-transform: uppercase; letter-spacing: 1px; margin-top: 5px; display: block;}

        .motivational-text { font-size: 15px; font-weight: 500; margin-bottom: 15px; color: #e2e8f0; font-style: italic;}
        .main-progress-bg { width: 100%; height: 30px; background: rgba(0,0,0,0.5); border-radius: 15px; position: relative; border: 1px solid rgba(255,255,255,0.1); overflow: hidden; }
        .main-progress-fill { height: 100%; width: 0%; background: linear-gradient(90deg, #6a11cb 0%, #2575fc 100%); transition: width 0.8s ease; }
        .main-progress-text { position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); font-weight: 800; font-size: 15px; color: #fff; z-index: 2;}

        .lessons-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 25px; }
        .lesson-card { background: rgba(30, 25, 45, 0.4); border-radius: 20px; padding: 25px; cursor: pointer; transition: all 0.3s ease; position: relative; border: 1px solid rgba(255,255,255,0.05); }
        .lesson-card:hover { transform: translateY(-5px); background: rgba(40, 35, 60, 0.8); }
        .card-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; }
        .card-title { font-size: 22px; font-weight: 800; margin: 0; }
        .card-icon { width: 50px; height: 50px; border-radius: 14px; display: flex; align-items: center; justify-content: center; font-size: 24px; box-shadow: inset 0 0 10px rgba(0,0,0,0.2);}
        .card-progress-bg { width: 100%; height: 8px; background: rgba(0,0,0,0.5); border-radius: 4px; overflow: hidden; }
        .card-progress-fill { height: 100%; width: 0%; border-radius: 4px; transition: width 0.5s ease; }
        .card-percent-text { text-align: right; font-size: 13px; margin-top: 10px; color: #8892b0; font-weight: 600;}

        /* Modal Stilleri */
        .modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.7); backdrop-filter: blur(8px); display: none; justify-content: center; align-items: center; z-index: 1000; opacity: 0; transition: opacity 0.3s; }
        .modal-overlay.active { display: flex; opacity: 1; }
        .modal-content { background: #11111a; width: 90%; max-width: 650px; max-height: 85vh; border-radius: 20px; padding: 35px; overflow-y: auto; position: relative; border: 1px solid rgba(255,255,255,0.1); }
        .close-btn { position: absolute; top: 25px; right: 25px; background: rgba(255,255,255,0.05); border: none; color: #a09eb5; font-size: 24px; width: 40px; height: 40px; border-radius: 50%; cursor: pointer; transition: 0.2s; display: flex; align-items: center; justify-content: center;}
        .close-btn:hover { background: rgba(231, 76, 60, 0.2); color: #ff7675; }

        /* Yeni Özel Pop-up Butonları */
        .btn-group { display: flex; gap: 15px; justify-content: center; margin-top: 25px; }
        .btn-modal-cancel { padding: 12px 25px; border-radius: 8px; border: none; font-weight: bold; cursor: pointer; background: rgba(255,255,255,0.05); color: #a09eb5; transition: 0.3s; }
        .btn-modal-cancel:hover { background: rgba(255,255,255,0.1); color: #fff; }
        .btn-modal-confirm { padding: 12px 25px; border-radius: 8px; border: none; font-weight: bold; cursor: pointer; color: #fff; transition: 0.3s; }
        .btn-modal-confirm:hover { transform: translateY(-2px); }

        .modal-title { font-size: 28px; margin-top: 0; margin-bottom: 5px; font-weight: 800; }
        .modal-subtitle { font-size: 14px; color: #8892b0; margin-bottom: 25px; padding-bottom: 20px; border-bottom: 1px solid rgba(255,255,255,0.05); }
        .topic-btn { background: rgba(255,255,255,0.02); color: #e2e8f0; cursor: pointer; padding: 18px 20px; width: 100%; text-align: left; border: 1px solid rgba(255,255,255,0.03); outline: none; transition: all 0.3s ease; border-radius: 12px; font-size: 16px; display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; font-weight: 600; }
        .topic-btn:hover { background: rgba(255,255,255,0.05); border-color: rgba(255,255,255,0.1);}
        .topic-status { font-size: 12px; padding: 4px 10px; border-radius: 20px; background: rgba(0,0,0,0.5); color: #8892b0; }
        .topic-btn.fully-completed { background: rgba(46, 204, 113, 0.1) !important; border: 1px solid rgba(46, 204, 113, 0.3) !important; color: #55efc4 !important; box-shadow: inset 0 0 15px rgba(46, 204, 113, 0.05); }
        .topic-btn.fully-completed .topic-status { background: rgba(46, 204, 113, 0.2); color: #55efc4; border: 1px solid rgba(46, 204, 113, 0.3); font-weight: bold; }
        .tasks-container { display: none; padding: 20px; background: rgba(0,0,0,0.3); border-radius: 12px; margin-bottom: 15px; }
        .checklist-item { display: flex; align-items: center; margin: 12px 0; font-size: 15px; color: #a09eb5; cursor: pointer; transition: 0.2s; padding: 8px; border-radius: 8px; }
        .checklist-item:hover { background: rgba(255,255,255,0.02); color: #fff; }
        .checklist-item.completed { color: #fff; opacity: 0.8;}
        .custom-checkbox { margin-right: 15px; width: 22px; height: 22px; cursor: pointer; accent-color: currentColor; }

        @media (max-width: 1024px) { .top-grid { grid-template-columns: 1fr 1fr; } }
        @media (max-width: 768px) {
            .sidebar { transform: translateX(-100%); } 
            .sidebar.active { transform: translateX(0); } 
            .sidebar-overlay.active { display: block; }
            .main-content { margin-left: 0; width: 100%; padding: 20px 15px; }
            .hamburger { display: block; }
            .top-grid { grid-template-columns: 1fr; gap: 15px; margin-bottom: 25px;}
            .lessons-grid { grid-template-columns: 1fr; }
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
                <span style="font-size: 11px; color: #d4b3ff; margin-top:3px;"><?= htmlspecialchars($user['sinav_tipi'] ?? 'Ortaöğretim'); ?></span>
            </div>
        </div>

        <div class="nav-menu">
            <a href="dashboard.php" class="nav-item active"><span>🏠</span> Ana Sayfa</a>
            <a href="pomodoro.php" class="nav-item"><span>🍅</span> Pomodoro & Skor</a>
            <a href="tekrar.php" class="nav-item"><span>⚠️</span> Tekrar Duvarı</a>
            <a href="logout.php" class="nav-item" style="color: #e74c3c; margin-top: 10px;"><span>🚪</span> Çıkış Yap</a>
        </div>
    </div>

    <div class="main-content">
        
        <div class="header">
            <div class="header-title-area">
                <button class="hamburger" onclick="toggleSidebar()">☰</button>
                <h2>Hoş Geldin, <?= htmlspecialchars($gorunen_isim); ?>! 👋</h2>
            </div>
        </div>

        <div class="quote-container">
            <div class="quote-text">"<?= $secilen_soz['soz'] ?>"</div>
            <div class="quote-author">— <?= $secilen_soz['yazar'] ?></div>
            <div class="quote-icon">❝</div>
        </div>

        <div class="top-grid">
            <div class="panel">
                <h3>📅 Çalışma Serisi</h3>
                <div class="days-wrapper" id="calendar-wrapper"></div>
            </div>

            <div class="panel countdown-panel">
                <h3 style="color: #d4b3ff;"><span style="color:#fff;">🎯</span> <?= htmlspecialchars($user_info['sinav_tipi']); ?>'ne Kalan</h3>
                <div class="timer-wrapper" id="countdown-timer">
                    <div class="time-box"><span class="time-num" id="cd-days">00</span><span class="time-label">Gün</span></div>
                    <div class="time-box"><span class="time-num" id="cd-hours">00</span><span class="time-label">Saat</span></div>
                    <div class="time-box"><span class="time-num" id="cd-mins">00</span><span class="time-label">Dk</span></div>
                    <div class="time-box"><span class="time-num" id="cd-secs">00</span><span class="time-label">Sn</span></div>
                </div>
            </div>

            <div class="panel">
                <h3>📈 Genel İlerleme</h3>
                <div class="motivational-text" id="motivation-msg">Masanın başına geçtin, tarih yazma vakti!</div>
                <div class="main-progress-bg">
                    <div class="main-progress-fill" id="main-progress-bar"></div>
                    <div class="main-progress-text" id="main-progress-text">%0 Tamamlandı</div>
                </div>
            </div>
        </div>

        <div class="lessons-grid">
            <?php foreach($kpss_mufredat as $ders => $detay): ?>
                <div class="lesson-card" onclick="openModal('<?= $ders ?>', '<?= $detay['renk'] ?>')">
                    <div class="card-header">
                        <h3 class="card-title" style="background: <?= $detay['renk'] ?>; -webkit-background-clip: text; -webkit-text-fill-color: transparent;"><?= $ders ?></h3>
                        <div class="card-icon" style="background: <?= $detay['renk'] ?>;">📚</div>
                    </div>
                    <div class="card-progress-bg">
                        <div class="card-progress-fill" id="bar-<?= $ders ?>" style="background: <?= $detay['renk'] ?>; box-shadow: 0 0 10px <?= $detay['gölge'] ?>;"></div>
                    </div>
                    <div class="card-percent-text" id="text-<?= $ders ?>">%0 Tamamlandı</div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="modal-overlay" id="lessonModal">
        <div class="modal-content">
            <button class="close-btn" onclick="closeModal('lessonModal')">✕</button>
            <h2 class="modal-title" id="modalTitle">Ders Adı</h2>
            <div class="modal-subtitle" id="modalSubtitle">Görevlerini tamamla ve ilerlemeni kaydet.</div>
            <div id="modalTopics"></div>
        </div>
    </div>

    <div class="modal-overlay" id="streakModal">
        <div class="modal-content" style="max-width: 400px; text-align: center;">
            <button class="close-btn" onclick="closeModal('streakModal')">✕</button>
            <div id="streak-icon" style="font-size: 50px; margin-bottom: 15px;">🔥</div>
            <h2 id="streak-title" style="margin-top: 0; font-weight: 800;">Başlık</h2>
            <p id="streak-desc" style="color: #a09eb5; margin-bottom: 25px; line-height: 1.5;">Açıklama</p>
            <div id="streak-buttons" class="btn-group">
                </div>
        </div>
    </div>

    <form id="streak-form" action="dashboard.php" method="POST" style="display: none;">
        <input type="hidden" name="streak_date" id="streak-date-input">
        <input type="hidden" name="streak_action" id="streak-action-input" value="add">
    </form>

    <script>
        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('active');
            document.getElementById('sidebarOverlay').classList.toggle('active');
        }

        // SAYAÇ
        const targetDate = new Date("<?= $hedef_tarih ?>").getTime();
        const countdownTimer = setInterval(function() {
            const now = new Date().getTime();
            const distance = targetDate - now;
            if (distance < 0) {
                clearInterval(countdownTimer);
                document.getElementById("countdown-timer").innerHTML = "<h3 style='color:#2ecc71; margin:0 auto;'>Sınav Günü Geldi! Başarılar!</h3>";
                return;
            }
            document.getElementById("cd-days").innerText = Math.floor(distance / (1000 * 60 * 60 * 24)).toString().padStart(2, '0');
            document.getElementById("cd-hours").innerText = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60)).toString().padStart(2, '0');
            document.getElementById("cd-mins").innerText = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60)).toString().padStart(2, '0');
            document.getElementById("cd-secs").innerText = Math.floor((distance % (1000 * 60)) / 1000).toString().padStart(2, '0');
        }, 1000);

        // --- GELİŞTİRİLMİŞ TAKVİM SİSTEMİ ---
        const dbProgress = <?= json_encode($db_progress) ?>;
        const activeDates = <?= json_encode($db_activity) ?>;
        const mufredat = <?= json_encode($kpss_mufredat) ?>;
        
        function generateCalendar() {
            const wrapper = document.getElementById('calendar-wrapper');
            const today = new Date(); 
            // Yerel saat dilimi ayarı (Gece yarısı hatalarını engeller)
            const offset = today.getTimezoneOffset() * 60000;
            const todayStr = (new Date(today - offset)).toISOString().split('T')[0];
            const days = ['Paz', 'Pzt', 'Sal', 'Çar', 'Per', 'Cum', 'Cmt'];
            
            for(let i = 6; i >= 0; i--) {
                let d = new Date(); 
                d.setDate(today.getDate() - i);
                let offsetD = d.getTimezoneOffset() * 60000;
                let dateStr = (new Date(d - offsetD)).toISOString().split('T')[0];
                
                let isToday = (dateStr === todayStr); 
                let isWorked = activeDates.includes(dateStr);
                let icon = isWorked ? '🔥' : (isToday ? '🔥' : '—');
                
                // MANTIK: Sadece Şık Pop-up Açılır (Alert YOK)
                let clickAction = '';
                if (!isToday && !isWorked) {
                    clickAction = `onclick="openStreakModal('past', '${dateStr}')"`;
                } else if (!isToday && isWorked) {
                    clickAction = `onclick="openStreakModal('past_worked', '${dateStr}')"`;
                } else if (isToday && !isWorked) {
                    clickAction = `onclick="openStreakModal('ask', '${dateStr}')"`;
                } else if (isToday && isWorked) {
                    clickAction = `onclick="openStreakModal('undo', '${dateStr}')"`;
                }
                
                wrapper.innerHTML += `<div class="day-box ${isToday ? 'today' : ''} ${isWorked ? 'worked' : ''}" id="day-${dateStr}" ${clickAction}><div class="day-circle">${icon}</div><span class="day-name">${days[d.getDay()]}</span></div>`;
            }
        }
        
        // ŞIK POP-UP (MODAL) YÖNETİCİSİ
        function openStreakModal(type, dateStr) {
            const title = document.getElementById('streak-title');
            const desc = document.getElementById('streak-desc');
            const icon = document.getElementById('streak-icon');
            const btns = document.getElementById('streak-buttons');
            
            document.getElementById('streak-date-input').value = dateStr;
            
            if (type === 'past' || type === 'past_worked') {
                icon.innerText = type === 'past_worked' ? '🏆' : '⏳';
                title.innerText = type === 'past_worked' ? 'Harika Bir Gündü' : 'Geçmişe Dönülemez';
                title.style.color = '#fff';
                desc.innerText = type === 'past_worked' ? 'O gün hedeflerini tamamlamışsın. Geçmiş günlerde değişiklik yapılamaz.' : 'Sadece bugünün çalışma durumunu işaretleyebilirsin. Geçmiş geçmişte kaldı, biz önümüze bakalım!';
                btns.innerHTML = `<button class="btn-modal-cancel" onclick="closeModal('streakModal')">Anladım</button>`;
            
            } else if (type === 'ask') {
                icon.innerText = '🎯';
                title.innerText = 'Bugün Çalıştın Mı?';
                title.style.color = '#fff';
                desc.innerText = 'Günün görevlerini yerine getirdiysen seriye bir ateş daha ekleyelim!';
                btns.innerHTML = `
                    <button class="btn-modal-cancel" onclick="closeModal('streakModal')">Henüz Değil</button>
                    <button class="btn-modal-confirm" style="background:#2ecc71; box-shadow: 0 5px 15px rgba(46, 204, 113, 0.4);" onclick="submitStreak('add')">Evet, Çalıştım 🔥</button>
                `;
            
            } else if (type === 'undo') {
                icon.innerText = '🔥';
                title.innerText = 'Bugün Zaten Çalıştın!';
                title.style.color = '#2ecc71';
                desc.innerText = 'Bugünün hakkını verdin, seri devam ediyor! Eğer yanlışlıkla tıkladıysan işlemi iptal edebilirsin.';
                btns.innerHTML = `
                    <button class="btn-modal-cancel" onclick="closeModal('streakModal')">Kapat</button>
                    <button class="btn-modal-confirm" style="background:#e74c3c; box-shadow: 0 5px 15px rgba(231, 76, 60, 0.4);" onclick="submitStreak('remove')">Seriyi İptal Et</button>
                `;
            }
            
            document.getElementById('streakModal').classList.add('active');
        }

        // Form Gönderme
        function submitStreak(action) {
            document.getElementById('streak-action-input').value = action;
            document.getElementById('streak-form').submit();
        }
        
        generateCalendar();

        let progressData = {}; let totalTasksOverall = 0;
        for (let ders in mufredat) { progressData[ders] = { renk: mufredat[ders].renk, konular: [] }; mufredat[ders].konular.forEach(k => { progressData[ders].konular.push({ ad: k, tasks: [false, false, false] }); totalTasksOverall += 3; }); }
        dbProgress.forEach(kayit => { if(progressData[kayit.lesson_name]) { let kObj = progressData[kayit.lesson_name].konular.find(k => k.ad === kayit.topic_name); if(kObj) kObj.tasks[parseInt(kayit.task_name)] = (kayit.is_completed == 1); } });

        const motivasyonMesajlari = { "0-5": "Masanın başına geçtin, şimdi ilk adımı atma zamanı!", "6-25": "Isınma turları bitti! Ritmimizi bulmaya başlıyoruz.", "26-50": "Yolun yarısına yaklaşıyorsun, istikrarını bozma!", "51-75": "Harika bir ilerleme! Rakiplerine fark atıyorsun.", "76-100": "Sen artık bir ustasın! Sınavı parçalamaya hazırsın." };
        function getMotivasyon(yuzde) { if(yuzde <= 5) return motivasyonMesajlari["0-5"]; if(yuzde <= 25) return motivasyonMesajlari["6-25"]; if(yuzde <= 50) return motivasyonMesajlari["26-50"]; if(yuzde <= 75) return motivasyonMesajlari["51-75"]; return motivasyonMesajlari["76-100"]; }

        function updateAllBars() {
            let totalCompletedOverall = 0;
            for (let ders in progressData) {
                let dersCompleted = 0; progressData[ders].konular.forEach(k => k.tasks.forEach(t => { if(t) dersCompleted++; }));
                totalCompletedOverall += dersCompleted;
                let yuzde = (progressData[ders].konular.length * 3) > 0 ? Math.round((dersCompleted / (progressData[ders].konular.length * 3)) * 100) : 0;
                document.getElementById(`bar-${ders}`).style.width = yuzde + '%'; document.getElementById(`text-${ders}`).innerText = `%${yuzde} Tamamlandı`;
            }
            let genelYuzde = Math.round((totalCompletedOverall / totalTasksOverall) * 100);
            document.getElementById('main-progress-bar').style.width = genelYuzde + '%'; document.getElementById('main-progress-text').innerText = `%${genelYuzde} Tamamlandı`; document.getElementById('motivation-msg').innerText = getMotivasyon(genelYuzde);
        }

        function handleCheck(cb, dersAd, kIndex, tIndex) {
            let isChecked = cb.checked; progressData[dersAd].konular[kIndex].tasks[tIndex] = isChecked;
            if(isChecked) cb.parentElement.classList.add('completed'); else cb.parentElement.classList.remove('completed');
            let tBtn = cb.parentElement.parentElement.previousElementSibling; let sSpan = tBtn.querySelector('.topic-status');
            let completed = progressData[dersAd].konular[kIndex].tasks.filter(t => t).length;
            sSpan.innerText = `${completed}/3 Görev`;
            if (completed === 3) { tBtn.classList.add('fully-completed'); sSpan.innerHTML = `✅ 3/3 Tamam!`; } else { tBtn.classList.remove('fully-completed'); }
            updateAllBars(); 
            fetch('api.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ action: 'update_progress', lesson: dersAd, topic: progressData[dersAd].konular[kIndex].ad, task: tIndex, status: isChecked }) }).then(res => res.json()).then(data => { if(isChecked) { let d = new Date(); let ts = `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`; let tb = document.getElementById('day-' + ts); if(tb && !tb.classList.contains('worked')) { tb.classList.add('worked'); tb.querySelector('.day-circle').innerText = '🔥'; } } });
        }

        function openModal(ders, renk) {
            document.getElementById('modalTitle').innerText = ders; document.getElementById('modalTitle').style.background = renk; document.getElementById('modalTitle').style.webkitBackgroundClip = "text"; document.getElementById('modalTitle').style.webkitTextFillColor = "transparent";
            let mTopics = document.getElementById('modalTopics'); mTopics.innerHTML = '';
            progressData[ders].konular.forEach((konu, kIndex) => {
                let completed = konu.tasks.filter(t => t).length; let fully = (completed === 3) ? 'fully-completed' : ''; let sText = (completed === 3) ? '✅ 3/3 Tamam!' : `${completed}/3 Görev`;
                mTopics.innerHTML += `<button class="topic-btn ${fully}" onclick="toggleAccordion(this)"><span>${konu.ad}</span><span class="topic-status">${sText}</span></button><div class="tasks-container" style="border-left: 3px solid ${renk.match(/#([a-zA-Z0-9]{6})/)[0]};"><label class="checklist-item ${konu.tasks[0] ? 'completed' : ''}"><input type="checkbox" class="custom-checkbox" style="color: ${renk.match(/#([a-zA-Z0-9]{6})/)[0]}" onchange="handleCheck(this, '${ders}', ${kIndex}, 0)" ${konu.tasks[0] ? 'checked' : ''}><span class="task-icon">📖</span> Konu anlatımı izlendi / çalışıldı</label><label class="checklist-item ${konu.tasks[1] ? 'completed' : ''}"><input type="checkbox" class="custom-checkbox" style="color: ${renk.match(/#([a-zA-Z0-9]{6})/)[0]}" onchange="handleCheck(this, '${ders}', ${kIndex}, 1)" ${konu.tasks[1] ? 'checked' : ''}><span class="task-icon">✍️</span> En az 30-50 soru çözüldü</label><label class="checklist-item ${konu.tasks[2] ? 'completed' : ''}"><input type="checkbox" class="custom-checkbox" style="color: ${renk.match(/#([a-zA-Z0-9]{6})/)[0]}" onchange="handleCheck(this, '${ders}', ${kIndex}, 2)" ${konu.tasks[2] ? 'checked' : ''}><span class="task-icon">🎯</span> Çıkmış sorular tarandı</label></div>`;
            });
            document.getElementById('lessonModal').classList.add('active');
        }

        function closeModal(modalId) { document.getElementById(modalId).classList.remove('active'); }
        function toggleAccordion(btn) { let panel = btn.nextElementSibling; panel.style.display = (panel.style.display === "block") ? "none" : "block"; }
        
        window.onclick = function(event) { 
            if (event.target.classList.contains('modal-overlay')) {
                event.target.classList.remove('active'); 
            }
        }
        
        updateAllBars();
    </script>
</body>
</html>