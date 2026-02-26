<?php
session_start();

// Validasi Sesi: Jika belum login, tendang kembali ke halaman login
if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    header('Location: index.php');
    exit;
}

// Logika Logout
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    session_destroy();
    header('Location: index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>CENTRAL COMMAND // DASHBOARD</title>
    
    <!-- Using classic Serif for the document and Monospace for data -->
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
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background-color: var(--bg-paper);
            color: var(--text-ink);
            font-family: 'Courier Prime', monospace;
            min-height: 100svh;
            display: flex;
            flex-direction: column;
            overflow-x: hidden; 
            overflow-y: auto; 
        }

        /* --- Global Scanning Line Effect --- */
        .scanner {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 10px;
            background: rgba(0, 0, 0, 0.1);
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.2);
            opacity: 0.4;
            z-index: 100;
            pointer-events: none;
            animation: scan 6s linear infinite;
        }

        /* --- Document Container --- */
        .dossier {
            background-color: transparent;
            width: 100%;
            min-height: 100svh;
            padding: 4rem 1.5rem; 
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            position: relative;
            
            opacity: 0;
            animation: fadeInDossier 1.2s ease forwards;
        }

        /* Paper Texture Overlay */
        .dossier::before {
            content: '';
            position: fixed; 
            top: 0; left: 0; width: 100%; height: 100%;
            background-image: url('data:image/svg+xml;utf8,%3Csvg viewBox="0 0 200 200" xmlns="http://www.w3.org/2000/svg"%3E%3Cfilter id="noise"%3E%3CfeTurbulence type="fractalNoise" baseFrequency="0.8" numOctaves="4" stitchTiles="stitch"/%3E%3C/filter%3E%3Crect width="100%25" height="100%25" filter="url(%23noise)" opacity="0.06"/%3E%3C/svg%3E');
            pointer-events: none;
            z-index: 1;
        }

        /* Classification Header & Footer */
        .classification-bar {
            position: fixed; 
            left: 0;
            width: 100%;
            background-color: var(--text-ink);
            color: var(--bg-paper);
            text-align: center;
            font-weight: bold;
            letter-spacing: 4px;
            padding: 6px 0;
            font-size: 0.85rem;
            z-index: 50;
            box-shadow: 0 0 10px rgba(0,0,0,0.5); 
        }
        .classification-bar.top { top: 0; }
        .classification-bar.bottom { bottom: 0; }

        /* --- Authorized Stamp --- */
        .stamp {
            position: absolute;
            top: 2rem;
            right: 0;
            color: var(--stamp-green);
            border: 4px solid var(--stamp-green);
            padding: 0.5rem 1rem;
            font-family: Arial, sans-serif;
            font-size: 1.4rem;
            font-weight: 900;
            letter-spacing: 2px;
            text-transform: uppercase;
            transform: rotate(-15deg);
            opacity: 0;
            pointer-events: none;
            z-index: 10;
            mix-blend-mode: multiply;
            
            -webkit-mask-image: url('data:image/svg+xml;utf8,%3Csvg viewBox="0 0 200 200" xmlns="http://www.w3.org/2000/svg"%3E%3Cfilter id="noise"%3E%3CfeTurbulence type="fractalNoise" baseFrequency="1.5" numOctaves="3" stitchTiles="stitch"/%3E%3C/filter%3E%3Crect width="100%25" height="100%25" filter="url(%23noise)" opacity="0.9"/%3E%3C/svg%3E');
            mask-image: url('data:image/svg+xml;utf8,%3Csvg viewBox="0 0 200 200" xmlns="http://www.w3.org/2000/svg"%3E%3Cfilter id="noise"%3E%3CfeTurbulence type="fractalNoise" baseFrequency="1.5" numOctaves="3" stitchTiles="stitch"/%3E%3C/filter%3E%3Crect width="100%25" height="100%25" filter="url(%23noise)" opacity="0.9"/%3E%3C/svg%3E');
            
            animation: slamStamp 0.4s cubic-bezier(0.25, 1, 0.5, 1) 1.5s forwards;
        }

        /* --- Typography --- */
        .content-wrapper {
            position: relative;
            z-index: 5;
            width: 100%;
            max-width: 700px; 
            margin: 0 auto;
        }

        h1 {
            font-family: 'Playfair Display', serif;
            font-size: 2rem;
            text-transform: uppercase;
            border-bottom: 2px solid var(--text-ink);
            padding-bottom: 0.5rem;
            margin-top: 2rem;
            margin-bottom: 1.5rem;
            line-height: 1.1;
        }

        .meta-data {
            font-size: 0.85rem;
            margin-bottom: 2rem;
            line-height: 1.5;
            border-left: 3px solid var(--text-ink);
            padding-left: 1rem;
        }

        p {
            font-size: 0.95rem;
            line-height: 1.6;
            margin-bottom: 1rem;
            text-align: justify;
            min-height: 3.2em; /* Reserve space for typewriter */
        }

        /* --- Redacted Text Effect --- */
        .redacted {
            background-color: var(--redacted-black);
            color: var(--redacted-black);
            padding: 0 4px;
            user-select: none;
            display: inline-block;
            line-height: 1;
            position: relative;
            overflow: hidden;
        }
        
        .redacted::after {
            content: '';
            position: absolute;
            top: 0; left: -100%;
            width: 50%; height: 100%;
            background: rgba(255,255,255,0.1);
            transform: skewX(-20deg);
            transition: left 0.5s ease;
        }

        .redacted:hover {
            color: #444;
            transition: color 1.5s ease-in;
        }
        
        .redacted:hover::after {
            left: 200%;
        }

        /* --- Photo Evidence --- */
        .evidence-photo {
            margin: 2rem auto;
            display: block;
            width: 100%;
            max-width: 250px;
            background: #fff;
            padding: 0.5rem;
            padding-bottom: 2rem;
            border: 1px solid #aaa;
            box-shadow: 2px 4px 15px rgba(0,0,0,0.3);
            transform: rotate(2deg);
            position: relative;
            
            opacity: 0;
            animation: revealPhoto 1s ease 2.2s forwards;
        }

        .evidence-photo img {
            width: 100%;
            height: auto;
            display: block;
            filter: grayscale(100%) contrast(1.6) sepia(0.3);
            transition: filter 0.5s ease;
        }
        
        .evidence-photo:hover img {
            filter: grayscale(80%) contrast(1.2) sepia(0.1);
        }

        .evidence-caption {
            position: absolute;
            bottom: 0.5rem;
            left: 0;
            width: 100%;
            text-align: center;
            font-size: 0.75rem;
            font-weight: bold;
        }

        /* --- Navigation Menu --- */
        .nav-menu {
            margin: 2.5rem 0;
            display: flex;
            flex-direction: column;
            gap: 1.2rem;
            width: 100%;
            opacity: 0;
            animation: fadeInText 1s ease 2.5s forwards;
        }

        .nav-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            text-decoration: none;
            color: var(--text-ink);
            font-weight: bold;
            font-size: 1.1rem;
            border-bottom: 2px dashed #999;
            padding-bottom: 0.5rem;
            transition: all 0.2s ease;
            position: relative;
        }

        .nav-item::before {
            content: '>';
            position: absolute;
            left: -20px;
            opacity: 0;
            color: var(--stamp-red);
            transition: all 0.2s;
        }

        .nav-item:hover {
            color: var(--stamp-red);
            border-bottom-color: var(--stamp-red);
            transform: translateX(15px);
        }

        .nav-item:hover::before {
            opacity: 1;
        }

        .nav-item .ext-icon {
            font-size: 0.8rem;
            opacity: 0.5;
            font-family: sans-serif;
            transition: transform 0.2s;
        }

        .nav-item:hover .ext-icon {
            transform: translate(3px, -3px);
            opacity: 1;
        }

        .logout-btn {
            margin-top: 1rem;
            color: var(--stamp-red);
            border-bottom: 2px solid var(--stamp-red);
        }

        .logout-btn:hover {
            background-color: var(--stamp-red);
            color: var(--bg-paper);
            padding-left: 1rem;
            padding-right: 1rem;
        }
        .logout-btn:hover::before { display: none; }

        /* --- SVG Paperclip --- */
        .paperclip {
            position: absolute;
            top: -30px;
            left: -20px;
            width: 40px;
            height: auto;
            transform: rotate(-10deg);
            filter: drop-shadow(2px 3px 2px rgba(0,0,0,0.4));
            z-index: 10;
        }

        /* --- Typewriter Cursor --- */
        .cursor {
            display: inline-block;
            width: 8px;
            height: 1.2em;
            background-color: var(--text-ink);
            vertical-align: middle;
            animation: blink 1s step-end infinite;
        }

        /* --- KEYFRAMES --- */
        @keyframes scan {
            0% { top: -10%; }
            100% { top: 110%; }
        }

        @keyframes fadeInDossier {
            from { opacity: 0; filter: blur(5px); }
            to { opacity: 1; filter: blur(0); }
        }

        @keyframes slamStamp {
            0% { transform: scale(3) rotate(-15deg); opacity: 0; }
            40% { transform: scale(0.8) rotate(-15deg); opacity: 1; }
            60% { transform: scale(1.05) rotate(-15deg); opacity: 0.9; }
            100% { transform: scale(1) rotate(-15deg); opacity: 0.85; }
        }

        @keyframes blink {
            0%, 100% { opacity: 1; }
            50% { opacity: 0; }
        }

        @keyframes fadeInText { 
            from { opacity: 0; transform: translateY(10px); } 
            to { opacity: 1; transform: translateY(0); } 
        }

        @keyframes revealPhoto {
            from { opacity: 0; transform: translateY(10px) rotate(0deg); }
            to { opacity: 1; transform: translateY(0) rotate(2deg); }
        }

    </style>
