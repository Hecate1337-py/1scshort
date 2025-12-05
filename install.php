<?php
// ERROR REPORTING
error_reporting(E_ALL);
ini_set('display_errors', 1);

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

            // 4. GENERATE ADMIN.PHP
            $admin_content = <<<'EOT'
<?php
session_start();
require_once 'config.php';

// --- MAIN LOGIC & AUTO CALCULATION ---
$protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http");
$domain = $protocol . "://" . $_SERVER['HTTP_HOST'] . "/";

$container_len = strlen($domain . ".mp4");
$target_total = 34;
$id_len_needed = $target_total - $container_len;
if ($id_len_needed < 4) { $id_len_needed = 4; }

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
    
    if (!preg_match('/^[a-zA-Z0-9-]+$/', $slug)) {
        $_SESSION['flash_error'] = "Error: ID invalid!";
    } elseif (!filter_var($target, FILTER_VALIDATE_URL)) {
        $_SESSION['flash_error'] = "Error: URL invalid!";
    } else {
        $check = $conn->query("SELECT id FROM shortlinks WHERE slug='$slug' AND id != $id");
        if ($check->num_rows > 0) {
            $_SESSION['flash_error'] = "Error: ID taken!";
        } else {
            $conn->query("UPDATE shortlinks SET slug='$slug', target_url='$target' WHERE id=$id");
            $_SESSION['flash_success'] = "Updated!";
        }
    }
    header("Location: admin.php"); exit();
}

