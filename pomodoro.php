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

// --- 2. KULLANICI BİLGİLERİNİ ÇEKELİM ---
$sorgu = $db->prepare("SELECT username, isim, profil_foto, sinav_tipi FROM users WHERE id = ?");
$sorgu->execute([$uid]);
$user = $sorgu->fetch(PDO::FETCH_ASSOC);

$gorunen_isim = !empty($user['isim']) ? $user['isim'] : $user['username'];
$pp_yol = ($user['profil_foto'] != 'default_pp.png' && file_exists('uploads/' . $user['profil_foto'])) 
            ? 'uploads/' . $user['profil_foto'] 
            : 'https://ui-avatars.com/api/?name=' . urlencode($gorunen_isim) . '&background=8a2be2&color=fff';

// --- 3. HAFTALIK LİDERLİK TABLOSU (En İyi 5) ---
$skor_sorgu = $db->query("
    SELECT u.isim, u.username, u.profil_foto, SUM(p.odak_dakika) as toplam_dakika 
    FROM pomodoro_log p 
    JOIN users u ON p.user_id = u.id 
    WHERE YEARWEEK(p.calisma_tarihi, 1) = YEARWEEK(CURDATE(), 1)
    GROUP BY p.user_id 
    ORDER BY toplam_dakika DESC 
    LIMIT 5
");
$liderlik_tablosu = $skor_sorgu->fetchAll(PDO::FETCH_ASSOC);

$skor_kendim = $db->prepare("SELECT SUM(odak_dakika) as toplam FROM pomodoro_log WHERE user_id = ? AND YEARWEEK(calisma_tarihi, 1) = YEARWEEK(CURDATE(), 1)");
$skor_kendim->execute([$uid]);
$benim_skorum = $skor_kendim->fetch(PDO::FETCH_ASSOC)['toplam'] ?? 0;
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pomodoro & Skor - KPSS Yol Arkadaşım</title>
    <link rel="icon" type="image/x-icon" href="images/favicon.ico">
<link rel="icon" type="image/png" href="images/favicon.png">
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #0b0b12; color: #fff; margin: 0; display: flex; min-height: 100vh; overflow-x: hidden; }
        
        /* SAAS YAN MENÜ (SIDEBAR) - ANA SAYFAYLA BİREBİR AYNI YAPILDI */
        .sidebar { width: 260px; background: rgba(20, 20, 25, 0.95); border-right: 1px solid rgba(255,255,255,0.05); display: flex; flex-direction: column; padding: 30px 0; position: fixed; height: 100vh; z-index: 100; transition: transform 0.3s ease; box-shadow: 5px 0 20px rgba(0,0,0,0.5);}
.brand {
    display: flex;
    justify-content: center;
    align-items: center;
    height: 90px; /* Sihirli dokunuş: Menülerin kaymasını engeller! */
    margin-bottom: 20px; /* Profil widget'ı ile aradaki sabit boşluk */
    width: 100%;
}

.brand img {
    width: 250px; /* Logoyu kocaman yaptık! */
    height: auto;
    object-fit: contain; /* Logonun en-boy oranını bozmadan sığdırır */
    transition: transform 0.3s ease;
}

.brand img:hover {
    transform: scale(1.05); /* Üzerine gelince hafif büyüme efekti */
}        
        /* Profil Widget (Mor Tasarım Korundu) */
        .user-widget { padding: 15px; margin: 0 20px 30px 20px; background: rgba(138,43,226,0.1); border-radius: 12px; display: flex; align-items: center; gap: 15px; border: 1px solid #8a2be2; cursor: pointer; transition: 0.3s;}
        .user-widget:hover { background: #8a2be2; border-color: #8a2be2; transform: translateY(-3px);}
        .user-widget img { width: 45px; height: 45px; border-radius: 50%; object-fit: cover; border: 2px solid #8a2be2; transition: 0.3s; }
        .user-widget:hover img { border-color: #fff; }
        .user-info-sidebar { display: flex; flex-direction: column; }
        .u-name { font-weight: bold; font-size: 14px; color: #fff; }
        .u-badge { font-size: 11px; color: #d4b3ff; margin-top: 3px;}

        .nav-menu { display: flex; flex-direction: column; gap: 5px; padding: 0 20px; }
        .nav-item { display: flex; align-items: center; gap: 15px; padding: 15px 20px; text-decoration: none; color: #a09eb5; font-size: 15px; font-weight: 600; border-radius: 12px; transition: 0.3s; }
        .nav-item:hover { background: rgba(255,255,255,0.05); color: #fff; transform: translateX(5px); }
        .nav-item.active { background: linear-gradient(90deg, rgba(138,43,226,0.2) 0%, transparent 100%); color: #fff; border-left: 4px solid #8a2be2; }
        .sidebar-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); z-index: 99; backdrop-filter: blur(3px); }

        /* ANA İÇERİK */
        .main-content { flex: 1; margin-left: 260px; padding: 40px 60px; box-sizing: border-box; width: calc(100% - 260px); }
        .header { margin-bottom: 30px; display: flex; justify-content: space-between; align-items: center; }
        .header h2 { margin: 0; font-size: 28px; font-weight: 800; }
        .hamburger { display: none; background: none; border: none; color: #d4b3ff; font-size: 28px; cursor: pointer; padding: 0; }

        /* İÇERİK IZGARASI */
        .pomodoro-container { display: grid; grid-template-columns: 1.1fr 1fr; gap: 30px; align-items: stretch; }

        .timer-box, .leaderboard-box { 
            background: rgba(30, 30, 40, 0.5); 
            border-radius: 20px; 
            border: 1px solid rgba(255,255,255,0.05); 
            padding: 35px; 
            box-shadow: 0 15px 40px rgba(0,0,0,0.4); 
            display: flex; 
            flex-direction: column;
            min-height: 480px; 
        }
        
        .timer-tabs { display: flex; background: rgba(0,0,0,0.4); border-radius: 12px; padding: 5px; margin-bottom: 25px; }
        .timer-tabs button { flex: 1; background: transparent; border: none; color: #a09eb5; padding: 12px; font-size: 15px; font-weight: bold; border-radius: 8px; cursor: pointer; transition: 0.3s; }
        .timer-tabs button.active { background: #8a2be2; color: #fff; }

        .time-display { font-size: 80px; font-weight: 900; color: #fff; text-shadow: 0 0 20px rgba(138,43,226,0.5); margin: 25px 0; font-variant-numeric: tabular-nums; letter-spacing: 2px; text-align: center;}
        
        .settings-row { display: flex; justify-content: center; align-items: center; gap: 15px; margin-bottom: 30px; flex-wrap: wrap; }
        .setting-item { display: flex; flex-direction: column; align-items: flex-start; gap: 5px; }
        .setting-item label { font-size: 11px; color: #a09eb5; text-transform: uppercase; font-weight: bold; }
        .setting-item input, .setting-item select { background: rgba(0,0,0,0.5); border: 1px solid #8a2be2; color: #fff; padding: 10px; border-radius: 8px; outline: none; font-size: 14px; }
        .setting-item input { width: 60px; text-align: center; }
        
        .audio-preview-btn { background: rgba(138,43,226,0.2); border: 1px solid #8a2be2; color: #fff; width: 40px; height: 40px; border-radius: 8px; cursor: pointer; display: flex; align-items: center; justify-content: center; margin-top: 18px; transition: 0.3s;}
        .audio-preview-btn:hover { background: #8a2be2; }

        .action-buttons { display: flex; justify-content: center; gap: 15px; margin-top: auto;}
        .btn-circle { width: 60px; height: 60px; border-radius: 50%; border: none; font-size: 22px; color: #fff; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: 0.3s; }
        .btn-start { background: #2ecc71; box-shadow: 0 5px 15px rgba(46,204,113,0.3); }
        .btn-pause { background: #f39c12; box-shadow: 0 5px 15px rgba(243,156,18,0.3); display: none; }
        .btn-finish { background: #e74c3c; border: none; color: #fff; border-radius: 35px; padding: 0 30px; font-weight: bold; font-size: 15px; cursor: pointer; box-shadow: 0 5px 15px rgba(231,76,60,0.3); transition: 0.3s; }

        .lb-header { display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid rgba(255,255,255,0.05); padding-bottom: 15px; margin-bottom: 20px; }
        .lb-header h3 { margin: 0; font-size: 18px; }
        .my-score-badge { background: rgba(138,43,226,0.2); color: #d4b3ff; padding: 6px 14px; border-radius: 10px; font-size: 13px; font-weight: bold; border: 1px solid rgba(138,43,226,0.4); }

        .lb-list { flex: 1; overflow-y: auto; display: flex; flex-direction: column; gap: 12px; }
        .lb-item { display: flex; align-items: center; background: rgba(0,0,0,0.3); padding: 12px 18px; border-radius: 15px; border: 1px solid rgba(255,255,255,0.02); }
        .lb-item.rank-1 { background: linear-gradient(90deg, rgba(255, 215, 0, 0.1) 0%, transparent 100%); border-left: 4px solid #ffd700; }
        .lb-rank { font-size: 16px; font-weight: 900; color: #555; width: 35px; text-align: center; }
        .rank-1 .lb-rank { color: #ffd700; font-size: 20px; }
        .lb-user { display: flex; align-items: center; gap: 12px; flex: 1; }
        .lb-user img { width: 40px; height: 40px; border-radius: 50%; object-fit: cover; border: 1px solid rgba(255,255,255,0.1); }
        .lb-name { font-weight: bold; font-size: 14px; color: #fff; }
        .lb-score { color: #2ecc71; font-weight: bold; font-size: 14px; background: rgba(46,204,113,0.1); padding: 6px 12px; border-radius: 8px; }

        .modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.85); backdrop-filter: blur(10px); display: none; justify-content: center; align-items: center; z-index: 1000; }
        .modal-overlay.active { display: flex; }
        .modal-content { background: #1a1a25; padding: 35px; border-radius: 25px; text-align: center; max-width: 380px; border: 1px solid #8a2be2; box-shadow: 0 0 50px rgba(138,43,226,0.3); }
        .modal-content h3 { font-size: 22px; margin-bottom: 10px; color: #fff; }
        .modal-content p { color: #a09eb5; margin-bottom: 25px; line-height: 1.6; font-size: 15px; }
        .modal-btn { background: #2ecc71; color: #fff; border: none; padding: 14px 35px; border-radius: 12px; font-weight: bold; cursor: pointer; transition: 0.3s; font-size: 16px; }
        .modal-btn:hover { transform: scale(1.05); }

        @media (max-width: 1024px) { 
            .pomodoro-container { grid-template-columns: 1fr; } 
            .main-content { margin-left: 0; width: 100%; padding: 20px; } 
            .sidebar { transform: translateX(-100%); } 
            .hamburger { display: block; }
        }
    </style>
</head>
<body>
    <div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>
    <div class="sidebar" id="sidebar">
        <div class="brand">
     <img src="images/dark_logo.png" alt="Logo">
    </div>
        <div class="user-widget" onclick="window.location.href='profil.php'">
            <img src="<?= $pp_yol ?>" alt="Profil">
            <div class="user-info-sidebar">
                <span class="u-name"><?= htmlspecialchars($gorunen_isim); ?></span>
                <span class="u-badge"><?= htmlspecialchars($user['sinav_tipi'] ?? 'Ortaöğretim'); ?></span>
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
                <h2>⏱️ Odak Merkezi</h2>
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
                            <option value="https://actions.google.com/sounds/v1/alarms/bugle_tune.ogg">Boru Sesi</option>
                            <option value="https://actions.google.com/sounds/v1/alarms/mechanical_clock_ring.ogg">Zil Sesi</option>
                        </select>
                    </div>
                    <button class="audio-preview-btn" id="ses-test-btn" onclick="testAudio()">🔊</button>
                </div>
                <div class="action-buttons">
                    <button class="btn-circle btn-start" id="btn-start" onclick="startTimer()">▶</button>
                    <button class="btn-circle btn-pause" id="btn-pause" onclick="pauseTimer()">⏸</button>
                    <button class="btn-finish" onclick="finishSession()">⏹️ Bitir ve Kaydet</button>
                </div>
            </div>

            <div class="leaderboard-box">
                <div class="lb-header">
                    <h3>🏆 Haftalık En İyi 5</h3>
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
                                <div class="lb-name"><?= htmlspecialchars($lb_isim) ?></div>
                            </div>
                            <div class="lb-score"><?= $kisi['toplam_dakika'] ?> dk</div>
                        </div>
                    <?php $sira++; endforeach; ?>
                    <?php if(empty($liderlik_tablosu)): ?>
                        <div style="text-align: center; color: #a09eb5; padding: 40px;">Henüz kimse çalışmamış.</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="modal-overlay" id="finishModal">
        <div class="modal-content">
            <div style="font-size: 50px; margin-bottom: 15px;">🎉</div>
            <h3 id="modal-title">Tebrikler!</h3>
            <p id="modal-desc"></p>
            <button class="modal-btn" onclick="submitToDatabase()">Skoru Kaydet 🔥</button>
        </div>
    </div>

    <form id="save-form" action="pomodoro.php" method="POST" style="display: none;">
        <input type="hidden" name="kaydedilecek_dakika" id="hidden-minutes">
    </form>
    <audio id="audio-player" src=""></audio>

    <script>
        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('active');
            document.getElementById('sidebarOverlay').classList.toggle('active');
        }

        let mode = 'sayac'; let isRunning = false; let timerInterval; let passedSeconds = 0; let goalSeconds = 25 * 60; let previewTimeout;
        const display = document.getElementById('time-display'); const inputMin = document.getElementById('input-minutes'); const audioPlayer = document.getElementById('audio-player');

        function setMode(newMode) {
            if (isRunning) return alert("Önce süreyi durdurmalısın!");
            mode = newMode; passedSeconds = 0;
            document.getElementById('tab-sayac').classList.toggle('active', mode === 'sayac');
            document.getElementById('tab-krono').classList.toggle('active', mode === 'kronometre');
            document.getElementById('hedef-alani').style.display = mode === 'sayac' ? 'flex' : 'none';
            document.getElementById('alarm-alani').style.display = mode === 'sayac' ? 'flex' : 'none';
            document.getElementById('ses-test-btn').style.display = mode === 'sayac' ? 'flex' : 'none';
            mode === 'sayac' ? updateTimeFromInput() : renderTime(0);
        }

        function updateTimeFromInput() {
            if(mode !== 'sayac' || isRunning) return;
            let val = parseInt(inputMin.value) || 25;
            goalSeconds = Math.max(1, val) * 60;
            renderTime(goalSeconds);
        }

        function renderTime(totalSecs) {
            let m = Math.floor(totalSecs / 60); let s = totalSecs % 60;
            display.innerText = `${m.toString().padStart(2, '0')}:${s.toString().padStart(2, '0')}`;
        }

        function testAudio() {
            const selectedSound = document.getElementById('alarm-select').value;
            audioPlayer.pause(); audioPlayer.currentTime = 0;
            if (selectedSound !== 'silent') {
                audioPlayer.src = selectedSound; audioPlayer.play();
                previewTimeout = setTimeout(() => { audioPlayer.pause(); }, 3000);
            }
        }

        function startTimer() {
            if (isRunning) return;
            audioPlayer.pause();
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
                        const snd = document.getElementById('alarm-select').value;
                        if (snd !== 'silent') { audioPlayer.src = snd; audioPlayer.play(); }
                        finishSession();
                    }
                } else renderTime(passedSeconds);
            }, 1000);
        }

        function pauseTimer() { clearInterval(timerInterval); isRunning = false; document.getElementById('btn-pause').style.display = 'none'; document.getElementById('btn-start').style.display = 'flex'; }

        function finishSession() {
            pauseTimer();
            let workedMins = Math.floor(passedSeconds / 60);
            if (workedMins > 0) {
                document.getElementById('modal-title').innerText = `Tebrikler ${workedMins} Dakika!`;
                document.getElementById('modal-desc').innerText = `Bugün hedefine bir adım daha yaklaştın. Bu emeğini skora ekleyelim mi?`;
                document.getElementById('finishModal').classList.add('active');
            } else {
                alert("Henüz 1 dakika bile dolmadı.");
                mode === 'sayac' ? renderTime(goalSeconds) : renderTime(0);
                inputMin.disabled = false;
            }
        }

        function submitToDatabase() {
            let workedMins = Math.floor(passedSeconds / 60);
            document.getElementById('hidden-minutes').value = workedMins;
            document.getElementById('save-form').submit();
        }

        updateTimeFromInput();
    </script>
</body>
</html>