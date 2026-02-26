<?php
// ==========================================
// SHELL SX: BACKEND NEURAL ENGINE (V10)
// ==========================================
if (isset($_POST['target_node'])) {
    error_reporting(0);
    header('Content-Type: application/json');
    set_time_limit(0);

    $target = trim($_POST['target_node']);
    
    // Auto-Generate XML Path
    $xml_node = preg_replace('/\/[^\/]+\.php(\?.*)?$/i', '/sxallsitemap.xml', $target);
    if ($xml_node == $target) {
        $xml_node = rtrim($target, '/') . '/sxallsitemap.xml';
    }

    function scanNode($url, $mode) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 20);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_ENCODING, ''); 
        
        $headers = [
            'User-Agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'Upgrade-Insecure-Requests: 1'
        ];
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        
        $start = microtime(true);
        $body = curl_exec($ch);
        $end = microtime(true);
        $latency = round(($end - $start) * 1000); // Calculate latency in ms
        
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if (!$body || $httpCode == 0) return ['status' => 'DEAD', 'lat' => 0];

        $status = 'DEAD';
        if ($mode == 'SHELL') {
            if (preg_match('/<title[^>]*>.*?TONO\s+FILEMANAGER.*?<\/title>/si', $body)) $status = 'LIVE';
            if (preg_match('/<title[^>]*>.*?Tiny File Manager\s*\|\s*H3K.*?<\/title>/si', $body)) $status = 'LIVE';
            if (preg_match('/<title[^>]*>.*?StealthFM\s+v65.*?<\/title>/si', $body)) $status = 'LIVE';
            if (preg_match('/<title[^>]*>.*?xshikata.*?<\/title>/si', $body)) $status = 'LIVE';
        } elseif ($mode == 'XML') {
            if ($httpCode == 200 && stripos($body, 'ok|') !== false) $status = 'LIVE';
        }
        return ['status' => $status, 'lat' => $latency];
    }

    $shell_data = scanNode($target, 'SHELL');
    $xml_data = scanNode($xml_node, 'XML');

    echo json_encode([
        'shell' => $shell_data['status'], 
        'xml' => $xml_data['status'],
        'latency' => $shell_data['lat']
    ]);
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>SHELL SX // TERMINAL EDITION</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Rajdhani:wght@400;600;700&family=JetBrains+Mono:wght@400;700&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --primary: #00f3ff;
            --secondary: #7000ff;
            --bg-deep: #030507;
            --glass: rgba(10, 20, 30, 0.85);
            --border: rgba(0, 243, 255, 0.2);
        }

        body {
            background-color: var(--bg-deep);
            color: #e0e0e0;
            font-family: 'Rajdhani', sans-serif;
            background-image: 
                radial-gradient(circle at 15% 50%, rgba(112, 0, 255, 0.05) 0%, transparent 25%), 
                radial-gradient(circle at 85% 30%, rgba(0, 243, 255, 0.05) 0%, transparent 25%);
            overflow-x: hidden;
        }

        .cyber-grid {
            position: fixed;
            top: 0; left: 0; width: 100%; height: 100%;
            background-image: linear-gradient(rgba(0, 243, 255, 0.03) 1px, transparent 1px),
            linear-gradient(90deg, rgba(0, 243, 255, 0.03) 1px, transparent 1px);
            background-size: 50px 50px;
            z-index: -1;
            pointer-events: none;
        }

        .hud-panel {
            background: var(--glass);
            backdrop-filter: blur(12px);
            border: 1px solid var(--border);
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.5);
        }

        .hud-card {
            background: rgba(5, 10, 15, 0.8);
            border: 1px solid rgba(255, 255, 255, 0.05);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .hud-card.status-alert { border-left: 3px solid #ff3333; }
        .hud-card.status-warn { border-left: 3px solid #ffaa00; }
        
        .hud-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 20px -5px rgba(0, 243, 255, 0.1);
            border-color: var(--primary);
        }

        .input-wrapper { transition: max-height 0.5s ease, opacity 0.5s ease; max-height: 500px; opacity: 1; }
        .input-wrapper.collapsed { max-height: 0; opacity: 0; overflow: hidden; }

        @keyframes scanline { 0% { transform: translateX(-100%); } 100% { transform: translateX(100%); } }
        .animate-scan { position: absolute; bottom: 0; left: 0; width: 100%; height: 2px; background: var(--primary); box-shadow: 0 0 10px var(--primary); animation: scanline 2s linear infinite; }

        @keyframes pulse-ring { 0% { transform: scale(0.8); opacity: 0.5; } 100% { transform: scale(1.2); opacity: 0; } }
        .ai-pulse { animation: pulse-ring 2s infinite; }

        /* Terminal Logs */
        .terminal-box {
            font-family: 'JetBrains Mono', monospace;
            background: #050505;
            border-top: 1px solid #333;
            color: #00f3ff;
            text-shadow: 0 0 5px rgba(0, 243, 255, 0.3);
            overflow-y: auto;
            scrollbar-width: thin;
        }
        .log-entry { margin-bottom: 2px; font-size: 10px; opacity: 0.8; }
        .log-entry span { color: #fff; opacity: 0.5; }

        ::-webkit-scrollbar { width: 5px; }
        ::-webkit-scrollbar-track { background: #000; }
        ::-webkit-scrollbar-thumb { background: #333; border-radius: 2px; }

        .hidden { display: none !important; }
        .text-glow { text-shadow: 0 0 10px rgba(0, 243, 255, 0.6); }
    </style>
</head>
<body class="antialiased min-h-screen pb-24"> <div class="cyber-grid"></div>

    <header class="fixed top-0 w-full z-50 hud-panel border-b border-b-cyan-900/50">
        <div class="max-w-7xl mx-auto px-4 py-3">
            <div class="flex justify-between items-center">
                <div class="flex items-center gap-3">
                    <div class="relative w-8 h-8 flex items-center justify-center bg-cyan-900/20 rounded border border-cyan-500/50">
                        <i class="fas fa-microchip text-cyan-400 text-lg"></i>
                        <div class="absolute inset-0 border border-cyan-400 rounded ai-pulse opacity-50"></div>
                    </div>
                    <div>
                        <h1 class="text-xl font-bold tracking-[0.15em] text-white leading-none">SHELL <span class="text-cyan-400 text-glow">SX</span></h1>
                        <p class="text-[9px] text-cyan-600 font-mono tracking-widest mt-0.5">NEURAL AUDIT SYSTEM v10.0</p>
                    </div>
                </div>
                <div class="flex gap-4">
                    <div class="text-right">
                        <p class="text-[9px] text-gray-500 uppercase font-mono">SHELL ACTIVE</p>
                        <p id="statShell" class="text-lg font-bold text-green-400 leading-none font-mono">0</p>
                    </div>
                    <div class="text-right border-l border-gray-800 pl-4">
                        <p class="text-[9px] text-gray-500 uppercase font-mono">XML FOUND</p>
                        <p id="statXml" class="text-lg font-bold text-blue-400 leading-none font-mono">0</p>
                    </div>
                </div>
            </div>
            <div id="aiStatusBar" class="hidden mt-3 flex items-center gap-3 bg-black/40 rounded px-3 py-1 border border-cyan-900/30">
                <i class="fas fa-circle-notch fa-spin text-xs text-cyan-400"></i>
                <span id="aiStatusText" class="text-[10px] text-cyan-400 font-mono truncate w-full uppercase">Initializing Neural Handshake...</span>
            </div>
            <div class="absolute bottom-0 left-0 w-full h-[2px] bg-gray-900">
                <div id="progressBar" class="h-full bg-cyan-400 shadow-[0_0_10px_#00f3ff] w-0 transition-all duration-200"></div>
            </div>
        </div>
    </header>
    <div class="h-28 md:h-32"></div>

    <main class="max-w-7xl mx-auto px-4 space-y-6">

        <section id="commandModule" class="hud-panel rounded-xl overflow-hidden relative">
            <div class="bg-gray-900/50 px-4 py-3 flex justify-between items-center cursor-pointer border-b border-gray-800 hover:bg-gray-800/50 transition-colors" onclick="toggleInput()">
                <div class="flex items-center gap-2">
                    <i class="fas fa-terminal text-cyan-600 text-xs"></i>
                    <span class="text-xs font-bold text-gray-300 tracking-widest uppercase">Target Vector Input</span>
                </div>
                <i id="inputToggleIcon" class="fas fa-chevron-up text-xs text-gray-500 transition-transform"></i>
            </div>

            <div id="inputBody" class="input-wrapper p-1 bg-black/40">
                <textarea id="targetList" class="w-full h-40 bg-transparent text-cyan-300 text-xs font-mono p-4 border-none focus:ring-0 resize-none placeholder-gray-800 leading-relaxed" placeholder="// Awaiting target list...&#10;https://target.com/vx.php"></textarea>
                
                <div class="p-3 border-t border-gray-800/50">
                    <button id="btnEngage" onclick="initiateScan()" class="group relative w-full bg-cyan-900/20 hover:bg-cyan-900/40 border border-cyan-800 text-cyan-400 font-bold py-3 rounded overflow-hidden transition-all active:scale-[0.99]">
                        <div class="absolute inset-0 w-0 bg-cyan-500/10 transition-all duration-[250ms] ease-out group-hover:w-full"></div>
                        <span class="relative flex justify-center items-center gap-2 text-xs tracking-[0.2em]">
                            <i class="fas fa-bolt"></i> ENGAGE NEURAL SCAN
                        </span>
                    </button>
                </div>
            </div>
            
            <div id="terminalLog" class="terminal-box h-24 px-4 py-2 hidden">
                <div class="log-entry"><span>[SYSTEM]</span> NEURAL CORE STANDBY...</div>
            </div>
        </section>

        <div id="recheckModule" class="hidden flex flex-col md:flex-row gap-3">
            <button onclick="triggerRecheck()" class="flex-1 bg-red-900/10 border border-red-500/30 hover:bg-red-900/20 text-red-400 font-bold py-3 rounded-xl flex justify-center items-center gap-2 text-xs tracking-widest transition-all">
                <i class="fas fa-sync-alt fa-spin"></i> RE-ANALYZE <span id="failCount">0</span> NODES
            </button>
            <button onclick="copyFailed()" class="flex-1 bg-gray-800/50 border border-gray-700 hover:border-cyan-500/50 text-gray-300 font-bold py-3 rounded-xl flex justify-center items-center gap-2 text-xs tracking-widest transition-all">
                <i class="fas fa-copy"></i> COPY FAILED LIST
            </button>
        </div>

        <section>
            <div class="flex justify-between items-end mb-4 px-1">
                <h3 class="text-[10px] font-bold text-gray-500 uppercase tracking-widest font-mono">
                    <i class="fas fa-stream mr-1"></i> ANOMALY LOGS
                </h3>
                <button onclick="clearLogs()" class="text-[9px] text-gray-600 hover:text-cyan-500 uppercase transition-colors font-mono">[ CLEAR BUFFER ]</button>
            </div>

            <div id="gridOutput" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 min-h-[100px]">
                <div id="systemIdle" class="col-span-full py-16 flex flex-col items-center justify-center border border-dashed border-gray-800 rounded-xl opacity-30">
                    <i class="fas fa-satellite-dish text-5xl mb-4 text-gray-600"></i>
                    <p class="text-xs font-mono uppercase tracking-widest text-gray-500">System Idle // Awaiting Input</p>
                </div>
            </div>
        </section>
    </main>

    <footer class="fixed bottom-0 w-full bg-[#030507] border-t border-gray-900 z-40">
        <div class="max-w-7xl mx-auto px-4 py-2 flex justify-between items-center text-[9px] font-mono text-gray-600 uppercase">
            <div class="flex gap-4">
                <span><i class="fas fa-server mr-1"></i> NODE: <span class="text-cyan-600">SG-1</span></span>
                <span><i class="fas fa-memory mr-1"></i> MEM: <span class="text-cyan-600">12%</span></span>
            </div>
            <div>
                <span>STATUS: <span class="text-green-500 animate-pulse">ONLINE</span></span>
            </div>
        </div>
    </footer>

    <script>
        let failedNodes = [];
        const logBox = document.getElementById('terminalLog');

        function log(msg, type = 'INFO') {
            const time = new Date().toLocaleTimeString('en-US', {hour12: false});
            const color = type === 'ERROR' ? 'text-red-400' : 'text-cyan-300';
            const entry = `<div class="log-entry"><span>[${time}]</span> <b class="${color}">${msg}</b></div>`;
            logBox.insertAdjacentHTML('afterbegin', entry);
        }

        function toggleInput(forceState = null) {
            const body = document.getElementById('inputBody');
            const icon = document.getElementById('inputToggleIcon');
            const isClosed = body.classList.contains('collapsed');

            if (forceState === 'open' || isClosed) {
                body.classList.remove('collapsed');
                icon.style.transform = 'rotate(0deg)';
                logBox.classList.add('hidden'); // Hide log when typing
            } else if (forceState === 'close' || !isClosed) {
                body.classList.add('collapsed');
                icon.style.transform = 'rotate(180deg)';
                logBox.classList.remove('hidden'); // Show log when scanning
            }
        }

        function clearLogs() {
            document.getElementById('gridOutput').innerHTML = `
                <div id="systemIdle" class="col-span-full py-16 flex flex-col items-center justify-center border border-dashed border-gray-800 rounded-xl opacity-30">
                    <i class="fas fa-check-circle text-5xl mb-4 text-gray-600"></i>
                    <p class="text-xs font-mono uppercase tracking-widest text-gray-500">LOGS PURGED</p>
                </div>`;
            failedNodes = [];
            document.getElementById('recheckModule').classList.add('hidden');
            document.getElementById('statShell').innerText = '0';
            document.getElementById('statXml').innerText = '0';
            logBox.innerHTML = '';
        }

        function triggerRecheck() {
            if(failedNodes.length === 0) return;
            document.getElementById('targetList').value = failedNodes.join('\n');
            toggleInput('open');
            window.scrollTo({top: 0, behavior: 'smooth'});
            setTimeout(initiateScan, 800);
        }

        function copyFailed() {
            if(failedNodes.length === 0) return;
            navigator.clipboard.writeText(failedNodes.join('\n')).then(() => {
                alert('FAILED LIST COPIED TO CLIPBOARD');
            });
        }

        async function initiateScan() {
            const inputVal = document.getElementById('targetList').value;
            if(!inputVal.trim()) return alert("INPUT VECTOR REQUIRED");

            const btn = document.getElementById('btnEngage');
            const statusBar = document.getElementById('aiStatusBar');
            const grid = document.getElementById('gridOutput');
            const recheckMod = document.getElementById('recheckModule');

            failedNodes = [];
            grid.innerHTML = '';
            recheckMod.classList.add('hidden');
            toggleInput('close');
            logBox.innerHTML = ''; // Clear old logs
            
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-cog fa-spin"></i> PROCESSING...';
            statusBar.classList.remove('hidden');

            const lines = inputVal.split('\n');
            const targets = lines.map(line => {
                let clean = line.trim();
                if(!clean) return null;
                let match = clean.match(/(https?:\/\/[^\s]+)/i);
                if(match) clean = match[1];
                else {
                    clean = clean.replace(/^[0-9.\s]+/, '').trim();
                    if(clean && !clean.startsWith('http')) clean = 'http://'+clean;
                }
                if(clean.includes('?')) clean = clean.split('?')[0];
                return clean;
            }).filter(u => u !== null);

            if(targets.length === 0) {
                alert("NO VALID TARGETS FOUND");
                resetUI();
                return;
            }

            let countShell = 0;
            let countXml = 0;
            document.getElementById('statShell').innerText = '0';
            document.getElementById('statXml').innerText = '0';

            for (let i = 0; i < targets.length; i++) {
                const url = targets[i];
                const domain = new URL(url).hostname;
                
                document.getElementById('aiStatusText').innerText = `ANALYZING: ${domain}`;
                log(`SCANNING: ${domain}...`);

                try {
                    const response = await fetch('', {
                        method: 'POST',
                        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                        body: 'target_node=' + encodeURIComponent(url)
                    });
                    
                    const data = await response.json();

                    if(data.shell === 'LIVE') countShell++;
                    if(data.xml === 'LIVE') countXml++;

                    document.getElementById('statShell').innerText = countShell;
                    document.getElementById('statXml').innerText = countXml;

                    const isClean = (data.shell === 'LIVE' && data.xml === 'LIVE');

                    if(!isClean) {
                        failedNodes.push(url);
                        renderCard(url, data.shell, data.xml, data.latency);
                        log(`ANOMALY DETECTED: ${domain}`, 'ERROR');
                    } else {
                        log(`VERIFIED: ${domain}`, 'INFO');
                    }

                } catch (err) {
                    failedNodes.push(url);
                    renderCard(url, 'ERROR', 'ERROR', 0);
                    log(`CONNECTION FAILED: ${domain}`, 'ERROR');
                }

                const pct = Math.round(((i + 1) / targets.length) * 100);
                document.getElementById('progressBar').style.width = pct + '%';
            }

            resetUI();
            
            if(failedNodes.length > 0) {
                document.getElementById('failCount').innerText = failedNodes.length;
                recheckMod.classList.remove('hidden');
            } else {
                grid.innerHTML = `
                    <div class="col-span-full py-12 flex flex-col items-center justify-center border border-green-900/50 bg-green-900/10 rounded-xl">
                        <i class="fas fa-shield-alt text-4xl mb-4 text-green-500"></i>
                        <p class="text-xs font-mono uppercase tracking-widest text-green-400">ALL TARGETS SECURE & VERIFIED</p>
                    </div>`;
            }
        }

        function renderCard(url, shellStatus, xmlStatus, latency) {
            const grid = document.getElementById('gridOutput');
            const isShellLive = shellStatus === 'LIVE';
            const isXmlLive = xmlStatus === 'LIVE';
            const statusClass = !isShellLive ? 'status-alert' : 'status-warn';
            
            const shellBadge = isShellLive 
                ? `<span class="bg-green-500/10 text-green-400 px-2 py-0.5 rounded text-[9px] border border-green-500/30">ACTIVE</span>`
                : `<span class="bg-red-500/10 text-red-400 px-2 py-0.5 rounded text-[9px] border border-red-500/30">OFFLINE</span>`;
                
            const xmlBadge = isXmlLive
                ? `<span class="bg-blue-500/10 text-blue-400 px-2 py-0.5 rounded text-[9px] border border-blue-500/30">FOUND</span>`
                : `<span class="bg-yellow-500/10 text-yellow-400 px-2 py-0.5 rounded text-[9px] border border-yellow-500/30">MISSING</span>`;

            const card = `
                <div class="hud-card rounded-lg p-3 ${statusClass} flex flex-col gap-2 animate-[fadeIn_0.4s_ease-out]">
                    <div class="flex justify-between items-start">
                        <div class="w-10/12">
                            <h4 class="text-[9px] text-gray-500 font-bold uppercase tracking-wider mb-0.5">TARGET NODE</h4>
                            <p class="text-[10px] font-mono text-cyan-100 truncate" title="${url}">${url}</p>
                        </div>
                        <span class="text-[9px] text-gray-600 font-mono">${latency}ms</span>
                    </div>
                    
                    <div class="grid grid-cols-2 gap-2 mt-1">
                        <div class="bg-black/40 p-1.5 rounded flex justify-between items-center border border-gray-800">
                            <span class="text-[9px] text-gray-500 font-bold">SHELL</span>
                            ${shellBadge}
                        </div>
                        <div class="bg-black/40 p-1.5 rounded flex justify-between items-center border border-gray-800">
                            <span class="text-[9px] text-gray-500 font-bold">XML</span>
                            ${xmlBadge}
                        </div>
                    </div>
                    <div class="animate-scan"></div>
                </div>
            `;
            grid.insertAdjacentHTML('afterbegin', card);
        }

        function resetUI() {
            const btn = document.getElementById('btnEngage');
            btn.disabled = false;
            btn.innerHTML = `<span class="relative flex justify-center items-center gap-2 text-xs tracking-[0.2em]"><i class="fas fa-bolt"></i> ENGAGE NEURAL SCAN</span>`;
            document.getElementById('aiStatusBar').classList.add('hidden');
        }
    </script>
</body>
</html>
