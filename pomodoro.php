<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$uid = $_SESSION['user_id'];

// --- 1. ÇALIŞILAN DAKİKALARI VERİTABANINA KAYDETME ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['kaydedilecek_dakika'])) {
    $dakika = (int)$_POST['kaydedilecek_dakika'];
    if ($dakika > 0) {
        $ekle = $db->prepare("INSERT INTO pomodoro_log (user_id, odak_dakika, calisma_tarihi) VALUES (?, ?, CURDATE())");
        $ekle->execute([$uid, $dakika]);
    }
    header("Location: pomodoro.php");
    exit;
}

// --- 2. KULLANICI BİLGİLERİNİ ÇEKELİM (Sidebar için) ---
$sorgu = $db->prepare("SELECT username, isim, profil_foto, sinav_tipi FROM users WHERE id = ?");
$sorgu->execute([$uid]);
$user = $sorgu->fetch(PDO::FETCH_ASSOC);

$gorunen_isim = !empty($user['isim']) ? $user['isim'] : $user['username'];
$pp_yol = ($user['profil_foto'] != 'default_pp.png' && file_exists('uploads/' . $user['profil_foto'])) 
            ? 'uploads/' . $user['profil_foto'] 
            : 'https://ui-avatars.com/api/?name=' . urlencode($gorunen_isim) . '&background=8a2be2&color=fff';