</head>
<body>

    <div class="scanner"></div>

    <div class="dossier">
        <div class="classification-bar top">TOP SECRET // NOFORN</div>
        
        <div class="content-wrapper">
            <!-- SVG Paperclip -->
            <svg class="paperclip" viewBox="0 0 24 24" fill="none" stroke="#555" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"></path>
            </svg>

            <div class="stamp">AUTHORIZED</div>

            <div class="meta-data">
                <strong>OPERATIVE ID:</strong> <span class="redacted">#88-A4F9</span><br>
                <strong>TIMESTAMP:</strong> <span id="time-log">00:00:00</span><br>
                <strong>CLEARANCE LEVEL:</strong> LEVEL 5 (ADMIN)
            </div>

            <h1>Welcome</h1>

            <!-- Image Photo Evidence Included Back -->
            <div class="evidence-photo">
                <img src="https://xshikata.wtf/logo.jpg" alt="Entity Logo">
                <div class="evidence-caption">EXHIBIT A: AUTHORIZED ENTITY</div>
            </div>

            <p id="typewriter-text"></p>

            <!-- Navigation Menu -->
            <div class="nav-menu">
                <a href="cpm.php" class="nav-item">
                    <span>[01] CPANEL ACCESS</span>
                    <span class="ext-icon">↗</span>
                </a>
                <a href="checker.php" class="nav-item">
                    <span>[02] SHELL CHECKER</span>
                    <span class="ext-icon">↗</span>
                </a>
                <a href="en.php" class="nav-item">
                    <span>[03] ENCODER / DECODER</span>
                    <span class="ext-icon">↗</span>
                </a>
                
                <a href="?action=logout" class="nav-item logout-btn">
                    <span>[ DISCONNECT / LOGOUT ]</span>
                </a>
            </div>

            <p style="opacity: 0; font-size: 0.8rem; font-style: italic; animation: fadeInText 1s ease 3s forwards;">
                All activities within this module are monitored and end-to-end encrypted (E2EE) by the <span class="redacted">NEXUS-9</span> protocol.
            </p>
        </div>
        
        <div class="classification-bar bottom">TOP SECRET // NOFORN</div>
    </div>

    <script>
        // Set current log time in UTC
        const timeElement = document.getElementById('time-log');
        const now = new Date();
        timeElement.textContent = now.toISOString().replace('T', ' ').substring(0, 19) + " UTC";

        // Typewriter Effect (Translated to English)
        const textToType = "Secure connection established. Identity validated. Please select an operational module from the directory below to initiate.";
        const typeContainer = document.getElementById('typewriter-text');
        let charIndex = 0;

        const cursor = document.createElement('span');
        cursor.className = 'cursor';

        function typeWriter() {
            if (charIndex < textToType.length) {
                // Wrap "operational module" in a redacted span when it is fully typed out
                let currentText = textToType.substring(0, charIndex + 1);
                
                if (currentText.includes("operational module")) {
                    currentText = currentText.replace("operational module", "<span class='redacted'>operational module</span>");
                }

                typeContainer.innerHTML = currentText;
                typeContainer.appendChild(cursor);
                
                charIndex++;
                
                const speed = Math.random() * 30 + 10; 
                setTimeout(typeWriter, speed);
            } else {
                setTimeout(() => {
                    cursor.style.display = 'none'; 
                }, 3000);
            }
        }

        // Mulai animasi ketik setelah jeda
        setTimeout(typeWriter, 1000);
    </script>
</body>
</html>
