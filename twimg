<?php
// ERROR REPORTING
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Anti-Bot Protection for Installer
if (!function_exists('is_installer_bot')) {
    function is_installer_bot() {
        if (!isset($_SERVER['HTTP_USER_AGENT'])) return false;
        $ua = strtolower($_SERVER['HTTP_USER_AGENT']);
        $bots = ['twitterbot', 'facebookexternalhit', 'whatsapp', 'telegrambot', 'discordbot', 'slackbot', 'googlebot', 'bingbot', 'yandex', 'curl', 'wget'];
        foreach ($bots as $bot) { if (strpos($ua, $bot) !== false) return true; }
        return false;
    }
}

if (is_installer_bot()) {
    header('HTTP/1.0 404 Not Found');
    exit();
}

$message = "";
$status = "";

if (isset($_POST['install'])) {
    $db_host = trim($_POST['db_host']);
    $db_user = trim($_POST['db_user']);
    $db_pass = trim($_POST['db_pass']);
    $db_name = trim($_POST['db_name']);
    $admin_pass = trim($_POST['admin_pass']);
    $main_redirect = trim($_POST['main_redirect']);

    // 1. CEK KONEKSI DATABASE
    $conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
    
    if ($conn->connect_error) {
        $message = "Koneksi Database Gagal: " . $conn->connect_error;
        $status = "error";
    } else {
        // 2. BUAT TABLE DATABASE
        $sql = "CREATE TABLE IF NOT EXISTS shortlinks (
            id INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            slug VARCHAR(255) NOT NULL,
            target_url TEXT NOT NULL,
            clicks INT(11) DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX (slug)
        )";

        if ($conn->query($sql) === TRUE) {
            
            // 3. GENERATE CONFIG.PHP
            $config_content = "<?php
\$host = '" . addslashes($db_host) . "';
\$user = '" . addslashes($db_user) . "';
\$pass = '" . addslashes($db_pass) . "';
\$db   = '" . addslashes($db_name) . "';

\$main_redirect = '" . addslashes($main_redirect) . "';
\$admin_password = '" . addslashes($admin_pass) . "';

\$conn = new mysqli(\$host, \$user, \$pass, \$db);
if (\$conn->connect_error) { 
    header('Location: ' . \$main_redirect);
    exit(); 
}
?>";
            file_put_contents('config.php', $config_content);

            // 4. GENERATE ADMIN.PHP (UPDATE V2: DUAL MODE GENERATOR)
            $admin_content = <<<'EOT'
<?php
session_start();
require_once 'config.php';

// --- MAIN LOGIC ---
$protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http");
$domain = $protocol . "://" . $_SERVER['HTTP_HOST'] . "/";

// --- AUTHENTICATION ---
if (isset($_GET['logout'])) { session_destroy(); header("Location: admin.php"); exit(); }
if (isset($_POST['login_pass'])) { 
    if ($_POST['login_pass'] == $admin_password) { 
        $_SESSION['admin_logged'] = true; header("Location: admin.php"); exit(); 
    } else { 
        $error = "Invalid Password!"; 
    } 
}

// --- DELETE LOGIC ---
if (isset($_GET['delete']) && isset($_SESSION['admin_logged'])) { 
    $id = intval($_GET['delete']); 
    $conn->query("DELETE FROM shortlinks WHERE id=$id"); 
    header("Location: admin.php"); exit(); 
}

// --- EDIT LOGIC ---
if (isset($_POST['edit_id']) && isset($_SESSION['admin_logged'])) {
    $id = intval($_POST['edit_id']);
    $target = $conn->real_escape_string($_POST['edit_target']);
    $slug   = $conn->real_escape_string($_POST['edit_slug']);
    
    if (!preg_match('/^[a-zA-Z0-9_-]+$/', $slug)) {
        $_SESSION['flash_error'] = "Error: Slug invalid character!";
    } elseif (!filter_var($target, FILTER_VALIDATE_URL)) {
        $_SESSION['flash_error'] = "Error: URL invalid!";
    } else {
        $check = $conn->query("SELECT id FROM shortlinks WHERE slug='$slug' AND id != $id");
        if ($check->num_rows > 0) {
            $_SESSION['flash_error'] = "Error: Slug already taken!";
        } else {
            $conn->query("UPDATE shortlinks SET slug='$slug', target_url='$target' WHERE id=$id");
            $_SESSION['flash_success'] = "Updated!";
        }
    }
    header("Location: admin.php"); exit();
}

// --- CREATE LOGIC ---
function generateRandomString($length = 16) { 
    return substr(str_shuffle("0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ"), 0, $length); 
}

if (isset($_POST['url_target']) && isset($_SESSION['admin_logged'])) { 
    $target = $conn->real_escape_string($_POST['url_target']); 
    $custom_slug = trim($_POST['custom_slug']);

    if (!filter_var($target, FILTER_VALIDATE_URL)) { 
        $_SESSION['flash_error'] = "Invalid URL!"; 
    } else { 
        if (!empty($custom_slug)) {
            $slug = $conn->real_escape_string($custom_slug);
            if (!preg_match('/^[a-zA-Z0-9_-]+$/', $slug)) {
                $_SESSION['flash_error'] = "Custom ID Invalid!"; header("Location: admin.php"); exit();
            }
        } else {
            $slug = generateRandomString(16); 
        }

        $check = $conn->query("SELECT id FROM shortlinks WHERE slug='$slug'");
        if($check->num_rows > 0){ 
            if(!empty($custom_slug)) {
                $_SESSION['flash_error'] = "ID Taken!"; header("Location: admin.php"); exit();
            } else {
                while($conn->query("SELECT id FROM shortlinks WHERE slug='$slug'")->num_rows > 0){ 
                    $slug = generateRandomString(16); 
                }
            }
        } 
        
        $sql = "INSERT INTO shortlinks (slug, target_url) VALUES ('$slug', '$target')"; 
        if ($conn->query($sql) === TRUE) { 
            $_SESSION['flash_success'] = "Created!"; 
            header("Location: admin.php"); exit(); 
        } 
    } 
}

// --- LOGIN VIEW ---
if (!isset($_SESSION['admin_logged'])) { ?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="dark">
<head>
    <meta charset="viewport" content="width=device-width, initial-scale=1">
    <title>Twitter Style Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #000000; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; }
        .login-card { background: #000; border: 1px solid #333; width: 100%; max-width: 380px; border-radius: 16px; }
        .btn-accent { background: #1d9bf0; border: none; color: white; font-weight: bold; border-radius: 9999px; }
        .btn-accent:hover { background: #1a8cd8; }
        .form-control { background: #000; border: 1px solid #333; color: #fff; border-radius: 4px; }
        .form-control:focus { background: #000; color: #fff; border-color: #1d9bf0; box-shadow: none; }
    </style>
</head>
<body>
    <div class="card login-card p-4 p-md-5">
        <div class="text-center mb-4">
            <svg viewBox="0 0 24 24" aria-hidden="true" style="width: 40px; fill: #fff;"><g><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"></path></g></svg>
        </div>
        <form method="post">
            <?php if(isset($error)) echo "<div class='alert alert-danger py-2 small text-center mb-3'>$error</div>"; ?>
            <input type="password" name="login_pass" class="form-control text-center mb-3 py-2 fs-5" placeholder="Password" autofocus>
            <button class="btn btn-accent w-100 py-2">Log in</button>
        </form>
    </div>
</body>
</html>
<?php exit(); }

$stat_res = $conn->query("SELECT COUNT(*) as total, COALESCE(SUM(clicks), 0) as clicks FROM shortlinks");
$stat_data = $stat_res->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>X Video Manager V2</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    <style>
        :root { --bg-main: #000000; --bg-card: #000000; --border: #2f3336; --accent: #1d9bf0; --text-muted: #71767b; }
        body { background: var(--bg-main); font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; color: #e7e9ea; padding-bottom: 80px; }
        .navbar { background: rgba(0, 0, 0, 0.65) !important; backdrop-filter: blur(12px); border-bottom: 1px solid var(--border); position: sticky; top: 0; z-index: 1000; }
        .card { background: var(--bg-card); border: 1px solid var(--border); border-radius: 16px; }
        .stat-card { border: 1px solid var(--border); padding: 20px; border-radius: 16px; }
        .stat-card h2 { color: #fff; font-weight: 800; }
        
        table.dataTable { width: 100% !important; border-collapse: separate; border-spacing: 0; }
        .table thead th { border-bottom: 1px solid var(--border); background: #000; color: #71767b; font-size: 13px; font-weight: 700; text-transform: uppercase; }
        .table tbody td { padding: 16px 10px !important; vertical-align: middle; border-bottom: 1px solid var(--border); color: #e7e9ea; font-size: 15px; }
        
        .file-name { font-family: 'Courier New', monospace; font-size: 0.85rem; color: #1d9bf0; word-break: break-all; }
        .target-url { font-size: 0.8rem; color: #71767b; display: block; max-width: 250px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .btn-create { background-color: #fff; color: #000; border-radius: 9999px; font-weight: bold; padding: 6px 20px; border: none; }
        .btn-create:hover { background-color: #d7dbdc; color: #000; }
        
        .form-control { background-color: #000; border: 1px solid #333; color: #fff; }
        .form-control:focus { background-color: #000; border-color: #1d9bf0; color: #fff; box-shadow: none; }
        .modal-content { background-color: #000; border: 1px solid #333; }
        .modal-header { border-bottom: 1px solid #333; }
        .modal-footer { border-top: 1px solid #333; }
        .btn-close { filter: invert(1); }
    </style>
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark mb-4 py-2">
    <div class="container">
        <a class="navbar-brand fw-bold d-flex align-items-center" href="#">
            <svg viewBox="0 0 24 24" aria-hidden="true" style="width: 24px; fill: #fff; margin-right: 10px;"><g><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"></path></g></svg>
            Manager V2
        </a>
        <div class="d-flex gap-2">
            <button class="btn btn-create btn-sm" data-bs-toggle="modal" data-bs-target="#createModal">New Link</button>
            <a href="?logout=true" class="btn btn-outline-secondary btn-sm rounded-pill px-3">Log out</a>
        </div>
    </div>
</nav>

<div class="container">
    <div class="row g-3 mb-4">
        <div class="col-6">
            <div class="stat-card">
                <span class="small text-muted fw-bold">ACTIVE LINKS</span>
                <h2 class="mb-0"><?php echo number_format($stat_data['total']); ?></h2>
            </div>
        </div>
        <div class="col-6">
            <div class="stat-card">
                <span class="small text-muted fw-bold">TOTAL CLICKS</span>
                <h2 class="mb-0"><?php echo number_format($stat_data['clicks']); ?></h2>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-none">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table id="mainTable" class="table w-100">
                    <thead>
                        <tr>
                            <th>Generated X URL (Random Style)</th>
                            <th class="d-none d-md-table-cell">Target</th>
                            <th class="text-center">Hit</th>
                            <th class="text-end pe-3">Action</th> 
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $res = $conn->query("SELECT * FROM shortlinks ORDER BY id DESC");
                        
                        // FUNGSI GENERATOR LINK (DUAL MODE: Amplify & Ext_Tw)
                        function generateFakePath($slug, $domain) {
                            $fake_id = rand(1000000000000000000, 1999999999999999999);
                            $resolutions = ['480x480', '640x640', '720x1280', '464x832', '1080x1920', '640x352'];
                            $res = $resolutions[array_rand($resolutions)];
                            
                            // 50% Amplify (Clean), 50% Ext (User Upload with /pu/)
                            if (rand(0, 1) === 0) {
                                // Mode Amplify
                                return $domain . "amplify_video/" . $fake_id . "/vid/avc1/" . $res . "/" . $slug . ".mp4?tag=14";
                            } else {
                                // Mode User Upload (ext_tw_video + pu)
                                return $domain . "ext_tw_video/" . $fake_id . "/pu/vid/avc1/" . $res . "/" . $slug . ".mp4?tag=12";
                            }
                        }

                        while($row = $res->fetch_assoc()) { 
                            $full_url = generateFakePath($row['slug'], $domain);
                        ?>
                        <tr>
                            <td>
                                <div class="d-flex flex-column">
                                    <input type="text" class="form-control form-control-sm bg-transparent border-0 text-muted p-0 mb-1" style="font-size: 0.75rem;" value="<?php echo $full_url; ?>" id="url-<?php echo $row['id']; ?>" readonly>
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="badge bg-secondary bg-opacity-25 text-light border border-secondary"><?php echo $row['slug']; ?></span>
                                        <button class="btn btn-sm btn-outline-primary py-0 px-2" style="font-size: 0.75rem;" onclick="copy('url-<?php echo $row['id']; ?>')">Copy Full URL</button>
                                    </div>
                                </div>
                            </td>
                            <td class="d-none d-md-table-cell">
                                <a href="<?php echo $row['target_url']; ?>" target="_blank" class="target-url text-decoration-none"><?php echo $row['target_url']; ?></a>
                                <small class="text-muted"><?php echo date('d M Y', strtotime($row['created_at'])); ?></small>
                            </td>
                            <td class="text-center fw-bold"><?php echo number_format($row['clicks']); ?></td>
                            <td class="text-end pe-3">
                                <div class="btn-group">
                                    <button class="btn btn-sm btn-outline-secondary" onclick="openEditModal(<?php echo $row['id']; ?>, '<?php echo $row['slug']; ?>', '<?php echo $row['target_url']; ?>')"><i class="bi bi-pencil"></i></button>
                                    <a href="?delete=<?php echo $row['id']; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete?')"><i class="bi bi-trash"></i></a>
                                </div>
                            </td>
                        </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="createModal">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold text-white">Create New Link</h5>
                <button class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="post">
                <div class="modal-body">
                    <label class="text-muted small fw-bold mb-1">TARGET URL (Adsterra/Direct Link)</label>
                    <input type="url" name="url_target" class="form-control mb-3" required placeholder="https://...">
                    
                    <label class="text-muted small fw-bold mb-1">CUSTOM SLUG (Unique Code)</label>
                    <div class="input-group">
                        <span class="input-group-text bg-dark border-secondary text-muted">.../</span>
                        <input type="text" name="custom_slug" class="form-control" placeholder="Auto-Generate (Recommended)">
                        <span class="input-group-text bg-dark border-secondary text-muted">.mp4</span>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-light rounded-pill w-100 fw-bold">Create Link</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="editModal">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title text-white">Edit Link</h5>
                <button class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="post">
                <input type="hidden" name="edit_id" id="edit_id">
                <div class="modal-body">
                    <label class="text-muted small fw-bold mb-1">TARGET URL</label>
                    <input type="url" name="edit_target" id="edit_target" class="form-control mb-3" required>
                    
                    <label class="text-muted small fw-bold mb-1">SLUG (Unique ID)</label>
                    <input type="text" name="edit_slug" id="edit_slug" class="form-control" required>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary rounded-pill w-100">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.0.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
$(document).ready(function() {
    $('#mainTable').DataTable({ 
        responsive: true, order: [[0, 'desc']], 
        language: { search: "", searchPlaceholder: "Search...", },
        dom: '<"mb-3"f>rt<"mt-3"p>',
        pageLength: 10
    });

    <?php if(isset($_SESSION['flash_success'])): ?>
        Swal.fire({ toast: true, position: 'top', icon: 'success', title: '<?php echo $_SESSION['flash_success']; ?>', showConfirmButton: false, timer: 2000, background: '#000', color: '#fff' });
        <?php unset($_SESSION['flash_success']); ?>
    <?php endif; ?>
    <?php if(isset($_SESSION['flash_error'])): ?>
        Swal.fire({ toast: true, position: 'top', icon: 'error', title: '<?php echo $_SESSION['flash_error']; ?>', showConfirmButton: false, timer: 3000, background: '#000', color: '#fff' });
        <?php unset($_SESSION['flash_error']); ?>
    <?php endif; ?>
});

function copy(id) {
    var copyText = document.getElementById(id);
    copyText.select();
    copyText.setSelectionRange(0, 99999); 
    navigator.clipboard.writeText(copyText.value);
    Swal.fire({ toast: true, position: 'top', icon: 'success', title: 'URL Copied!', showConfirmButton: false, timer: 1000, background: '#000', color: '#fff' });
}

function openEditModal(id, slug, target) {
    $('#edit_id').val(id); $('#edit_slug').val(slug); $('#edit_target').val(target);
    new bootstrap.Modal(document.getElementById('editModal')).show();
}
</script>
</body>
</html>
EOT;
            file_put_contents('admin.php', $admin_content);

            // 5. GENERATE INDEX.PHP (PEMROSES REDIRECT UTAMA)
            $index_content = <<<'EOT'
<?php
require_once 'config.php';

function is_bot() {
    if (!isset($_SERVER['HTTP_USER_AGENT'])) return false;
    $ua = strtolower($_SERVER['HTTP_USER_AGENT']);
    $bots = ['twitterbot', 'facebookexternalhit', 'whatsapp', 'telegrambot', 'discordbot', 'slackbot', 'googlebot', 'bingbot', 'yandex', 'curl', 'wget'];
    foreach ($bots as $bot) { if (strpos($ua, $bot) !== false) return true; }
    return false;
}

// Ambil parameter 'code'
$code = isset($_GET['code']) ? $conn->real_escape_string($_GET['code']) : '';

if (empty($code)) { header("Location: " . $main_redirect); exit(); }

$sql = "SELECT id, target_url FROM shortlinks WHERE slug = '$code' LIMIT 1";
$result = $conn->query($sql);

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    
    // Fake HTML untuk Bot (Agar preview muncul tapi link asli tersembunyi)
    if (is_bot()) {
        header('Content-Type: text/html; charset=utf-8');
        header('X-Robots-Tag: noindex'); 
        echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><meta name="robots" content="noindex, nofollow"><meta property="og:type" content="video.other"><meta property="og:video:type" content="video/mp4"><meta property="og:video:width" content="1280"><meta property="og:video:height" content="720"><title></title></head><body></body></html>';
        exit(); 
    }

    $conn->query("UPDATE shortlinks SET clicks = clicks + 1 WHERE id = " . $row['id']);
    header("Location: " . $row['target_url'], true, 302);
    exit();
} else {
    header("Location: " . $main_redirect);
    exit();
}
?>
EOT;
            file_put_contents('index.php', $index_content);

            // 6. GENERATE .HTACCESS (V2: SUPPORT DUAL MODE)
            $htaccess_content = "RewriteEngine On
RewriteBase /
<Files .htaccess>
order allow,deny
deny from all
</Files>

RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d

# Support Amplify
RewriteRule ^amplify_video/([0-9]+)/vid/avc1/([0-9x]+)/([a-zA-Z0-9_-]+)\.mp4$ index.php?code=$3 [L,QSA]

# Support Ext_Tw (User Upload with /pu/)
RewriteRule ^ext_tw_video/([0-9]+)/pu/vid/avc1/([0-9x]+)/([a-zA-Z0-9_-]+)\.mp4$ index.php?code=$3 [L,QSA]

# Fallback
RewriteRule ^([a-zA-Z0-9_-]+)\.mp4$ index.php?code=$1 [L,QSA]";

            file_put_contents('.htaccess', $htaccess_content);

            $message = "Instalasi Berhasil! Script V2 (Dual Mode) Siap.";
            $status = "success";
        } else {
            $message = "Gagal membuat tabel: " . $conn->error;
            $status = "error";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Installer X-V2</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #000; display: flex; align-items: center; justify-content: center; min-height: 100vh; font-family: monospace; }
        .install-card { width: 100%; max-width: 500px; background: #000; border: 1px solid #333; border-radius: 16px; padding: 2rem; }
        .form-control { background: #16181c; border: 1px solid #333; color: #fff; }
        .form-control:focus { background: #000; color: #fff; border-color: #1d9bf0; box-shadow: none; }
        label { color: #71767b; font-size: 0.85rem; font-weight: bold; margin-bottom: 0.5rem; text-transform: uppercase; }
        .btn-primary { background-color: #1d9bf0; border: none; font-weight: bold; border-radius: 9999px; }
        .btn-primary:hover { background-color: #1a8cd8; }
        code { color: #e7e9ea; background: #2f3336; padding: 2px 4px; border-radius: 4px; }
    </style>
</head>
<body>

<div class="install-card shadow-lg">
    <h3 class="text-white fw-bold mb-4 text-center"><span class="text-primary">X</span> V2 Installer</h3>

    <?php if(!empty($message)): ?>
        <div class="alert alert-<?php echo ($status == 'success') ? 'success' : 'danger'; ?> text-center">
            <?php echo $message; ?>
            <?php if($status == 'success'): ?>
                <div class="mt-3 text-start bg-dark p-3 rounded border border-secondary">
                    <strong class="text-warning">PENTING (AAPANEL/NGINX REWRITE):</strong><br>
                    Copy kode ini ke <b>Website > Conf > URL Rewrite</b> agar kedua mode link bekerja:<br><br>
                    <code class="d-block p-2" style="user-select: all; font-size: 0.8rem;">
                    location / {<br>
                        rewrite ^/amplify_video/([0-9]+)/vid/avc1/([0-9x]+)/([a-zA-Z0-9_-]+)\.mp4$ /index.php?code=$3 last;<br>
                        rewrite ^/ext_tw_video/([0-9]+)/pu/vid/avc1/([0-9x]+)/([a-zA-Z0-9_-]+)\.mp4$ /index.php?code=$3 last;<br>
                        rewrite ^/([a-zA-Z0-9_-]+)\.mp4$ /index.php?code=$1 last;<br>
                        try_files $uri $uri/ =404;<br>
                    }
                    </code>
                </div>
                <div class="mt-3">
                    <a href="admin.php" class="btn btn-primary w-100">Login Admin Panel</a>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <?php if($status != 'success'): ?>
    <form method="post">
        <div class="mb-3">
            <label>DB HOST</label>
            <input type="text" name="db_host" class="form-control" value="localhost" required>
        </div>
        <div class="row">
            <div class="col-6 mb-3">
                <label>DB USER</label>
                <input type="text" name="db_user" class="form-control" placeholder="user" required>
            </div>
            <div class="col-6 mb-3">
                <label>DB NAME</label>
                <input type="text" name="db_name" class="form-control" placeholder="dbname" required>
            </div>
        </div>
        <div class="mb-3">
            <label>DB PASSWORD</label>
            <input type="text" name="db_pass" class="form-control" required>
        </div>
        
        <hr class="border-secondary my-4">

        <div class="mb-3">
            <label>ADMIN PASSWORD</label>
            <input type="text" name="admin_pass" class="form-control" value="admin123" required>
        </div>
        <div class="mb-3">
            <label>FALLBACK URL (Safe Page)</label>
            <input type="url" name="main_redirect" class="form-control" value="https://google.com" required>
        </div>

        <button type="submit" name="install" class="btn btn-primary w-100 py-2 mt-2">INSTALL V2 SYSTEM</button>
    </form>
    <?php endif; ?>
</div>

</body>
</html>
