<?php
declare(strict_types=1);
session_start();
$dsn      = 'mysql:host=mysql;dbname=example_db;charset=utf8mb4';
$db_user  = 'root';
$db_pass  = '';
try {
    $dbh = new PDO($dsn, $db_user, $db_pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    exit('DB connection failed.');
}

if (empty($_SESSION['token'])) {
    $_SESSION['token'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($_POST['token']) || !hash_equals($_SESSION['token'], $_POST['token'])) {
        http_response_code(400);
        exit('Invalid CSRF token.');
    }

    if (!isset($_POST['body']) || trim($_POST['body']) === '') {
        header('Location: ./bbs.php');
        exit;
    }

    $image_filename = null;

    if (isset($_FILES['image']) && is_array($_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {
        if ($_FILES['image']['error'] !== UPLOAD_ERR_OK) {
            header('Location: ./bbs.php');
            exit;
        }
        if (!is_uploaded_file($_FILES['image']['tmp_name'])) {
            header('Location: ./bbs.php');
            exit;
        }

        $imgDir = '/var/www/upload/image';
        if (!is_dir($imgDir)) {
            if (!mkdir($imgDir, 0755, true) && !is_dir($imgDir)) {
                http_response_code(500);
                exit('Failed to create image directory.');
            }
        }

        $pathinfo  = pathinfo($_FILES['image']['name']);
        $extension = strtolower($pathinfo['extension'] ?? 'jpg');

        $image_filename = sprintf('%d_%s.%s', time(), bin2hex(random_bytes(8)), $extension);
        $filepath = rtrim($imgDir, '/') . '/' . $image_filename;

        if (!move_uploaded_file($_FILES['image']['tmp_name'], $filepath)) {
            header('Location: ./bbs.php');
            exit;
        }
        @chmod($filepath, 0644);
    }

    $sql = "INSERT INTO bbs_entries (body, image_filename) VALUES (:body, :image_filename)";
    $stmt = $dbh->prepare($sql);
    $stmt->execute([
        ':body' => $_POST['body'],
        ':image_filename' => $image_filename,
    ]);

    header('Location: ./bbs.php');
    exit;
}

$rows = $dbh->query('SELECT id, body, image_filename, created_at FROM bbs_entries ORDER BY created_at DESC')->fetchAll();

function autolink_reply_anchors(string $safeText): string {
    $pattern = '/(&gt;){2}(\d{1,10})/u';
    $repl = function(array $m): string {
        $num = $m[2];
        return '<a class="reply-anchor" href="#post-' . $num . '">&gt;&gt;' . $num . '</a>';
    };
    return preg_replace_callback($pattern, $repl, $safeText);
}
?>
<!doctype html>
<html lang="ja">
<head>
  <meta charset="utf-8">
  <title>画像付きBBS</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="/css/bbs.css">
  <style>
    body { max-width: 720px; margin: 2rem auto; font-family: system-ui, -apple-system, "Segoe UI", Roboto, "Noto Sans JP", "Hiragino Kaku Gothic ProN", Meiryo, sans-serif; }
    textarea { width: 100%; height: 8rem; }
    .entry { margin-bottom: 1.25em; padding: 1em 0; border-bottom: 1px solid #ddd; scroll-margin-top: 1.5rem; }
    .entry-header { display: flex; align-items: baseline; gap: .5rem; }
    .entry-no { font-weight: 700; }
    .entry-meta { color: #666; font-size: .9rem; }
    .entry-actions { margin-left: auto; display: flex; gap: .5rem; }
    .permalink { text-decoration: none; color: #999; font-weight: 700; }
    .reply-btn { border: 1px solid #ccc; background: #f7f7f7; padding: .15rem .5rem; border-radius: .4rem; cursor: pointer; font-size: .85rem; }
    .reply-btn:hover { background: #eee; }
    .reply-anchor { color: #06c; text-decoration: none; }
    .reply-anchor:hover { text-decoration: underline; }
    .highlight { background: #fff6d6; transition: background 1.2s ease; }
    img.post-image { max-height: 10em; height: auto; }
  </style>
</head>
<body>
  <h1>画像付きBBS</h1>

  <form id="bbs-form" method="POST" action="./bbs.php" enctype="multipart/form-data">
    <textarea name="body" required placeholder="本文を入力..."></textarea>
    <div style="margin: 1em 0;">
      <input id="image-input" type="file" accept="image/*" name="image">
    </div>
    <input type="hidden" name="token" value="<?= htmlspecialchars($_SESSION['token'], ENT_QUOTES, 'UTF-8') ?>">
    <button type="submit">送信</button>
  </form>

  <hr>

  <?php foreach ($rows as $entry): ?>
    <?php
      $id = (int)$entry['id'];
      $safeCreated = htmlspecialchars($entry['created_at'] ?? '', ENT_QUOTES, 'UTF-8');
      $safeBody    = htmlspecialchars($entry['body'] ?? '', ENT_QUOTES, 'UTF-8');
      $linkedBody  = autolink_reply_anchors($safeBody);
      $renderBody  = nl2br($linkedBody);
    ?>
    <article id="post-<?= $id ?>" class="entry">
      <div class="entry-header">
        <span class="entry-no">No.<?= $id ?></span>
        <span class="entry-meta"><?= $safeCreated ?></span>
        <div class="entry-actions">
          <button class="reply-btn" type="button" data-reply-id="<?= $id ?>"><?=$id ?></button>
          <a class="permalink" href="#post-<?= $id ?>" title="この投稿へのリンク">#</a>
        </div>
      </div>
      <div class="entry-body">
        <?= $renderBody ?>
        <?php if (!empty($entry['image_filename'])): ?>
          <div style="margin-top:.5rem">
            <img class="post-image" src="/image/<?= rawurlencode($entry['image_filename']) ?>" alt="">
          </div>
        <?php endif; ?>
      </div>
    </article>
  <?php endforeach; ?>

  <script src="/js/image-compress-upload.js" defer></script>

  <script>
  (function() {
    const form = document.getElementById('bbs-form');
    const textarea = form?.querySelector('textarea[name="body"]');

    document.addEventListener('click', (e) => {
      const btn = e.target.closest('.reply-btn');
      if (!btn || !textarea) return;
      const id = btn.getAttribute('data-reply-id');
      if (!id) return;

      const insert = `>>${id} `;
      const start = textarea.selectionStart ?? textarea.value.length;
      const end   = textarea.selectionEnd ?? textarea.value.length;
      const before = textarea.value.slice(0, start);
      const after  = textarea.value.slice(end);
      textarea.value = before + insert + after;
      const pos = (before + insert).length;
      textarea.setSelectionRange?.(pos, pos);
      textarea.focus();
      window.scrollTo({ top: form.offsetTop - 16, behavior: 'smooth' });
    });

    function highlightFromHash() {
      const id = location.hash && location.hash.startsWith('#post-') ? location.hash.substring(6) : null;
      if (!id) return;
      const el = document.getElementById(`post-${id}`);
      if (!el) return;
      el.classList.add('highlight');
      setTimeout(() => el.classList.remove('highlight'), 1600);
    }
    window.addEventListener('hashchange', highlightFromHash);
    document.addEventListener('DOMContentLoaded', highlightFromHash);
  })();
  </script>
</body>
</html>

