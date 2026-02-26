<?php
session_start();
// error_reporting(E_ALL);
// ini_set('display_errors', 1);

define('CREDENTIALS_FILE', 'xnxs.json');
define('MASTER_USER', 'xshikata');
define('MASTER_PASS', '@04Dec97');

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
        if ($response === false) {
            throw new Exception('cURL error: ' . curl_error($ch));
        }
        curl_close($ch);
        return json_decode($response, true);
    }

    public function testConnection() {
        try {
            $result = $this->api_request('/execute/Email/list_pops');
            return isset($result['status']);
        } catch (Exception $e) {
            return false;
        }
    }

    public function listDir() {
        // PERUBAHAN DI SINI: Menambahkan &show_hidden=1 untuk menampilkan dotfiles
        return $this->api_request('/execute/Fileman/list_files?dir=' . urlencode($this->currentDir) . '&show_hidden=1');
    }

    public function uploadFile($file) {
        if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            throw new Exception('Invalid file upload');
        }
        $postFields = [ 'dir' => $this->currentDir, 'file' => new CURLFile($file['tmp_name'], $file['type'], $file['name']) ];
        return $this->api_request('/execute/Fileman/upload_files', $postFields);
    }

    public function viewFile($filename) {
        $result = $this->api_request('/execute/Fileman/get_file_content?dir=' . urlencode($this->currentDir) . '&file=' . urlencode($filename));
        return $result['data']['content'] ?? 'Unable to read file content';
    }

    public function createFile($filename, $content = '') {
        $postFields = http_build_query(['dir' => $this->currentDir, 'file' => $filename, 'content' => $content]);
        return $this->api_request('/execute/Fileman/save_file_content', $postFields);
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

// --- Master Login Gate ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['master_login'])) {
    if (isset($_POST['username']) && isset($_POST['password']) && $_POST['username'] === MASTER_USER && $_POST['password'] === MASTER_PASS) {
        $_SESSION['master_loggedin'] = true;
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    } else {
        $masterLoginError = 'Invalid master credentials.';
    }
}

