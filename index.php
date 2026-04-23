<?php
session_start();
require 'db.php';

$mesaj = "";
$mesaj_turu = ""; 
$aktif_form = "login"; // Sayfa yenilendiğinde hangi formun açık kalacağını tutar

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    // KAYIT OLMA İŞLEMİ
    if (isset($_POST['register_btn'])) {
        $aktif_form = "register"; // Kayıt formundayken hata alırsak, tekrar kayıt formunu açsın
        
        $isim = trim($_POST['isim']);
        $username = trim($_POST['username']);
        $password = trim($_POST['password']);
        $sinav_tipi = $_POST['sinav_tipi']; 

        if (!empty($username) && !empty($password) && !empty($isim)) {
            $kontrol = $db->prepare("SELECT id FROM users WHERE username = ?");
            $kontrol->execute([$username]);
            
            if ($kontrol->rowCount() > 0) {
                $mesaj = "Bu kullanıcı adı alınmış, farklı bir isim dene.";
                $mesaj_turu = "error";
            } else {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $ekle = $db->prepare("INSERT INTO users (isim, username, password, sinav_tipi) VALUES (?, ?, ?, ?)");
                
                if ($ekle->execute([$isim, $username, $hashed_password, $sinav_tipi])) {
                    $mesaj = "Harika! Kayıt başarılı. Şimdi giriş yapabilirsin.";
                    $mesaj_turu = "success";
                    $aktif_form = "login"; // Kayıt başarılıysa giriş formuna yönlendir
                } else {
                    $mesaj = "Bir hata oluştu, tekrar dene.";
                    $mesaj_turu = "error";
                }
            }
        }
    } 
    // GİRİŞ YAPMA İŞLEMİ
    elseif (isset($_POST['login_btn'])) {
        $aktif_form = "login";
        $username = trim($_POST['username']);
        $password = trim($_POST['password']);

        $sorgu = $db->prepare("SELECT * FROM users WHERE username = ?");
        $sorgu->execute([$username]);
        $kullanici = $sorgu->fetch(PDO::FETCH_ASSOC);

        if ($kullanici && password_verify($password, $kullanici['password'])) {
            $_SESSION['user_id'] = $kullanici['id'];
            $_SESSION['username'] = $kullanici['username'];
            $_SESSION['isim'] = $kullanici['isim']; // Gerçek ismini hafızaya aldık
            $_SESSION['sinav_tipi'] = $kullanici['sinav_tipi']; 
            $_SESSION['profil_foto'] = $kullanici['profil_foto']; 
            
            header("Location: dashboard.php");
            exit;
        } else {
            $mesaj = "Kullanıcı adı veya şifre hatalı!";
            $mesaj_turu = "error";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>KPSS Asistanı - Giriş</title>
    <link rel="icon" type="image/x-icon" href="images/favicon.ico">
<link rel="icon" type="image/png" href="images/favicon.png">
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: linear-gradient(135deg, #0a0a0a 0%, #1a0b2e 100%); display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; color: #ffffff; }
        .login-container { background: rgba(20, 20, 25, 0.85); padding: 40px; border-radius: 16px; box-shadow: 0 8px 32px rgba(106, 13, 173, 0.3); border: 1px solid rgba(138, 43, 226, 0.2); width: 100%; max-width: 380px; text-align: center; backdrop-filter: blur(10px); position: relative; overflow: hidden; }
        h1 { color: #d4b3ff; font-size: 28px; margin-bottom: 10px; letter-spacing: 1px; }
        p { color: #a09eb5; font-size: 14px; margin-bottom: 25px; }
        input, select { width: 100%; padding: 14px; margin-bottom: 15px; background-color: rgba(255, 255, 255, 0.05); border: 1px solid #333; color: #fff; border-radius: 8px; box-sizing: border-box; font-size: 14px; transition: all 0.3s; }
        select option { background-color: #1a0b2e; color: #fff; }
        input:focus, select:focus { outline: none; border-color: #8a2be2; box-shadow: 0 0 10px rgba(138, 43, 226, 0.4); }
        button { width: 100%; padding: 14px; background: linear-gradient(90deg, #6a11cb 0%, #8a2be2 100%); color: white; border: none; border-radius: 8px; font-size: 16px; font-weight: 600; cursor: pointer; transition: 0.2s; margin-top: 5px; }
        button:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(138, 43, 226, 0.5); }
        .toggle-text { margin-top: 20px; font-size: 13px; color: #a09eb5; }
        .toggle-text span { color: #d4b3ff; font-weight: bold; cursor: pointer; transition: color 0.3s; }
        .toggle-text span:hover { color: #fff; text-decoration: underline; }
        .alert { padding: 10px; border-radius: 6px; margin-bottom: 15px; font-size: 13px; font-weight: bold;}
        .alert.error { background-color: rgba(231, 76, 60, 0.2); color: #ff7675; border: 1px solid #e74c3c; }
        .alert.success { background-color: rgba(46, 204, 113, 0.2); color: #55efc4; border: 1px solid #2ecc71; }
        .hidden { display: none; }
    </style>
</head>
<body>

    <div class="login-container">
        <h1 id="form-title">KPSS Asistanı</h1>
        <p id="form-subtitle">Sınav yolculuğunu yönetmeye başla.</p>

        <?php if($mesaj != ""): ?>
            <div class="alert <?= $mesaj_turu ?>"><?= $mesaj ?></div>
        <?php endif; ?>
        
        <form id="login-form" class="<?= $aktif_form == 'login' ? '' : 'hidden' ?>" method="POST" action="">
            <input type="text" name="username" placeholder="Kullanıcı Adı" autocomplete="off" required>
            <input type="password" name="password" placeholder="Şifre" required>
            <button type="submit" name="login_btn">Giriş Yap</button>
            <div class="toggle-text">
                Hesabın yok mu? <span onclick="toggleForms('register')">Kayıt Ol</span>
            </div>
        </form>

        <form id="register-form" class="<?= $aktif_form == 'register' ? '' : 'hidden' ?>" method="POST" action="">
            <input type="text" name="isim" placeholder="Ad" autocomplete="off" required>
            <input type="text" name="username" placeholder="Kullanıcı Adı" autocomplete="off" required>
            <input type="password" name="password" placeholder="Şifre" required>
            
            <select name="sinav_tipi" required>
                <option value="" disabled selected>Hangi Sınava Hazırlanıyorsun?</option>
                <option value="Lisans">KPSS Lisans</option>
                <option value="Önlisans">KPSS Önlisans</option>
                <option value="Ortaöğretim">KPSS Ortaöğretim</option>
            </select>

            <button type="submit" name="register_btn">Kayıt Ol</button>
            <div class="toggle-text">
                Zaten hesabın var mı? <span onclick="toggleForms('login')">Giriş Yap</span>
            </div>
        </form>
    </div>

    <script>
        // Sayfa yüklendiğinde aktif forma göre başlıkları ayarla
        document.addEventListener("DOMContentLoaded", function() {
            if ("<?= $aktif_form ?>" === "register") {
                document.getElementById('form-title').innerText = "Aramıza Katıl";
                document.getElementById('form-subtitle').innerText = "Topluluğa katıl ve hedefini seç.";
            }
        });

        function toggleForms(targetForm) {
            const loginForm = document.getElementById('login-form');
            const registerForm = document.getElementById('register-form');
            const title = document.getElementById('form-title');
            const subtitle = document.getElementById('form-subtitle');

            if (targetForm === 'register') {
                loginForm.classList.add('hidden');
                registerForm.classList.remove('hidden');
                title.innerText = "Aramıza Katıl";
                subtitle.innerText = "Topluluğa katıl ve hedefini seç.";
            } else {
                loginForm.classList.remove('hidden');
                registerForm.classList.add('hidden');
                title.innerText = "KPSS Asistanı";
                subtitle.innerText = "Sınav yolculuğunu yönetmeye başla.";
            }
        }
    </script>
</body>
</html>