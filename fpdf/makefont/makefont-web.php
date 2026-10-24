<?php
require_once __DIR__ . '/makefont.php';

$msg = '';
$generated = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['font'])) {
    $file = $_FILES['font'];
    if ($file['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['ttf', 'otf', 'pfb'])) {
            $msg = 'Error: Only .ttf, .otf, or .pfb files are accepted.';
        } else {
            $dest = __DIR__ . '/' . basename($file['name']);
            if (move_uploaded_file($file['tmp_name'], $dest)) {
                $enc = $_POST['encoding'] ?? 'cp1256';
                $embed = ($_POST['embed'] ?? '1') === '1';
                $subset = ($_POST['subset'] ?? '1') === '1';

                $enc = preg_replace('/[^a-z0-9_-]/i', '', $enc);

                ob_start();
                try {
                    MakeFont($dest, $enc, $embed, $subset);
                    $output = ob_get_clean();

                    $basename = pathinfo($dest, PATHINFO_FILENAME);
                    $files = glob(__DIR__ . '/' . $basename . '.*');
                    foreach ($files as $f) {
                        $bn = basename($f);
                        if ($bn !== basename($dest)) {
                            $generated[] = $bn;
                        }
                    }
                    // Delete uploaded TTF
                    @unlink($dest);
                    $msg = 'Font definition generated successfully!';
                } catch (Exception $e) {
                    $output = ob_get_clean();
                    $msg = 'Error: ' . $e->getMessage();
                    @unlink($dest);
                }
            } else {
                $msg = 'Error: Failed to save uploaded file.';
            }
        }
    } else {
        $msg = 'Error: Upload failed (code ' . $file['error'] . ').';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>FPDF Font Generator</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:Arial,sans-serif;background:#f5f5f5;display:flex;justify-content:center;align-items:center;min-height:100vh}
.card{background:#fff;border-radius:12px;padding:2rem;max-width:500px;width:90%;box-shadow:0 4px 20px rgba(0,0,0,0.1)}
h1{font-size:1.4rem;margin-bottom:0.5rem}
p{color:#666;font-size:0.85rem;margin-bottom:1.5rem}
label{display:block;font-weight:700;font-size:0.8rem;margin-bottom:0.3rem;color:#444}
input,select{width:100%;padding:0.6rem;border:1px solid #ccc;border-radius:6px;font-size:0.9rem;margin-bottom:1rem}
button{background:#6366f1;color:#fff;border:none;padding:0.7rem 1.5rem;border-radius:6px;font-size:0.95rem;cursor:pointer;width:100%}
button:hover{background:#4f46e5}
.msg{padding:0.8rem;border-radius:6px;margin-bottom:1rem;font-size:0.85rem}
.msg.ok{background:#d1fae5;color:#065f46;border:1px solid #a7f3d0}
.msg.err{background:#fee2e2;color:#991b1b;border:1px solid #fecaca}
ul{list-style:none;padding:0}
ul li{background:#f0fdf4;padding:0.5rem 0.8rem;border-radius:4px;margin-bottom:0.3rem;font-family:monospace;font-size:0.85rem}
.info{background:#eef2ff;padding:0.6rem;border-radius:6px;font-size:0.8rem;color:#4338ca;margin-bottom:1rem}
</style>
</head>
<body>
<div class="card">
    <h1>FPDF Font Generator</h1>
    <p>Upload a TrueType (.ttf), OpenType (.otf), or Type1 (.pfb) font to generate FPDF definition files.</p>

    <?php if ($msg): ?>
    <div class="msg <?php echo strpos($msg, 'Error') === 0 ? 'err' : 'ok'; ?>"><?php echo htmlspecialchars($msg); ?></div>
    <?php endif; ?>

    <?php if ($generated): ?>
    <div class="msg ok">Generated files in <code>fpdf/font/</code>:</div>
    <ul>
        <?php foreach ($generated as $g): ?>
        <li><?php echo htmlspecialchars($g); ?></li>
        <?php endforeach; ?>
    </ul>
    <p style="margin-top:1rem;font-size:0.85rem;color:#666;">
        <strong>Next steps:</strong><br>
        1. Move these files to <code>fpdf/font/</code><br>
        2. For the bold variant, upload the bold TTF separately and rename to match (e.g., <code>DejaVuSansb.php</code>, <code>DejaVuSansb.z</code>)<br>
        3. The system looks for: <code>DejaVuSans.php</code>, <code>DejaVuSans.z</code>, <code>DejaVuSansb.php</code>, <code>DejaVuSansb.z</code><br>
        4. This font uses <strong>cp1256</strong> encoding — the code automatically converts UTF-8 text to cp1256 when the font is detected.
    </p>
    <hr style="margin:1.5rem 0;border:none;border-top:1px solid #eee;">
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data">
        <label for="font">Font File (.ttf / .otf / .pfb)</label>
        <input type="file" name="font" id="font" accept=".ttf,.otf,.pfb" required>

        <label for="encoding">Encoding</label>
        <select name="encoding" id="encoding">
            <option value="cp1252">cp1252 (Western Europe)</option>
            <option value="cp1250">cp1250 (Central Europe)</option>
            <option value="cp1251">cp1251 (Cyrillic)</option>
            <option value="cp1253">cp1253 (Greek)</option>
            <option value="cp1254">cp1254 (Turkish)</option>
            <option value="cp1255">cp1255 (Hebrew)</option>
            <option value="cp1257">cp1257 (Baltic)</option>
            <option value="cp1258">cp1258 (Vietnamese)</option>
            <option value="cp1256" selected>cp1256 (Arabic) — recommended</option>
        </select>

        <div class="info">
            For Arabic support, select <strong>cp1256 (Arabic)</strong> and use a font with Arabic glyphs (e.g., DejaVuSans.ttf).<br>
            Then in your PHP code, convert UTF-8 text to cp1256 before passing to FPDF:
            <code style="display:block;margin-top:4px;font-size:0.75rem;">$text = iconv('UTF-8', 'Windows-1256//IGNORE', $utf8_text);</code>
        </div>

        <label><input type="checkbox" name="embed" value="1" checked> Embed font in PDF</label>
        <label><input type="checkbox" name="subset" value="1" checked> Subset font (smaller file)</label>

        <button type="submit">Generate Font Definition</button>
    </form>
</div>
</body>
</html>