if (!isset($_SESSION['master_loggedin']) || $_SESSION['master_loggedin'] !== true) {
    ?>
    <!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Master Access</title><script src="https://cdn.tailwindcss.com"></script><style>html { background-color: #000; } .animate-slide-up { animation: slideUp 0.4s ease-out forwards; } @keyframes slideUp { from { transform: translateY(20px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }</style></head><body class="bg-black text-gray-200 font-sans min-h-screen flex items-center justify-center p-4"><div class="w-full max-w-sm"><div class="bg-gray-950 border border-gray-800 rounded-xl shadow-2xl p-8 animate-slide-up"><h1 class="text-3xl font-bold mb-6 text-center text-red-500">Master Access</h1><?php if (isset($masterLoginError)): ?><div class="mb-4 p-3 bg-red-900/50 text-red-200 rounded-lg text-sm"><?= htmlspecialchars($masterLoginError); ?></div><?php endif; ?><form method="post" class="space-y-4"><input type="hidden" name="master_login" value="1"><div><label class="block text-gray-400 mb-2 text-sm">Username</label><input type="text" name="username" class="w-full p-3 bg-gray-900 border border-gray-700 rounded-lg focus:ring-2 focus:ring-red-500 text-white" required></div><div><label class="block text-gray-400 mb-2 text-sm">Password</label><input type="password" name="password" class="w-full p-3 bg-gray-900 border border-gray-700 rounded-lg focus:ring-2 focus:ring-red-500 text-white" required></div><button type="submit" class="w-full bg-red-600 text-white p-3 rounded-lg hover:bg-red-700 font-medium">Authenticate</button></form></div></div></body></html>
    <?php
    exit;
}

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
    ?>
    <!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>cPanel Manager - Login</title><script src="https://cdn.tailwindcss.com"></script><link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css"><style>html { background-color: #000; } .animate-slide-up { animation: slideUp 0.4s ease-out forwards; } @keyframes slideUp { from { transform: translateY(20px); opacity: 0; } to { transform: translateY(0); opacity: 1; } } ::-webkit-scrollbar { width: 5px; } ::-webkit-scrollbar-track { background: transparent; } ::-webkit-scrollbar-thumb { background: #4b5563; border-radius: 10px;}</style></head><body class="bg-black text-gray-200 font-sans min-h-screen flex items-center justify-center p-4"><div class="w-full max-w-lg"><?php if (!empty($saved_credentials) && !isset($_GET['show_new'])): ?><div class="bg-gray-950 border border-gray-800 rounded-xl shadow-2xl p-6 animate-slide-up"><div class="flex justify-between items-center mb-4"><h1 class="text-2xl font-bold text-blue-400">Select Account</h1><a href="?show_new=1" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-all font-medium text-sm"><i class="fa-solid fa-plus mr-1"></i> Add New</a></div><div class="max-h-[60vh] overflow-y-auto"><table class="w-full text-left"><thead class="sticky top-0 bg-gray-950"><tr><th class="p-3 text-sm font-semibold text-gray-400">Username</th><th class="p-3 text-sm font-semibold text-gray-400">Domain</th><th class="p-3 text-sm font-semibold text-gray-400"></th></tr></thead><tbody class="divide-y divide-gray-800"><?php foreach ($saved_credentials as $cred): $account_id = $cred['username'] . '@' . $cred['domain']; ?><tr class="hover:bg-gray-900"><td class="p-3 font-medium text-white"><?= htmlspecialchars($cred['username']) ?></td><td class="p-3 text-gray-400"><?= htmlspecialchars($cred['domain']) ?></td><td class="p-3 text-right"><form method="post" class="inline"><input type="hidden" name="action" value="login_existing"><input type="hidden" name="account_id" value="<?= htmlspecialchars($account_id) ?>"><button type="submit" class="px-3 py-1 bg-blue-600 text-white rounded-md hover:bg-blue-700 text-sm">Login</button></form></td></tr><?php endforeach; ?></tbody></table></div></div><?php else: ?><div class="bg-gray-950 border border-gray-800 rounded-xl shadow-2xl p-8 animate-slide-up"><h1 class="text-3xl font-bold mb-6 text-center text-blue-400">Add/Update cPanel Account</h1><?php if (isset($loginError)): ?><div class="mb-4 p-3 bg-red-900/50 text-red-200 rounded-lg text-sm"><?= htmlspecialchars($loginError); ?></div><?php endif; ?><form method="post" class="space-y-4"><input type="hidden" name="action" value="login_new"><div><label class="block text-gray-400 mb-2 text-sm">Domain</label><input type="text" name="domain" class="w-full p-3 bg-gray-900 border border-gray-700 rounded-lg focus:ring-2 focus:ring-blue-500 text-white" placeholder="example.com" required></div><div><label class="block text-gray-400 mb-2 text-sm">Username</label><input type="text" name="username" class="w-full p-3 bg-gray-900 border border-gray-700 rounded-lg focus:ring-2 focus:ring-blue-500 text-white" required></div><div><label class="block text-gray-400 mb-2 text-sm">API Token</label><input type="password" name="apiToken" class="w-full p-3 bg-gray-900 border border-gray-700 rounded-lg focus:ring-2 focus:ring-blue-500 text-white" required></div><div><label class="block text-gray-400 mb-2 text-sm">Home Base Path (Optional)</label><input type="text" name="home_base" class="w-full p-3 bg-gray-900 border border-gray-700 rounded-lg focus:ring-2 focus:ring-blue-500 text-white" placeholder="Default: /home"></div><button type="submit" class="w-full bg-blue-600 text-white p-3 rounded-lg hover:bg-blue-700 font-medium">Login & Save</button><?php if (!empty($saved_credentials)): ?><div class="text-center mt-4"><a href="<?= $_SERVER['PHP_SELF'] ?>" class="text-gray-400 hover:text-white text-sm">&larr; Back to account selection</a></div><?php endif; ?></form></div><?php endif; ?></div></body></html>
    <?php
    exit;
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
            case 'create_file': $browser->createFile($_POST['filename'], $_POST['content'] ?? ''); $message = 'File created.'; break;
            case 'edit_file': $browser->createFile($_POST['filename'], $_POST['content']); $message = 'File updated.'; break;
            case 'create_folder': $browser->createFolder($_POST['foldername']); $message = 'Folder created.'; break;
            case 'delete_file': $browser->deleteFile($_POST['filename']); $message = 'File deleted.'; break;
            case 'delete_folder': $browser->deleteFolder($_POST['foldername']); $message = 'Folder deleted.'; break;
            case 'rename': $browser->renameItem($_POST['old_name'], $_POST['new_name']); $message = 'Item renamed.'; break;
            case 'create_ftp': $browser->createFTPAccount($_POST['login'], $_POST['password'], $_POST['quota'] ?? 0); $message = 'FTP Account created.'; break;
            case 'delete_ftp': $browser->deleteFTPAccount($_POST['user']); $message = 'FTP Account deleted.'; break;
            case 'add_dns': $browser->addDNSRecord($active_credential['domain'], $_POST['dname'], $_POST['record_type'], $_POST['address'], (int)$_POST['ttl']); $message = 'DNS Record added.'; break;
            case 'toggle_modsec': $browser->toggleModSecurity($_POST['domain'], isset($_POST['enable'])); $message = 'ModSecurity status changed.'; break;
        }
    } catch (Exception $e) {
        $message = 'Error: ' . $e->getMessage();
        $message_type = 'error';
    }
}
if (isset($_FILES['file'])) {
    try { $browser->uploadFile($_FILES['file']); $message = 'File uploaded.'; } catch (Exception $e) { $message = 'Error: ' . $e->getMessage(); $message_type = 'error';}
}

