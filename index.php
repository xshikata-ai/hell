<?php
session_start();

// ==========================================
// BACKEND LOGIC (API ENDPOINT)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    // Kredensial Database
    $db_host = 'localhost';
    $db_name = 'xshikata';
    $db_user = 'xshikata';
    $db_pass = 'sA3EtCGjNhY326NA';
    
    // Menerima data JSON dari Frontend (Fetch API)
    $inputJSON = file_get_contents('php://input');
    $input = json_decode($inputJSON, true);
    
    $operativeId = $input['operativeId'] ?? '';
    $cipherKey = $input['cipherKey'] ?? '';

    try {
        // Koneksi PDO yang aman
        $dsn = "mysql:host={$db_host};dbname={$db_name};charset=utf8mb4";
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        $pdo = new PDO($dsn, $db_user, $db_pass, $options);

        // Query ke database
        $stmt = $pdo->prepare("SELECT id, password FROM users WHERE username = :username LIMIT 1");
        $stmt->execute(['username' => $operativeId]);
        $user = $stmt->fetch();

        // Verifikasi Password
        if ($user && password_verify($cipherKey, $user['password'])) {
            $_SESSION['authenticated'] = true;
            $_SESSION['user_id'] = $user['id'];
            
            echo json_encode([
                'status' => 'success', 
                'message' => 'ACCESS GRANTED. INITIATING UPLINK...',
                'redirect' => 'dashboard.php' // Mengirimkan instruksi redirect ke frontend
            ]);
        } else {
            echo json_encode([
                'status' => 'error', 
                'message' => 'FATAL ERROR: Invalid Credentials.'
            ]);
        }

    } catch (PDOException $e) {
        echo json_encode([
            'status' => 'error', 
            'message' => 'SYSTEM OFFLINE: Database connection failed.'
        ]);
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>SECURE LOGIN // AUTHORIZED ONLY</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Courier+Prime:wght@400;700&family=Playfair+Display:wght@700&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --bg-paper: #ecece4;
            --bg-body: #1a1a1a;
            --text-ink: #111;
            --stamp-red: #c1121f;
            --stamp-green: #1a5e20;
            --redacted-black: #0a0a0a;
            --highlight: rgba(0, 0, 0, 0.05);
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            background-color: var(--bg-paper); color: var(--text-ink);
            font-family: 'Courier Prime', monospace; min-height: 100svh;
            display: flex; flex-direction: column; overflow-x: hidden; overflow-y: auto; 
        }

        .scanner {
            position: fixed; top: 0; left: 0; width: 100%; height: 15px;
            background: linear-gradient(to bottom, rgba(0,0,0,0), rgba(0,0,0,0.1) 50%, rgba(0,0,0,0));
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.15); opacity: 0.6; z-index: 100;
            pointer-events: none; animation: scan 4s linear infinite;
        }

        .dossier {
            background-color: transparent; width: 100%; min-height: 100svh;
            padding: 4rem 1.5rem; display: flex; flex-direction: column;
            align-items: center; justify-content: center; position: relative;
            opacity: 0; animation: fadeInDossier 1s cubic-bezier(0.4, 0, 0.2, 1) forwards;
        }

        .dossier::before {
            content: ''; position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background-image: url('data:image/svg+xml;utf8,%3Csvg viewBox="0 0 200 200" xmlns="http://www.w3.org/2000/svg"%3E%3Cfilter id="noise"%3E%3CfeTurbulence type="fractalNoise" baseFrequency="0.8" numOctaves="4" stitchTiles="stitch"/%3E%3C/filter%3E%3Crect width="100%25" height="100%25" filter="url(%23noise)" opacity="0.06"/%3E%3C/svg%3E');
            pointer-events: none; z-index: 1;
        }

        .classification-bar {
            position: fixed; left: 0; width: 100%; background-color: var(--text-ink);
            color: var(--bg-paper); text-align: center; font-weight: bold;
            letter-spacing: 4px; padding: 6px 0; font-size: 0.85rem; z-index: 50;
            box-shadow: 0 0 10px rgba(0,0,0,0.5); text-shadow: 0 0 5px rgba(255,255,255,0.3);
        }
        .classification-bar.top { top: 0; }
        .classification-bar.bottom { bottom: 0; }

        .content-wrapper { position: relative; z-index: 5; width: 100%; max-width: 550px; margin: 0 auto; }

        .header-section {
            text-align: center; margin-bottom: 2.5rem; border-bottom: 2px solid var(--text-ink);
            padding-bottom: 1.5rem; position: relative;
        }

        .animated-shield {
            width: 65px; height: 65px; margin: 0 auto 1.5rem auto; display: block;
            color: var(--text-ink); filter: drop-shadow(0 4px 6px rgba(0,0,0,0.1));
        }
        .animated-shield path { stroke-dasharray: 150; stroke-dashoffset: 150; animation: drawPath 2.5s cubic-bezier(0.4, 0, 0.2, 1) forwards; }
        .animated-shield circle { stroke-dasharray: 100; stroke-dashoffset: 100; animation: drawPath 1.5s cubic-bezier(0.4, 0, 0.2, 1) 0.5s forwards; }

        h1 { font-family: 'Playfair Display', serif; font-size: 2.2rem; text-transform: uppercase; line-height: 1.1; margin-bottom: 0.5rem; font-variant-numeric: tabular-nums; }
        .sub-heading { font-size: 0.9rem; letter-spacing: 2px; font-weight: bold; opacity: 0; animation: fadeIn 1s ease 1s forwards; }

        .login-box {
            border: 1px solid #999; background: rgba(255, 255, 255, 0.3); padding: 2.5rem 2rem;
            position: relative; box-shadow: inset 0 0 20px rgba(0,0,0,0.03); transition: background 0.3s;
        }
        .login-box:hover { background: rgba(255, 255, 255, 0.5); }
        .login-box::before, .login-box::after { content: ''; position: absolute; width: 15px; height: 15px; border: 2px solid var(--text-ink); }
        .login-box::before { top: -1px; left: -1px; border-right: none; border-bottom: none; }
        .login-box::after { bottom: -1px; right: -1px; border-left: none; border-top: none; }

        .input-group { margin-bottom: 2rem; position: relative; }
        .input-group label { display: block; font-weight: bold; font-size: 0.85rem; letter-spacing: 1px; margin-bottom: 0.5rem; transition: color 0.3s; }
        .input-group input {
            width: 100%; background: transparent; border: none; border-bottom: 2px dashed var(--text-ink);
            color: var(--text-ink); font-family: 'Courier Prime', monospace; font-size: 1.2rem;
            padding: 0.5rem 0; outline: none; transition: all 0.3s;
        }

        .input-group::before, .input-group::after {
            content: '['; position: absolute; bottom: 5px; font-size: 1.5rem; color: var(--stamp-red);
            opacity: 0; pointer-events: none; transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }
        .input-group::before { left: -20px; content: '['; transform: translateX(10px); }
        .input-group::after { right: -20px; content: ']'; transform: translateX(-10px); }
        .input-group:focus-within::before, .input-group:focus-within::after { opacity: 1; transform: translateX(0); }
        .input-group:focus-within label { color: var(--stamp-red); }
        .input-group input:focus { border-bottom: 2px solid var(--stamp-red); background: var(--highlight); }

        .submit-btn {
            background-color: var(--text-ink); color: var(--bg-paper); font-family: 'Courier Prime', monospace;
            font-weight: bold; font-size: 1rem; padding: 1rem; border: none; cursor: pointer; width: 100%;
            text-transform: uppercase; letter-spacing: 3px; transition: all 0.3s ease; position: relative; overflow: hidden; z-index: 1;
        }
        .submit-btn::before {
            content: ''; position: absolute; top: 0; left: -100%; width: 100%; height: 100%;
            background: var(--stamp-red); transition: left 0.4s cubic-bezier(0.25, 0.8, 0.25, 1); z-index: -1;
        }
        .submit-btn:hover::before { left: 0; }
        .submit-btn:active { transform: scale(0.98); }

        .system-msg { margin-top: 1.5rem; font-size: 0.85rem; text-align: center; min-height: 1.2rem; font-weight: bold; }
        .pulse-text { animation: pulseOpacity 1.5s infinite alternate; }

        /* --- Dynamically Colored Stamp --- */
        .stamp-overlay {
            position: absolute; top: 50%; left: 50%; padding: 1rem 2rem; font-family: Arial, sans-serif;
            font-size: 2.5rem; font-weight: 900; letter-spacing: 4px; text-transform: uppercase; opacity: 0;
            pointer-events: none; z-index: 20; mix-blend-mode: multiply; white-space: nowrap;
            -webkit-mask-image: url('data:image/svg+xml;utf8,%3Csvg viewBox="0 0 200 200" xmlns="http://www.w3.org/2000/svg"%3E%3Cfilter id="noise"%3E%3CfeTurbulence type="fractalNoise" baseFrequency="1.2" numOctaves="3" stitchTiles="stitch"/%3E%3C/filter%3E%3Crect width="100%25" height="100%25" filter="url(%23noise)" opacity="0.85"/%3E%3C/svg%3E');
            mask-image: url('data:image/svg+xml;utf8,%3Csvg viewBox="0 0 200 200" xmlns="http://www.w3.org/2000/svg"%3E%3Cfilter id="noise"%3E%3CfeTurbulence type="fractalNoise" baseFrequency="1.2" numOctaves="3" stitchTiles="stitch"/%3E%3C/filter%3E%3Crect width="100%25" height="100%25" filter="url(%23noise)" opacity="0.85"/%3E%3C/svg%3E');
        }
        .stamp-denied { color: var(--stamp-red); border: 5px solid var(--stamp-red); }
        .stamp-granted { color: var(--stamp-green); border: 5px solid var(--stamp-green); }

        .paperclip {
            position: absolute; top: -20px; right: -10px; width: 40px; height: auto; transform: rotate(15deg);
            filter: drop-shadow(2px 3px 2px rgba(0,0,0,0.4)); z-index: 10;
        }

        @keyframes scan { 0% { top: -10%; } 100% { top: 110%; } }
        @keyframes fadeInDossier { from { opacity: 0; transform: translateY(20px); filter: blur(4px); } to { opacity: 1; transform: translateY(0); filter: blur(0); } }
        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
        @keyframes drawPath { to { stroke-dashoffset: 0; } }
        @keyframes pulseOpacity { from { opacity: 0.4; } to { opacity: 1; } }
        @keyframes slamStamp {
            0% { transform: translate(-50%, -50%) scale(3) rotate(-10deg); opacity: 0; }
            40% { transform: translate(-50%, -50%) scale(0.8) rotate(-10deg); opacity: 1; }
            60% { transform: translate(-50%, -50%) scale(1.05) rotate(-10deg); opacity: 0.9; }
            100% { transform: translate(-50%, -50%) scale(1) rotate(-10deg); opacity: 0.85; }
        }
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            10%, 30%, 50%, 70%, 90% { transform: translateX(-5px) rotate(-1deg); }
            20%, 40%, 60%, 80% { transform: translateX(5px) rotate(1deg); }
        }
        .shake-animation { animation: shake 0.5s cubic-bezier(.36,.07,.19,.97) both; }
    </style>