// --- CREATE LOGIC ---
function generateRandomString($length) { 
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
            if (!preg_match('/^[a-zA-Z0-9-]+$/', $slug)) {
                $_SESSION['flash_error'] = "Custom ID Invalid!"; header("Location: admin.php"); exit();
            }
        } else {
            $slug = generateRandomString($id_len_needed); 
        }

        $check = $conn->query("SELECT id FROM shortlinks WHERE slug='$slug'");
        if($check->num_rows > 0){ 
            if(!empty($custom_slug)) {
                $_SESSION['flash_error'] = "ID Taken!"; header("Location: admin.php"); exit();
            } else {
                while($conn->query("SELECT id FROM shortlinks WHERE slug='$slug'")->num_rows > 0){ 
                    $slug = generateRandomString($id_len_needed); 
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
    <title>Admin Login</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #0f172a; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px; font-family: 'Segoe UI', sans-serif; }
        .login-card { background: #1e293b; border: 1px solid #334155; width: 100%; max-width: 380px; border-radius: 16px; }
        .btn-accent { background: #3b82f6; border: none; color: white; font-weight: 600; }
    </style>
</head>
<body>
    <div class="card login-card p-4 p-md-5">
        <form method="post">
            <h4 class="text-white fw-bold text-center mb-4">Admin Access</h4>
            <?php if(isset($error)) echo "<div class='alert alert-danger py-2 small text-center mb-3'>$error</div>"; ?>
            <input type="password" name="login_pass" class="form-control bg-dark border-secondary text-white text-center mb-3 py-2 fs-5" placeholder="••••••" autofocus>
            <button class="btn btn-accent w-100 py-2 rounded-3">Unlock Dashboard</button>
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>CDN Manager</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    <style>
        :root { --bg-main: #0f172a; --bg-card: #1e293b; --border: #334155; --accent: #3b82f6; --text-muted: #94a3b8; }
        body { background: var(--bg-main); font-family: 'Segoe UI', sans-serif; color: #f8fafc; padding-bottom: 80px; }
        .navbar { background: rgba(30, 41, 59, 0.95) !important; backdrop-filter: blur(10px); border-bottom: 1px solid var(--border); position: sticky; top: 0; z-index: 1000; }
        .card, .modal-content { background: var(--bg-card); border: 1px solid var(--border); border-radius: 12px; }
        .stat-card { background: linear-gradient(135deg, #1d4ed8 0%, #3b82f6 100%); border:none; border-radius:16px; position:relative; overflow:hidden; }
        .stat-card.green { background: linear-gradient(135deg, #047857 0%, #10b981 100%); }
        .stat-icon { position:absolute; right:-10px; bottom:-10px; font-size:5rem; opacity:0.2; transform:rotate(-15deg); }
        table.dataTable { width: 100% !important; table-layout: fixed !important; margin-top: 0 !important; }
        .table thead th { border-bottom: 1px solid var(--border); background: #1e293b; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.5px; white-space: nowrap; vertical-align: middle; }
        .table tbody td { padding: 12px 10px !important; vertical-align: middle; border-bottom: 1px solid #1f2937; white-space: nowrap; }
        .file-name { font-size: 0.9rem; font-weight: bold; color: #f8fafc; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .target-url { font-size: 0.8rem; color: #64748b; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; display: block; max-width: 100%; }
        .date-text { font-size: 0.75rem; color: #94a3b8; }
        .d-none-mobile { display: none; }
        @media (min-width: 768px) { .d-none-mobile { display: table-cell; } }
        .play-icon { width: 32px; height: 32px; background: #0f172a; border-radius: 6px; display: flex; align-items: center; justify-content: center; color: var(--accent); flex-shrink: 0; margin-right: 10px; }
        .btn-action { width: 40px; height: 40px; padding: 0; display: inline-flex; align-items: center; justify-content: center; border-radius: 8px; transition: all 0.2s; }
        .btn-action:hover { transform: translateY(-2px); filter: brightness(1.2); }
        .dropdown-menu { background-color: #1e293b; border: 1px solid #334155; }
        .dropdown-item { color: #cbd5e1; }
        .dropdown-item:hover { background-color: #334155; color: #fff; }
        .dropdown-item.text-danger:hover { background-color: #450a0a; }
        div.dataTables_wrapper div.dataTables_filter input { background: #0f172a; border: 1px solid var(--border); color: #fff; border-radius: 50px; padding: 6px 15px; width: 250px; }
        div.dataTables_wrapper div.dataTables_filter input:focus { border-color: var(--accent); outline: none; }
        .page-item .page-link { background-color: var(--bg-card); border-color: var(--border); color: var(--text-muted); border-radius: 6px; margin: 0 2px; }
        .page-item.active .page-link { background-color: var(--accent); border-color: var(--accent); color: white; }
        div.dataTables_wrapper div.dataTables_length select { background: #0f172a; border: 1px solid var(--border); color: #fff; border-radius: 6px; }
        div.dataTables_wrapper div.dataTables_info { color: #64748b; font-size: 0.85rem; padding-top: 1rem; }
    </style>
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark mb-4 py-3">
    <div class="container">
        <a class="navbar-brand fw-bold d-flex align-items-center" href="#">
            <i class="bi bi-hdd-network-fill text-primary me-2"></i> CDNPanel
        </a>
        <div class="d-flex gap-2">
            <button class="btn btn-primary btn-sm rounded-pill px-3" data-bs-toggle="modal" data-bs-target="#createModal"><i class="bi bi-plus-lg"></i> New</button>
            <a href="?logout=true" class="btn btn-dark btn-sm rounded-circle"><i class="bi bi-power"></i></a>
        </div>
    </div>
</nav>
<div class="container">
    <div class="row g-3 mb-4">
        <div class="col-6">
            <div class="card stat-card p-3 text-white shadow-sm h-100">
                <span class="small opacity-75 fw-bold">ACTIVE</span>
                <h2 class="fw-bold mb-0"><?php echo number_format($stat_data['total']); ?></h2>
                <i class="bi bi-link-45deg stat-icon"></i>
            </div>
        </div>
        <div class="col-6">
            <div class="card stat-card green p-3 text-white shadow-sm h-100">
                <span class="small opacity-75 fw-bold">CLICKS</span>
                <h2 class="fw-bold mb-0"><?php echo number_format($stat_data['clicks']); ?></h2>
                <i class="bi bi-bar-chart-fill stat-icon"></i>
            </div>
        </div>
    </div>
    <div class="card shadow-lg border-0">
        <div class="card-body p-3">
            <div class="table-responsive" style="overflow-x: hidden;">
                <table id="mainTable" class="table w-100">
                    <thead>
                        <tr>
                            <th class="ps-2" style="width: 25%;">File / Link</th>
                            <th class="d-none-mobile">Destination</th>
                            <th class="d-none-mobile" style="width: 15%;">Date</th>
                            <th class="text-center" style="width: 60px;">Hit</th>
                            <th class="text-end pe-2 menu-col"></th> 
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $res = $conn->query("SELECT * FROM shortlinks ORDER BY id DESC");
                        while($row = $res->fetch_assoc()) { 
                            $full = $domain.$row['slug'].".mp4"; 
                            $date = isset($row['created_at']) ? date('d M Y', strtotime($row['created_at'])) : '-';
                        ?>
                        <tr>
                            <td class="ps-2">
                                <div class="d-flex align-items-center">
                                    <div class="play-icon"><i class="bi bi-play-fill fs-5"></i></div>
                                    <div style="min-width:0; width:100%;">
                                        <a href="<?php echo $full; ?>" target="_blank" class="text-decoration-none file-name d-block">/<?php echo $row['slug']; ?>.mp4</a>
                                        <span class="d-md-none file-url d-block" style="color:#64748b; font-size:0.7rem; overflow:hidden; text-overflow:ellipsis;"><?php echo $row['target_url']; ?></span>
                                        <input type="hidden" id="l-<?php echo $row['id']; ?>" value="<?php echo $full; ?>">
                                    </div>
                                </div>
                            </td>
                            <td class="d-none-mobile">
                                <div class="d-flex align-items-center text-muted">
                                    <i class="bi bi-arrow-return-right me-2 text-secondary"></i>
                                    <a href="<?php echo $row['target_url']; ?>" target="_blank" class="target-url text-decoration-none text-muted"><?php echo $row['target_url']; ?></a>
                                </div>
                            </td>
                            <td class="d-none-mobile"><span class="date-text"><i class="bi bi-calendar3 me-1"></i> <?php echo $date; ?></span></td>
                            <td class="text-center"><span class="badge bg-dark border border-secondary text-light rounded-pill px-2"><?php echo number_format($row['clicks']); ?></span></td>
                            <td class="text-end pe-2">
                                <div class="dropdown d-md-none">
                                    <button class="btn btn-sm btn-link text-muted p-0" type="button" data-bs-toggle="dropdown"><i class="bi bi-three-dots-vertical fs-5"></i></button>
                                    <ul class="dropdown-menu dropdown-menu-end shadow">
                                        <li><button class="dropdown-item gap-2 d-flex align-items-center" onclick="copy('l-<?php echo $row['id']; ?>')"><i class="bi bi-clipboard"></i> Copy</button></li>
                                        <li><button class="dropdown-item gap-2 d-flex align-items-center" onclick="openEditModal(<?php echo $row['id']; ?>, '<?php echo $row['slug']; ?>', '<?php echo $row['target_url']; ?>')"><i class="bi bi-pencil-square"></i> Edit</button></li>
                                        <li><hr class="dropdown-divider border-secondary"></li>
                                        <li><a href="?delete=<?php echo $row['id']; ?>" class="dropdown-item text-danger gap-2 d-flex align-items-center" onclick="return confirm('Delete?')"><i class="bi bi-trash"></i> Delete</a></li>
                                    </ul>
                                </div>
                                <div class="d-none d-md-flex gap-2 justify-content-end">
                                    <button class="btn btn-dark border-secondary text-info btn-action" onclick="copy('l-<?php echo $row['id']; ?>')" title="Copy"><i class="bi bi-clipboard"></i></button>
                                    <button class="btn btn-dark border-secondary text-warning btn-action" onclick="openEditModal(<?php echo $row['id']; ?>, '<?php echo $row['slug']; ?>', '<?php echo $row['target_url']; ?>')" title="Edit"><i class="bi bi-pencil-square"></i></button>
                                    <a href="?delete=<?php echo $row['id']; ?>" class="btn btn-danger btn-action" onclick="return confirm('Delete?')" title="Delete"><i class="bi bi-trash"></i></a>
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
            <div class="modal-header border-secondary">
                <h5 class="modal-title text-white">Create New</h5>
                <button class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="post">
                <div class="modal-body">
                    <label class="text-muted small fw-bold mb-1">TARGET URL</label>
                    <input type="url" name="url_target" class="form-control bg-dark text-white border-secondary mb-3" required placeholder="https://...">
                    <label class="text-muted small fw-bold mb-1">CUSTOM ID (Optional)</label>
                    <div class="input-group">
                        <span class="input-group-text bg-dark border-secondary text-muted">/</span>
                        <input type="text" name="custom_slug" class="form-control bg-dark text-white border-secondary" placeholder="Auto-generate">
                        <span class="input-group-text bg-dark border-secondary text-muted">.mp4</span>
                    </div>
                </div>
                <div class="modal-footer border-secondary">
                    <button type="submit" class="btn btn-primary w-100">Create Link</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="editModal">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header border-secondary">
                <h5 class="modal-title text-white">Edit Link</h5>
                <button class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="post">
                <input type="hidden" name="edit_id" id="edit_id">
                <div class="modal-body">
                    <label class="text-muted small fw-bold mb-1">TARGET URL</label>
                    <input type="url" name="edit_target" id="edit_target" class="form-control bg-dark text-white border-secondary mb-3" required>
                    <label class="text-muted small fw-bold mb-1">SHORT ID</label>
                    <div class="input-group">
                        <span class="input-group-text bg-dark border-secondary text-muted">/</span>
                        <input type="text" name="edit_slug" id="edit_slug" class="form-control bg-dark text-white border-secondary" required>
                        <span class="input-group-text bg-dark border-secondary text-muted">.mp4</span>
                    </div>
                </div>
                <div class="modal-footer border-secondary">
                    <button type="submit" class="btn btn-warning w-100">Save Changes</button>
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
    var table = $('#mainTable').DataTable({ 
        responsive: false, order: [[0, 'desc']], 
        language: { search: "", searchPlaceholder: "Search files...", lengthMenu: "_MENU_ per page", info: "Showing _START_ to _END_ of _TOTAL_" },
        dom: '<"row align-items-center mb-2"<"col-md-6"l><"col-md-6"f>>rt<"row align-items-center mt-2"<"col-md-6 small text-muted"i><"col-md-6"p>>',
        pageLength: 10, autoWidth: false,
        columnDefs: [ { width: "60px", targets: 3 }, { className: "menu-col", targets: 4 } ]
    });
    function adjustColumns() {
        if ($(window).width() < 768) { $('.menu-col').css('width', '30px'); } else { $('.menu-col').css('width', '150px'); }
    }
    adjustColumns(); $(window).resize(adjustColumns);
    <?php if(isset($_SESSION['flash_success'])): ?>
        Swal.fire({ toast: true, position: 'top', icon: 'success', title: '<?php echo $_SESSION['flash_success']; ?>', showConfirmButton: false, timer: 2000, background: '#1e293b', color: '#fff' });
        <?php unset($_SESSION['flash_success']); ?>
    <?php endif; ?>
    <?php if(isset($_SESSION['flash_error'])): ?>
        Swal.fire({ toast: true, position: 'top', icon: 'error', title: '<?php echo $_SESSION['flash_error']; ?>', showConfirmButton: false, timer: 3000, background: '#1e293b', color: '#fff' });
        <?php unset($_SESSION['flash_error']); ?>
    <?php endif; ?>
});
function copy(id) {
    navigator.clipboard.writeText(document.getElementById(id).value);
    Swal.fire({ toast: true, position: 'top', icon: 'success', title: 'Copied!', showConfirmButton: false, timer: 1000, background: '#1e293b', color: '#fff' });
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

            // 5. GENERATE INDEX.PHP (Redirect Logic)
            $index_content = <<<'EOT'
<?php
require_once 'config.php';

function is_bot() {
    if (!isset($_SERVER['HTTP_USER_AGENT'])) return false;
    $ua = strtolower($_SERVER['HTTP_USER_AGENT']);
    $bots = ['twitterbot', 'facebookexternalhit', 'whatsapp', 'telegrambot', 'discordbot', 'slackbot', 'googlebot', 'bingbot', 'yandex'];
    foreach ($bots as $bot) { if (strpos($ua, $bot) !== false) return true; }
    return false;
}

$code = isset($_GET['code']) ? $conn->real_escape_string($_GET['code']) : '';

if (empty($code)) { header("Location: " . $main_redirect); exit(); }

$sql = "SELECT id, target_url FROM shortlinks WHERE slug = '$code' LIMIT 1";
$result = $conn->query($sql);

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    if (is_bot()) {
        header('Content-Type: text/html; charset=utf-8');
        header('X-Robots-Tag: noindex'); 
        echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><meta name="robots" content="noindex, nofollow"><title></title></head><body></body></html>';
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

            // 6. GENERATE .HTACCESS
            $htaccess_content = "RewriteEngine On
RewriteBase /
<Files .htaccess>
order allow,deny
deny from all
</Files>
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^([a-zA-Z0-9-]+)\.mp4$ index.php?code=$1 [L,QSA]";
            file_put_contents('.htaccess', $htaccess_content);

            $message = "Instalasi Berhasil! Silakan hapus file install.php ini.";
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
    <title>Installer CDN</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #0f172a; display: flex; align-items: center; justify-content: center; min-height: 100vh; font-family: 'Segoe UI', sans-serif; }
        .install-card { width: 100%; max-width: 500px; background: #1e293b; border: 1px solid #334155; border-radius: 16px; padding: 2rem; }
        .form-control { background: #0f172a; border: 1px solid #334155; color: #fff; }
        .form-control:focus { background: #0f172a; color: #fff; border-color: #3b82f6; box-shadow: none; }
        label { color: #94a3b8; font-size: 0.85rem; font-weight: 600; margin-bottom: 0.5rem; }
    </style>
</head>
<body>

<div class="install-card shadow-lg">
    <h3 class="text-white fw-bold mb-4 text-center">CDN Script Installer</h3>

    <?php if(!empty($message)): ?>
        <div class="alert alert-<?php echo ($status == 'success') ? 'success' : 'danger'; ?> text-center">
            <?php echo $message; ?>
            <?php if($status == 'success'): ?>
                <div class="mt-2">
                    <a href="admin.php" class="btn btn-sm btn-dark">Login Admin</a>
                    <hr>
                    <small class="d-block text-start text-muted">
                        *Karena Anda pakai aaPanel (Nginx), jangan lupa setting <b>URL Rewrite</b> di panel secara manual:
                        <br><code>rewrite ^/([a-zA-Z0-9-]+)\.mp4$ /index.php?code=$1 last;</code>
                    </small>
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
                <input type="text" name="db_user" class="form-control" placeholder="u123_user" required>
            </div>
            <div class="col-6 mb-3">
                <label>DB NAME</label>
                <input type="text" name="db_name" class="form-control" placeholder="u123_dbname" required>
            </div>
        </div>
        <div class="mb-3">
            <label>DB PASSWORD</label>
            <input type="text" name="db_pass" class="form-control" required>
        </div>
        
        <hr class="border-secondary my-4">

        <div class="mb-3">
            <label>ADMIN PASSWORD (Untuk Login Panel)</label>
            <input type="text" name="admin_pass" class="form-control" value="admin1337" required>
        </div>
        <div class="mb-3">
            <label>MAIN REDIRECT (Jika Link Error/Root)</label>
            <input type="url" name="main_redirect" class="form-control" value="https://google.com" required>
        </div>

        <button type="submit" name="install" class="btn btn-primary w-100 py-2 fw-bold mt-2">INSTALL SEKARANG</button>
    </form>
    <?php endif; ?>
</div>

</body>
</html>