// --- Prepare data for page rendering ---
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
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>cPanel Manager</title><script src="https://cdn.tailwindcss.com"></script><link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css"><style>html { background-color: #000; } .modal { transition: opacity 0.3s ease, transform 0.3s ease; transform: scale(0.95); opacity: 0; } .modal.show { transform: scale(1); opacity: 1; } .sidebar-link { transition: all 0.2s ease-in-out; border-left: 3px solid transparent; } .sidebar-link:hover { background-color: rgba(59, 130, 246, 0.1); border-left-color: #3b82f6; color: #eff6ff; } .sidebar-link.active { background-color: rgba(59, 130, 246, 0.2); border-left-color: #3b82f6; color: #ffffff; font-weight: 600; } ::-webkit-scrollbar { width: 8px; } ::-webkit-scrollbar-track { background: #1f2937; } ::-webkit-scrollbar-thumb { background: #4b5563; border-radius: 4px;} ::-webkit-scrollbar-thumb:hover { background: #6b7280; } #sidebar.open { transform: translateX(0); }</style></head><body class="bg-black text-gray-300 font-sans"><div id="sidebar-overlay" class="fixed inset-0 bg-black/60 z-30 hidden md:hidden"></div><div class="relative min-h-screen md:flex"><div class="bg-gray-950 text-gray-200 flex justify-between md:hidden"><a href="#" class="block p-4 text-white font-bold text-blue-400">cPanel Manager</a><button id="mobile-menu-button" class="p-4 focus:outline-none focus:bg-gray-800"><i class="fa-solid fa-bars fa-lg"></i></button></div><div id="sidebar" class="bg-gray-950 w-64 space-y-6 py-7 px-2 fixed inset-y-0 left-0 transform -translate-x-full transition-transform duration-300 ease-in-out md:relative md:translate-x-0 z-40"><div class="px-4 mb-8"><h1 class="text-2xl font-bold text-blue-400">cPanel Manager</h1><p class="text-sm text-gray-400 mt-1">Logged in as: <br><span class="font-medium text-gray-200"><?= htmlspecialchars($active_credential['username']) ?></span></p></div><nav class="flex flex-col space-y-1"><a href="?section=files" class="sidebar-link py-2.5 px-4 rounded <?= $section === 'files' ? 'active' : '' ?>"><i class="fa-solid fa-folder w-6"></i> File Manager</a><a href="?section=ftp" class="sidebar-link py-2.5 px-4 rounded <?= $section === 'ftp' ? 'active' : '' ?>"><i class="fa-solid fa-server w-6"></i> FTP Manager</a><a href="?section=dns" class="sidebar-link py-2.5 px-4 rounded <?= $section === 'dns' ? 'active' : '' ?>"><i class="fa-solid fa-globe w-6"></i> DNS</a><a href="?section=modsec" class="sidebar-link py-2.5 px-4 rounded <?= $section === 'modsec' ? 'active' : '' ?>"><i class="fa-solid fa-shield-halved w-6"></i> ModSecurity</a></nav><div class="absolute bottom-0 w-full left-0 p-4"><a href="?logout=1" class="w-full block text-center bg-red-600/80 text-white px-4 py-2.5 rounded-lg hover:bg-red-600 transition-all"><i class="fa-solid fa-arrow-right-from-bracket mr-2"></i>Logout</a></div></div><main class="flex-1 p-4 md:p-6 lg:p-8">
<?php if ($message): $msg_class = $message_type === 'error' ? 'bg-red-900/50 text-red-200' : 'bg-blue-900/50 text-blue-200'; ?><div class="mb-4 p-3 <?= $msg_class ?> rounded-lg"><?= htmlspecialchars($message) ?></div><?php endif; ?>
<?php if ($section === 'files'): ?>
<div class="bg-gray-950 border border-gray-800 rounded-xl shadow-lg p-4 md:p-6"><div class="flex flex-wrap justify-between items-center gap-4 mb-6"><h2 class="text-2xl font-bold text-white">File Manager</h2><div class="flex flex-wrap gap-2"><form method="post" enctype="multipart/form-data"><input type="hidden" name="dir" value="<?= htmlspecialchars($currentDir) ?>"><input type="file" name="file" id="file-upload" class="hidden" onchange="this.form.submit()"><label for="file-upload" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 cursor-pointer flex items-center transition-all"><i class="fa-solid fa-upload mr-2"></i> Upload</label></form><button onclick="showFileModal()" class="bg-purple-600 text-white px-4 py-2 rounded-lg hover:bg-purple-700 flex items-center transition-all"><i class="fa-solid fa-file-circle-plus mr-2"></i> New File</button><button onclick="showFolderModal()" class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 flex items-center transition-all"><i class="fa-solid fa-folder-plus mr-2"></i> New Folder</button></div></div><div class="mb-4 bg-gray-900 p-3 rounded-lg text-gray-400 text-sm overflow-x-auto whitespace-nowrap"><?php foreach ($breadcrumb as $index => $part) { echo '<a href="?section=files&dir=' . urlencode($part['path']) . '" class="hover:text-blue-400">' . htmlspecialchars($part['name']) . '</a>'; if ($index < count($breadcrumb) - 1) echo ' <span class="mx-1">/</span> '; } ?></div><div class="overflow-x-auto"><table class="w-full text-left min-w-[600px]"><thead><tr class="border-b border-gray-700 text-gray-400"><th class="p-3">Name</th><th class="p-3">Size</th><th class="p-3">Modified</th><th class="p-3">Actions</th></tr></thead><tbody><?php if (!empty($contents['data'])) { foreach ($contents['data'] as $item) { echo '<tr class="border-b border-gray-800 hover:bg-gray-900"><td class="p-3 text-gray-200">'; if ($item['type'] === 'dir') { echo '<i class="fa-solid fa-folder text-blue-400 mr-2"></i> <a href="?section=files&dir=' . urlencode($currentDir . '/' . $item['file']) . '" class="hover:underline">' . htmlspecialchars($item['file']) . '</a>'; } else { echo '<i class="fa-solid fa-file-lines text-gray-500 mr-2"></i> <span onclick="showViewModal(\'' . htmlspecialchars($item['file'], ENT_QUOTES) . '\')" class="cursor-pointer hover:underline">' . htmlspecialchars($item['file']) . '</span>'; } echo '</td><td class="p-3 text-gray-400">' . ($item['humansize'] ?? '-') . '</td><td class="p-3 text-gray-400">' . (isset($item['mtime']) ? date('Y-m-d H:i', $item['mtime']) : '-') . '</td><td class="p-3"><div class="flex gap-3 text-sm">'; if ($item['type'] !== 'dir') { echo '<button onclick="showEditModal(\'' . htmlspecialchars($item['file'], ENT_QUOTES) . '\')" class="text-green-400 hover:text-green-300">Edit</button>'; } echo '<button onclick="showRenameModal(\'' . htmlspecialchars($item['file'], ENT_QUOTES) . '\', \'' . $item['type'] . '\')" class="text-yellow-400 hover:text-yellow-300">Rename</button><form method="post" onsubmit="return confirm(\'Delete?\');" class="inline"><input type="hidden" name="action" value="delete_' . ($item['type'] === 'dir' ? 'folder' : 'file') . '"><input type="hidden" name="' . ($item['type'] === 'dir' ? 'foldername' : 'filename') . '" value="' . htmlspecialchars($item['file']) . '"><button type="submit" class="text-red-400 hover:text-red-300">Delete</button></form></div></td></tr>'; } } else { echo '<tr><td colspan="4" class="p-4 text-center text-gray-500">Directory is empty.</td></tr>'; } ?></tbody></table></div></div>
<?php elseif ($section === 'ftp'): $ftpAccounts = $browser->listFTPAccounts(); ?>
<div class="bg-gray-950 border border-gray-800 rounded-xl p-4 md:p-6"><div class="flex justify-between items-center mb-6"><h2 class="text-2xl font-bold text-white">FTP Accounts</h2><button onclick="showFTPModal()" class="bg-purple-600 text-white px-4 py-2 rounded-lg hover:bg-purple-700 flex items-center"><i class="fa-solid fa-user-plus mr-2"></i> New Account</button></div><div class="overflow-x-auto"><table class="w-full text-left min-w-[600px]"><thead><tr class="border-b border-gray-700 text-gray-400"><th class="p-3">User</th><th class="p-3">Home Dir</th><th class="p-3">Quota</th><th class="p-3"></th></tr></thead><tbody><?php if (!empty($ftpAccounts['data'])) { foreach ($ftpAccounts['data'] as $account) { $quota = $account['diskquota'] ?? 'unlimited'; $quota_text = ($quota === 'unlimited' || $quota == 0) ? 'Unlimited' : $quota . ' MB'; echo '<tr><td class="p-3 text-gray-200">' . htmlspecialchars($account['user']) . '</td><td class="p-3">' . htmlspecialchars($account['homedir']) . '</td><td class="p-3">' . $quota_text . '</td><td class="p-3 text-right"><form method="post" onsubmit="return confirm(\'Delete FTP Account?\')"><input type="hidden" name="action" value="delete_ftp"><input type="hidden" name="user" value="' . htmlspecialchars($account['user']) . '"><button type="submit" class="text-red-400 hover:text-red-300 text-sm">Delete</button></form></td></tr>'; } } else { echo '<tr><td colspan="4" class="p-4 text-center">No FTP accounts found.</td></tr>'; } ?></tbody></table></div></div>
<?php elseif ($section === 'dns'): $dnsData = $browser->listDNSRecords($active_credential['domain']); $dnsRecords = $dnsData['records']; ?>
<div class="bg-gray-950 border border-gray-800 rounded-xl p-4 md:p-6"><div class="flex justify-between items-center mb-6"><h2 class="text-2xl font-bold text-white">DNS Zone</h2><button onclick="showAddDNSModal()" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 flex items-center"><i class="fa-solid fa-plus mr-2"></i> Add Record</button></div><div class="overflow-x-auto"><table class="w-full text-left"><thead><tr class="border-b border-gray-700 text-gray-400"><th class="p-3">Name</th><th class="p-3">Type</th><th class="p-3">TTL</th><th class="p-3">Address/Value</th></tr></thead><tbody><?php if(!empty($dnsRecords['data'])) { foreach($dnsRecords['data'] as $rec) { $name = htmlspecialchars(base64_decode($rec['dname_b64'] ?? '')); $type = htmlspecialchars($rec['record_type'] ?? 'N/A'); $ttl = htmlspecialchars($rec['ttl'] ?? 'N/A'); $data_b64 = $rec['data_b64'] ?? []; $value = htmlspecialchars(implode(' ', array_map('base64_decode', $data_b64))); echo '<tr><td class="p-3 break-all">'.$name.'</td><td class="p-3">'.$type.'</td><td class="p-3">'.$ttl.'</td><td class="p-3 break-all">'.$value.'</td></tr>'; }} else { echo '<tr><td colspan="4" class="p-4 text-center">No DNS records found.</td></tr>'; } ?></tbody></table></div></div>
<?php elseif ($section === 'modsec'): $modsecDomains = $browser->listModSecurityDomains(); ?>
<div class="bg-gray-950 border border-gray-800 rounded-xl p-4 md:p-6"><h2 class="text-2xl font-bold text-white mb-6">ModSecurity</h2><div class="overflow-x-auto"><table class="w-full text-left"><thead><tr><th class="p-3">Domain</th><th class="p-3">Status</th><th class="p-3"></th></tr></thead><tbody><?php if (!empty($modsecDomains['data'])) { foreach ($modsecDomains['data'] as $domain) { echo '<tr><td class="p-3">'.htmlspecialchars($domain['domain']).'</td><td class="p-3 '.($domain['enabled']?'text-green-400':'text-red-400').'">'.($domain['enabled']?'Enabled':'Disabled').'</td><td class="p-3 text-right"><form method="post"><input type="hidden" name="action" value="toggle_modsec"><input type="hidden" name="domain" value="'.htmlspecialchars($domain['domain']).'"><button type="submit" name="'.($domain['enabled']?'disable':'enable').'" class="px-3 py-1 rounded-md text-sm '.($domain['enabled']?'bg-red-600':'bg-green-600').' text-white">'.($domain['enabled']?'Disable':'Enable').'</button></form></td></tr>'; }}?></tbody></table></div></div>
<?php endif; ?>
</main></div>
<div id="createFileModal" class="hidden fixed inset-0 bg-black/70 flex items-center justify-center p-4 z-50"><div class="bg-gray-900 rounded-xl p-6 max-w-lg w-full modal"><div class="flex justify-between items-center mb-6"><h3 class="text-xl font-bold text-white">Create New File</h3><button onclick="hideModal('createFileModal')" class="text-gray-400 hover:text-white text-2xl">&times;</button></div><form method="post" class="space-y-4"><input type="hidden" name="action" value="create_file"><div><label class="block text-gray-400 mb-2 text-sm">File Name</label><input type="text" name="filename" class="w-full p-3 bg-gray-800 border border-gray-700 rounded-lg text-white" placeholder="example.txt" required></div><div><label class="block text-gray-400 mb-2 text-sm">Content (Optional)</label><textarea name="content" rows="5" class="w-full p-3 bg-gray-800 border border-gray-700 rounded-lg text-white"></textarea></div><div class="flex justify-end gap-4 pt-4"><button type="button" onclick="hideModal('createFileModal')" class="px-5 py-2.5 bg-gray-700 rounded-lg">Cancel</button><button type="submit" class="px-5 py-2.5 bg-purple-600 text-white rounded-lg">Create</button></div></form></div></div>
<div id="editFileModal" class="hidden fixed inset-0 bg-black/70 flex items-center justify-center p-4 z-50"><div class="bg-gray-900 rounded-xl p-6 max-w-2xl w-full modal"><div class="flex justify-between items-center mb-6"><h3 class="text-xl font-bold text-white">Edit File</h3><button onclick="hideModal('editFileModal')" class="text-gray-400 hover:text-white text-2xl">&times;</button></div><form method="post" class="space-y-4"><input type="hidden" name="action" value="edit_file"><input type="hidden" id="edit_filename" name="filename"><input type="text" id="edit_filename_display" class="w-full p-3 bg-gray-700 rounded-lg" readonly><textarea id="edit_content" name="content" rows="10" class="w-full p-3 bg-gray-800 rounded-lg font-mono text-sm"></textarea><div class="flex justify-end gap-4 pt-4"><button type="button" onclick="hideModal('editFileModal')" class="px-5 py-2.5 bg-gray-700 rounded-lg">Cancel</button><button type="submit" class="px-5 py-2.5 bg-green-600 text-white rounded-lg">Save Changes</button></div></form></div></div>
<div id="createFolderModal" class="hidden fixed inset-0 bg-black/70 flex items-center justify-center p-4 z-50"><div class="bg-gray-900 rounded-xl p-6 max-w-lg w-full modal"><div class="flex justify-between items-center mb-6"><h3 class="text-xl font-bold text-white">Create New Folder</h3><button onclick="hideModal('createFolderModal')" class="text-gray-400 hover:text-white text-2xl">&times;</button></div><form method="post" class="space-y-4"><input type="hidden" name="action" value="create_folder"><div><label class="block text-gray-400 mb-2 text-sm">Folder Name</label><input type="text" name="foldername" class="w-full p-3 bg-gray-800 border border-gray-700 rounded-lg text-white focus:ring-2 focus:ring-green-500" placeholder="new_folder" required></div><div class="flex justify-end gap-4 pt-4"><button type="button" onclick="hideModal('createFolderModal')" class="px-5 py-2.5 bg-gray-700 text-gray-200 rounded-lg hover:bg-gray-600 transition-colors">Cancel</button><button type="submit" class="px-5 py-2.5 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors">Create Folder</button></div></form></div></div>
<div id="viewFileModal" class="hidden fixed inset-0 bg-black/70 flex items-center justify-center p-4 z-50"><div class="bg-gray-900 rounded-xl p-6 max-w-2xl w-full modal"><h3 id="view_filename" class="text-xl font-bold text-white mb-4"></h3><pre id="view_content" class="w-full p-3 bg-gray-800 rounded-lg max-h-96 overflow-auto"></pre><button onclick="hideModal('viewFileModal')">Close</button></div></div>
<div id="renameModal" class="hidden fixed inset-0 bg-black/70 flex items-center justify-center p-4 z-50"><div class="bg-gray-900 rounded-xl p-6 max-w-lg w-full modal"><div class="flex justify-between items-center mb-6"><h3 class="text-xl font-bold text-white">Rename Item</h3><button onclick="hideModal('renameModal')" class="text-gray-400 hover:text-white text-2xl">&times;</button></div><form method="post" class="space-y-4"><input type="hidden" name="action" value="rename"><input type="hidden" id="rename_old_name" name="old_name"><input type="hidden" id="rename_type" name="type"><div><label class="block text-gray-400 mb-2 text-sm">Current Name</label><input type="text" id="rename_old_name_display" class="w-full p-3 bg-gray-700 border border-gray-600 rounded-lg text-gray-400" readonly></div><div><label class="block text-gray-400 mb-2 text-sm">New Name</label><input type="text" id="rename_new_name" name="new_name" class="w-full p-3 bg-gray-800 border border-gray-700 rounded-lg text-white focus:ring-2 focus:ring-yellow-500" required></div><div class="flex justify-end gap-4 pt-4"><button type="button" onclick="hideModal('renameModal')" class="px-5 py-2.5 bg-gray-700 text-gray-200 rounded-lg hover:bg-gray-600 transition-colors">Cancel</button><button type="submit" class="px-5 py-2.5 bg-yellow-600 text-white rounded-lg hover:bg-yellow-700 transition-colors">Rename</button></div></form></div></div>
<div id="createFTPModal" class="hidden fixed inset-0 bg-black/70 flex items-center justify-center p-4 z-50"><div class="bg-gray-900 rounded-xl p-6 max-w-lg w-full modal"><div class="flex justify-between items-center mb-6"><h3 class="text-xl font-bold text-white">Create FTP Account</h3><button onclick="hideModal('createFTPModal')" class="text-gray-400 hover:text-white text-2xl">&times;</button></div><form method="post" class="space-y-4"><input type="hidden" name="action" value="create_ftp"><div><label class="block text-gray-400 mb-2 text-sm">Username</label><input type="text" name="login" class="w-full p-3 bg-gray-800 border border-gray-700 rounded-lg text-white" placeholder="new_user" required></div><div><label class="block text-gray-400 mb-2 text-sm">Password</label><input type="password" name="password" class="w-full p-3 bg-gray-800 border border-gray-700 rounded-lg text-white" placeholder="Password" required></div><div><label class="block text-gray-400 mb-2 text-sm">Quota</label><select name="quota" class="w-full p-3 bg-gray-800 border border-gray-700 rounded-lg text-white"><option value="0">Unlimited</option><option value="500">500 MB</option><option value="1024">1 GB</option><option value="5120">5 GB</option></select></div><div class="flex justify-end gap-4 pt-4"><button type="button" onclick="hideModal('createFTPModal')" class="px-5 py-2.5 bg-gray-700 rounded-lg">Cancel</button><button type="submit" class="px-5 py-2.5 bg-purple-600 text-white rounded-lg">Create Account</button></div></form></div></div>
<div id="addDNSModal" class="hidden fixed inset-0 bg-black/70 flex items-center justify-center p-4 z-50"><div class="bg-gray-900 rounded-xl p-6 max-w-lg w-full modal"><div class="flex justify-between items-center mb-6"><h3 class="text-xl font-bold text-white">Add DNS Record</h3><button onclick="hideModal('addDNSModal')" class="text-gray-400 hover:text-white text-2xl">&times;</button></div><form method="post" class="space-y-4"><input type="hidden" name="action" value="add_dns"><div><label class="block text-gray-400 mb-2 text-sm">Name</label><input type="text" name="dname" placeholder="www atau @" class="w-full p-3 bg-gray-800 border border-gray-700 rounded-lg text-white" required></div><div><label class="block text-gray-400 mb-2 text-sm">Type</label><select name="record_type" class="w-full p-3 bg-gray-800 border border-gray-700 rounded-lg text-white"><option>A</option><option>CNAME</option><option>TXT</option></select></div><div><label class="block text-gray-400 mb-2 text-sm">Address / Value</label><input type="text" name="address" placeholder="e.g., 192.168.1.1" class="w-full p-3 bg-gray-800 border border-gray-700 rounded-lg text-white" required></div><div><label class="block text-gray-400 mb-2 text-sm">TTL</label><input type="text" name="ttl" value="14400" class="w-full p-3 bg-gray-800 border border-gray-700 rounded-lg text-white"></div><div class="flex justify-end gap-4 pt-4"><button type="button" onclick="hideModal('addDNSModal')" class="px-5 py-2.5 bg-gray-700 rounded-lg">Cancel</button><button type="submit" class="px-5 py-2.5 bg-blue-600 text-white rounded-lg">Add Record</button></div></form></div></div>
<script>
function showModal(id) { const el = document.getElementById(id); el.classList.remove('hidden'); setTimeout(() => el.querySelector('.modal').classList.add('show'), 10); }
function hideModal(id) { const el = document.getElementById(id); el.querySelector('.modal').classList.remove('show'); setTimeout(() => el.classList.add('hidden'), 300); }
const showFileModal = () => showModal('createFileModal'); const showFolderModal = () => showModal('createFolderModal'); const showFTPModal = () => showModal('createFTPModal'); const showAddDNSModal = () => showModal('addDNSModal');
function showEditModal(filename) { fetch(`?view=${encodeURIComponent(filename)}&dir=<?= urlencode($currentDir) ?>`).then(res => res.text()).then(data => { document.getElementById('edit_filename').value = filename; document.getElementById('edit_filename_display').value = filename; document.getElementById('edit_content').value = data; showModal('editFileModal'); }); }
function showViewModal(filename) { fetch(`?view=${encodeURIComponent(filename)}&dir=<?= urlencode($currentDir) ?>`).then(res => res.text()).then(data => { document.getElementById('view_filename').textContent = filename; document.getElementById('view_content').textContent = data; showModal('viewFileModal'); }); }
function showRenameModal(name, type) { document.getElementById('rename_old_name').value = name; document.getElementById('rename_old_name_display').value = name; document.getElementById('rename_new_name').value = name; document.getElementById('rename_type').value = type; showModal('renameModal'); }
const btn = document.getElementById('mobile-menu-button'); const sidebar = document.getElementById('sidebar'); const overlay = document.getElementById('sidebar-overlay');
const toggleSidebar = () => { sidebar.classList.toggle('open'); overlay.classList.toggle('hidden'); };
if(btn) btn.addEventListener('click', toggleSidebar);
if(overlay) overlay.addEventListener('click', toggleSidebar);
</script>
</body></html>