</head>
<body>

    <div class="scanner"></div>

    <div class="dossier">
        <div class="classification-bar top">TOP SECRET // NOFORN</div>
        
        <div class="content-wrapper">
            <div class="header-section">
                <svg class="animated-shield" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                    <circle cx="12" cy="11" r="3"/>
                    <path d="M12 14v2"/>
                </svg>
                <h1 id="glitch-title" data-value="IDENTITY VERIFICATION">---------------------</h1>
                <div class="sub-heading">DEPARTMENT OF RESTRICTED INTELLIGENCE</div>
            </div>

            <div class="login-box" id="loginContainer">
                <svg class="paperclip" viewBox="0 0 24 24" fill="none" stroke="#555" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"></path>
                </svg>

                <div class="stamp-overlay" id="statusStamp"></div>

                <form id="adminForm">
                    <div class="input-group">
                        <label for="operativeId">OPERATIVE IDENTIFICATION CODE</label>
                        <input type="text" id="operativeId" required autocomplete="off" placeholder="[ ENTER ID ]" spellcheck="false">
                    </div>
                    
                    <div class="input-group">
                        <label for="cipherKey">SECURITY CIPHER</label>
                        <input type="password" id="cipherKey" required placeholder="[ ENTER CIPHER ]">
                    </div>
                    
                    <button type="submit" class="submit-btn" id="submitBtn">AUTHENTICATE</button>
                    
                    <div class="system-msg pulse-text" id="sysMsg">Awaiting input...</div>
                </form>
            </div>
        </div>
        
        <div class="classification-bar bottom">TOP SECRET // NOFORN</div>
    </div>

    <script>
        const title = document.getElementById("glitch-title");
        const letters = "ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789@#$%&*<>";
        let interval = null;

        function startDecryption() {
            let iteration = 0;
            clearInterval(interval);
            interval = setInterval(() => {
                title.innerText = title.dataset.value.split("").map((letter, index) => {
                    if(index < iteration) return title.dataset.value[index];
                    return letters[Math.floor(Math.random() * letters.length)];
                }).join("");
                
                if(iteration >= title.dataset.value.length){ 
                    clearInterval(interval);
                    title.style.fontFamily = "'Playfair Display', serif"; 
                }
                iteration += 1 / 2; 
            }, 40);
        }
        setTimeout(startDecryption, 300);

        const form = document.getElementById('adminForm');
        const sysMsg = document.getElementById('sysMsg');
        const submitBtn = document.getElementById('submitBtn');
        const loginContainer = document.getElementById('loginContainer');
        const statusStamp = document.getElementById('statusStamp');

        form.addEventListener('submit', async function(e) {
            e.preventDefault(); 
            
            submitBtn.disabled = true;
            submitBtn.textContent = 'VERIFYING...';
            sysMsg.classList.remove('pulse-text');
            sysMsg.style.color = 'var(--text-ink)';
            sysMsg.textContent = '>> Establishing secure connection...';
            statusStamp.style.animation = 'none'; 
            statusStamp.className = 'stamp-overlay'; 
            loginContainer.classList.remove('shake-animation');

            const operativeId = document.getElementById('operativeId').value;
            const cipherKey = document.getElementById('cipherKey').value;

            try {
                await new Promise(r => setTimeout(r, 800));
                sysMsg.textContent = '>> Checking clearance level...';
                
                const response = await fetch(window.location.href, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ operativeId, cipherKey })
                });
                
                const result = await response.json();
                await new Promise(r => setTimeout(r, 800)); 

                sysMsg.textContent = '>> ' + result.message;
                
                if (result.status === 'success') {
                    sysMsg.style.color = 'var(--stamp-green)';
                    statusStamp.textContent = 'GRANTED';
                    statusStamp.classList.add('stamp-granted');
                    
                    void statusStamp.offsetWidth; 
                    statusStamp.style.animation = 'slamStamp 0.4s cubic-bezier(0.25, 1, 0.5, 1) forwards';
                    
                    // Eksekusi redirect ke Dashboard setelah jeda animasi 1.5 detik
                    setTimeout(() => {
                        window.location.href = result.redirect || 'dashboard.php';
                    }, 1500);
                    
                } else {
                    sysMsg.style.color = 'var(--stamp-red)';
                    statusStamp.textContent = 'ACCESS DENIED';
                    statusStamp.classList.add('stamp-denied');
                    
                    submitBtn.textContent = 'AUTHENTICATE';
                    submitBtn.disabled = false;
                    document.getElementById('cipherKey').value = '';
                    
                    loginContainer.classList.add('shake-animation');
                    void statusStamp.offsetWidth; 
                    statusStamp.style.animation = 'slamStamp 0.4s cubic-bezier(0.25, 1, 0.5, 1) forwards';
                }

            } catch (error) {
                sysMsg.style.color = 'var(--stamp-red)';
                sysMsg.textContent = '>> SYSTEM ERROR: Connection Terminated.';
                submitBtn.textContent = 'AUTHENTICATE';
                submitBtn.disabled = false;
            }
        });

        const inputs = document.querySelectorAll('input');
        inputs.forEach(input => {
            input.addEventListener('input', () => {
                if(sysMsg.textContent !== 'Awaiting input...') {
                    sysMsg.style.color = 'var(--text-ink)';
                    sysMsg.textContent = 'Awaiting input...';
                    sysMsg.classList.add('pulse-text');
                    statusStamp.style.animation = 'none'; 
                }
            });
        });
    </script>
</body>
</html>