// --- 3. HAFTALIK LİDERLİK TABLOSU ---
$skor_sorgu = $db->query("
    SELECT u.isim, u.username, u.profil_foto, SUM(p.odak_dakika) as toplam_dakika 
    FROM pomodoro_log p 
    JOIN users u ON p.user_id = u.id 
    WHERE YEARWEEK(p.calisma_tarihi, 1) = YEARWEEK(CURDATE(), 1)
    GROUP BY p.user_id 
    ORDER BY toplam_dakika DESC 
    LIMIT 10
");
$liderlik_tablosu = $skor_sorgu->fetchAll(PDO::FETCH_ASSOC);

$benim_skorum = 0;
foreach ($liderlik_tablosu as $kisi) {
    if ($kisi['username'] == $_SESSION['username']) {
        $benim_skorum = $kisi['toplam_dakika'];
        break;
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pomodoro & Skor - KPSS Yol Arkadaşım</title>
    <style>
        /* GENEL AYARLAR */
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #0b0b12; color: #fff; margin: 0; display: flex; min-height: 100vh; overflow-x: hidden; }
        
        /* YAN MENÜ (SIDEBAR) */
        .sidebar { width: 260px; background: rgba(20, 20, 25, 0.95); border-right: 1px solid rgba(255,255,255,0.05); display: flex; flex-direction: column; padding: 30px 0; position: fixed; height: 100vh; z-index: 100; transition: 0.3s ease; }
        .brand { text-align: center; margin-bottom: 25px; font-size: 22px; font-weight: 900; color: #d4b3ff; letter-spacing: 1px; line-height: 1.4; }
        
        /* Profil Widget */
        .user-widget { padding: 15px; margin: 0 20px 30px 20px; background: rgba(138,43,226,0.1); border-radius: 12px; display: flex; align-items: center; gap: 15px; border: 1px solid #8a2be2; cursor: pointer; transition: 0.3s;}
        .user-widget:hover { background: #8a2be2; border-color: #8a2be2; transform: translateY(-3px); }
        .user-widget img { width: 45px; height: 45px; border-radius: 50%; object-fit: cover; border: 2px solid #fff; }
        .user-info-sidebar { display: flex; flex-direction: column; }
        
        /* Menü Linkleri */
        .nav-menu { display: flex; flex-direction: column; gap: 5px; padding: 0 20px; }
        .nav-item { display: flex; align-items: center; gap: 15px; padding: 15px 20px; text-decoration: none; color: #a09eb5; font-size: 15px; font-weight: 600; border-radius: 12px; transition: 0.3s; }
        .nav-item:hover { background: rgba(255,255,255,0.05); color: #fff; transform: translateX(5px); }
        .nav-item.active { background: linear-gradient(90deg, rgba(138,43,226,0.2) 0%, transparent 100%); color: #fff; border-left: 4px solid #8a2be2; }
        
        .sidebar-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); z-index: 99; backdrop-filter: blur(3px); }

        /* ANA İÇERİK */
        .main-content { flex: 1; margin-left: 260px; padding: 40px 50px; box-sizing: border-box; width: calc(100% - 260px); }
        .header { margin-bottom: 30px; display: flex; justify-content: space-between; align-items: center; }
        .header h2 { margin: 0; font-size: 28px; font-weight: 800; }
        .header p { color: #a09eb5; margin-top: 5px; font-size: 14px; }
        .hamburger { display: none; background: none; border: none; color: #d4b3ff; font-size: 28px; cursor: pointer; padding: 0; }

        /* YENİ POMODORO IZGARASI */
        .pomodoro-container { display: grid; grid-template-columns: 1fr 1fr; gap: 30px; align-items: start; }

        /* 1. SOL KART: ZAMANLAYICI */
        .timer-box { background: rgba(30, 30, 40, 0.5); border-radius: 20px; border: 1px solid rgba(255,255,255,0.05); padding: 40px; box-shadow: 0 15px 40px rgba(0,0,0,0.4); text-align: center; }
        
        .timer-tabs { display: flex; background: rgba(0,0,0,0.4); border-radius: 12px; padding: 5px; margin-bottom: 30px; }
        .timer-tabs button { flex: 1; background: transparent; border: none; color: #a09eb5; padding: 12px; font-size: 15px; font-weight: bold; border-radius: 8px; cursor: pointer; transition: 0.3s; }
        .timer-tabs button.active { background: #8a2be2; color: #fff; }

        .time-display { font-size: 80px; font-weight: 900; color: #fff; text-shadow: 0 0 20px rgba(138,43,226,0.5); margin: 20px 0; font-variant-numeric: tabular-nums; letter-spacing: 2px;}
        
        .settings-row { display: flex; justify-content: center; align-items: center; gap: 20px; margin-bottom: 30px; flex-wrap: wrap; }
        
        .setting-item { display: flex; flex-direction: column; align-items: flex-start; gap: 5px; }
        .setting-item label { font-size: 12px; color: #a09eb5; text-transform: uppercase; font-weight: bold; }
        .setting-item input, .setting-item select { background: rgba(0,0,0,0.5); border: 1px solid #8a2be2; color: #fff; padding: 10px; border-radius: 8px; outline: none; font-size: 14px; }
        .setting-item input { width: 70px; text-align: center; font-weight: bold; font-size: 16px;}
        
        .audio-preview-btn { background: rgba(138,43,226,0.2); border: 1px solid #8a2be2; color: #fff; width: 40px; height: 40px; border-radius: 8px; cursor: pointer; display: flex; align-items: center; justify-content: center; margin-top: 20px; transition: 0.3s;}
        .audio-preview-btn:hover { background: #8a2be2; }

        .action-buttons { display: flex; justify-content: center; gap: 15px; }
        .btn-circle { width: 60px; height: 60px; border-radius: 50%; border: none; font-size: 20px; color: #fff; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: 0.3s; }
        .btn-start { background: #2ecc71; box-shadow: 0 5px 15px rgba(46,204,113,0.3); }
        .btn-pause { background: #f39c12; box-shadow: 0 5px 15px rgba(243,156,18,0.3); display: none; }
        .btn-finish { background: #e74c3c; border: none; color: #fff; border-radius: 30px; padding: 0 25px; font-weight: bold; font-size: 16px; cursor: pointer; box-shadow: 0 5px 15px rgba(231,76,60,0.3); transition: 0.3s; }
        .btn-finish:hover { transform: scale(1.05); }

        /* 2. SAĞ KART: LİDERLİK TABLOSU */
        .leaderboard-box { background: rgba(30, 30, 40, 0.5); border-radius: 20px; border: 1px solid rgba(255,255,255,0.05); padding: 30px; box-shadow: 0 15px 40px rgba(0,0,0,0.4); display: flex; flex-direction: column; height: 100%; max-height: 520px; }
        .lb-header { display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid rgba(255,255,255,0.05); padding-bottom: 15px; margin-bottom: 15px; }
        .lb-header h3 { margin: 0; font-size: 18px; display: flex; align-items: center; gap: 8px; }
        .my-score-badge { background: rgba(138,43,226,0.2); color: #d4b3ff; padding: 5px 12px; border-radius: 8px; font-size: 13px; font-weight: bold; border: 1px solid rgba(138,43,226,0.4); }

        .lb-list { flex: 1; overflow-y: auto; padding-right: 5px; display: flex; flex-direction: column; gap: 10px; }
        .lb-list::-webkit-scrollbar { width: 5px; }
        .lb-list::-webkit-scrollbar-thumb { background: #8a2be2; border-radius: 10px; }

        .lb-item { display: flex; align-items: center; background: rgba(0,0,0,0.3); padding: 10px 15px; border-radius: 12px; border: 1px solid rgba(255,255,255,0.02); }
        .lb-item.rank-1 { background: linear-gradient(90deg, rgba(255, 215, 0, 0.1) 0%, transparent 100%); border-left: 4px solid #ffd700; }
        
        .lb-rank { font-size: 16px; font-weight: 900; color: #555; width: 35px; text-align: center; }
        .rank-1 .lb-rank { color: #ffd700; font-size: 20px; }

        .lb-user { display: flex; align-items: center; gap: 12px; flex: 1; min-width: 0; }
        .lb-user img { width: 38px; height: 38px; border-radius: 50%; object-fit: cover; border: 1px solid rgba(255,255,255,0.1); }
        .rank-1 .lb-user img { border-color: #ffd700; }
        
        .lb-names { display: flex; flex-direction: column; min-width: 0; }
        .lb-name { font-weight: bold; font-size: 14px; color: #fff; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .lb-username { font-size: 11px; color: #a09eb5; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

        .lb-score { color: #2ecc71; font-weight: bold; font-size: 14px; background: rgba(46,204,113,0.1); padding: 4px 10px; border-radius: 6px; flex-shrink: 0; }

        /* RESPONSIVE TASARIM */
        @media (max-width: 1024px) {
            .pomodoro-container { grid-template-columns: 1fr; }
            .leaderboard-box { max-height: 400px; }
        }
        @media (max-width: 768px) {
            .sidebar { transform: translateX(-100%); }
            .sidebar.active { transform: translateX(0); }
            .sidebar-overlay.active { display: block; }
            .hamburger { display: block; }
            .main-content { margin-left: 0; width: 100%; padding: 20px; }
            .time-display { font-size: 60px; }
            .settings-row { flex-direction: column; align-items: stretch; }
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
            <a href="dashboard.php" class="nav-item"><span>🏠</span> Ana Sayfa</a>
            <a href="pomodoro.php" class="nav-item active"><span>🍅</span> Pomodoro & Skor</a>
            <a href="tekrar.php" class="nav-item"><span>⚠️</span> Tekrar Duvarı</a>
            <a href="logout.php" class="nav-item" style="color: #e74c3c; margin-top: 10px;"><span>🚪</span> Çıkış Yap</a>
        </div>
    </div>

    <div class="main-content">
        <div class="header">
            <div style="display: flex; align-items: center; gap: 15px;">
                <button class="hamburger" onclick="toggleSidebar()">☰</button>
                <div>
                    <h2>⏱️ Odak Merkezi</h2>
                    <p>Süreyi başlat, çalış ve sıralamaya adını yazdır!</p>
                </div>
            </div>
        </div>

        <div class="pomodoro-container">
            
            <div class="timer-box">
                <div class="timer-tabs">
                    <button class="active" id="tab-sayac" onclick="setMode('sayac')">⏳ Geri Sayım</button>
                    <button id="tab-krono" onclick="setMode('kronometre')">⏱️ Kronometre</button>
                </div>

                <div class="time-display" id="time-display">25:00</div>

                <div class="settings-row">
                    <div class="setting-item" id="hedef-alani">
                        <label>Hedef (Dk)</label>
                        <input type="number" id="input-minutes" value="25" min="1" max="300" onchange="updateTimeFromInput()">
                    </div>
                    
                    <div class="setting-item" id="alarm-alani">
                        <label>Bitiş Sesi</label>
                        <select id="alarm-select">
                            <option value="silent" selected>Sessiz (Çalmaz)</option>
                            <option value="https://actions.google.com/sounds/v1/alarms/beep_short.ogg">Bip Bip Sesi</option>
                            <option value="https://actions.google.com/sounds/v1/alarms/digital_watch_alarm_long.ogg">Dijital Saat</option>
                            <option value="https://actions.google.com/sounds/v1/alarms/bugle_tune.ogg">Boru Sesi</option>
                            <option value="https://actions.google.com/sounds/v1/alarms/mechanical_clock_ring.ogg">Zil Sesi</option>
                        </select>
                    </div>
                    
                    <button class="audio-preview-btn" id="ses-test-btn" onclick="testAudio()" title="Sesi Dinle">🔊</button>
                </div>

                <div class="action-buttons">
                    <button class="btn-circle btn-start" id="btn-start" onclick="startTimer()">▶</button>
                    <button class="btn-circle btn-pause" id="btn-pause" onclick="pauseTimer()">⏸</button>
                    <button class="btn-finish" onclick="finishSession()">⏹️ Bitir ve Kaydet</button>
                </div>
            </div>

            <div class="leaderboard-box">
                <div class="lb-header">
                    <h3>🏆 Haftalının En İyileri</h3>
                    <div class="my-score-badge">Sen: <?= $benim_skorum ?> dk</div>
                </div>
                
                <div class="lb-list">
                    <?php 
                    $sira = 1;
                    foreach($liderlik_tablosu as $kisi): 
                        $lb_isim = !empty($kisi['isim']) ? $kisi['isim'] : $kisi['username'];
                        $lb_pp = ($kisi['profil_foto'] != 'default_pp.png' && file_exists('uploads/' . $kisi['profil_foto'])) 
                                    ? 'uploads/' . $kisi['profil_foto'] 
                                    : 'https://ui-avatars.com/api/?name=' . urlencode($lb_isim) . '&background=8a2be2&color=fff';
                        $rank_class = ($sira == 1) ? 'rank-1' : '';
                    ?>
                        <div class="lb-item <?= $rank_class ?>">
                            <div class="lb-rank"><?= $sira == 1 ? '👑' : '#'.$sira ?></div>
                            <div class="lb-user">
                                <img src="<?= $lb_pp ?>" alt="PP">
                                <div class="lb-names">
                                    <div class="lb-name"><?= htmlspecialchars($lb_isim) ?></div>
                                    <div class="lb-username">@<?= htmlspecialchars($kisi['username']) ?></div>
                                </div>
                            </div>
                            <div class="lb-score"><?= $kisi['toplam_dakika'] ?> dk</div>
                        </div>
                    <?php $sira++; endforeach; ?>
                    
                    <?php if(empty($liderlik_tablosu)): ?>
                        <div style="text-align: center; color: #a09eb5; padding: 20px; font-size: 14px;">Henüz kimse çalışmamış. İlk sen ol!</div>
                    <?php endif; ?>
                </div>
            </div>

        </div>
    </div>

    <form id="save-form" action="pomodoro.php" method="POST" style="display: none;">
        <input type="hidden" name="kaydedilecek_dakika" id="hidden-minutes">
    </form>
    
    <audio id="audio-player" src=""></audio>

    <script>
        // Mobil Menü
        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('active');
            document.getElementById('sidebarOverlay').classList.toggle('active');
        }

        // Değişkenler
        let mode = 'sayac'; 
        let isRunning = false;
        let timerInterval;
        let passedSeconds = 0;
        let goalSeconds = 25 * 60;
        let previewTimeout;

        const display = document.getElementById('time-display');
        const inputMin = document.getElementById('input-minutes');
        const audioPlayer = document.getElementById('audio-player');

        // Sekme Değiştirme
        function setMode(newMode) {
            if (isRunning) return alert("Önce süreyi durdurmalısın!");
            mode = newMode;
            passedSeconds = 0;
            
            document.getElementById('tab-sayac').classList.remove('active');
            document.getElementById('tab-krono').classList.remove('active');
            
            if (mode === 'sayac') {
                document.getElementById('tab-sayac').classList.add('active');
                document.getElementById('hedef-alani').style.display = 'flex';
                document.getElementById('alarm-alani').style.display = 'flex';
                document.getElementById('ses-test-btn').style.display = 'flex';
                updateTimeFromInput();
            } else {
                document.getElementById('tab-krono').classList.add('active');
                document.getElementById('hedef-alani').style.display = 'none';
                document.getElementById('alarm-alani').style.display = 'none';
                document.getElementById('ses-test-btn').style.display = 'none';
                renderTime(0);
            }
        }

        // Input'tan Zamanı Güncelleme
        function updateTimeFromInput() {
            if(mode !== 'sayac' || isRunning) return;
            let val = parseInt(inputMin.value) || 25;
            if(val < 1) val = 1;
            goalSeconds = val * 60;
            passedSeconds = 0;
            renderTime(goalSeconds);
        }

        // Ekrana Zaman Yazdırma (MM:SS)
        function renderTime(totalSecs) {
            let m = Math.floor(totalSecs / 60);
            let s = totalSecs % 60;
            display.innerText = `${m.toString().padStart(2, '0')}:${s.toString().padStart(2, '0')}`;
        }

        // Ses Testi (Önizleme)
        function testAudio() {
            const selectedSound = document.getElementById('alarm-select').value;
            audioPlayer.pause();
            audioPlayer.currentTime = 0;
            clearTimeout(previewTimeout);

            if (selectedSound !== 'silent') {
                audioPlayer.src = selectedSound;
                audioPlayer.play().catch(e => console.log(e));
                previewTimeout = setTimeout(() => {
                    audioPlayer.pause();
                    audioPlayer.currentTime = 0;
                }, 3000);
            } else {
                alert("Sessiz mod seçili. Alarm çalmayacak.");
            }
        }

        // Başlat
        function startTimer() {
            if (isRunning) return;
            
            // Eğer çalan bir önizleme sesi varsa anında kes
            audioPlayer.pause();
            audioPlayer.currentTime = 0;
            clearTimeout(previewTimeout);

            if (mode === 'sayac' && passedSeconds === 0) updateTimeFromInput();

            isRunning = true;
            document.getElementById('btn-start').style.display = 'none';
            document.getElementById('btn-pause').style.display = 'flex';
            inputMin.disabled = true;

            timerInterval = setInterval(() => {
                passedSeconds++;
                
                if (mode === 'sayac') {
                    let rem = goalSeconds - passedSeconds;
                    renderTime(rem);

                    if (rem <= 0) {
                        clearInterval(timerInterval);
                        
                        // Alarmı çal
                        const selectedSound = document.getElementById('alarm-select').value;
                        if (selectedSound !== 'silent') {
                            audioPlayer.src = selectedSound;
                            audioPlayer.play().catch(e => console.log(e));
                        }
                        
                        setTimeout(() => { finishSession(); }, 1500);
                    }
                } else {
                    // Kronometre
                    renderTime(passedSeconds);
                }
            }, 1000);
        }

        // Duraklat
        function pauseTimer() {
            clearInterval(timerInterval);
            isRunning = false;
            document.getElementById('btn-pause').style.display = 'none';
            document.getElementById('btn-start').style.display = 'flex';
        }

        // Bitir ve Kaydet
        function finishSession() {
            pauseTimer();
            inputMin.disabled = false;
            
            let workedMins = Math.floor(passedSeconds / 60);
            
            if (workedMins > 0) {
                document.getElementById('hidden-minutes').value = workedMins;
                document.getElementById('save-form').submit();
            } else {
                alert("1 dakikadan az çalıştığın için skor tablosuna eklenmedi.");
                passedSeconds = 0;
                if(mode === 'sayac') renderTime(goalSeconds);
                else renderTime(0);
            }
        }

        // Sayfa yüklendiğinde
        updateTimeFromInput();
    </script>
</body>
</html>