<?php
$dbh = new PDO('mysql:host=mysql;dbname=example_db', 'root', '');

if (isset($_POST['body'])) {
  // POSTで送られてくるフォームパラメータ body がある場合

  $image_filename = null;
  if (isset($_FILES['image']) && !empty($_FILES['image']['tmp_name'])) {
    // アップロードされた画像がある場合
    // アップロードされたものが画像でない場合、強制的に終了
    if (preg_match('/^image\//', mime_content_type($_FILES['image']['tmp_name'])) !== 1) {
      header("HTTP/1.1 302 Found");
      header("Location: ./bbsimagetest.php");
      return;
    }

    // 元のファイル名から拡張子を取得
    $pathinfo = pathinfo($_FILES['image']['name']);
    $extension = $pathinfo['extension'];

    // 新しいファイル名を決める。他の投稿の画像ファイルと重複しないように時間+乱数で決める。
    $image_filename = strval(time()) . bin2hex(random_bytes(25)) . '.' . $extension;
    $filepath = '/var/www/upload/image/' . $image_filename;

    // ファイルを指定したパスに移動
    move_uploaded_file($_FILES['image']['tmp_name'], $filepath);
  }

  // insertする
  $insert_sth = $dbh->prepare("INSERT INTO bbs_entries (body, image_filename) VALUES (:body, :image_filename)");
  $insert_sth->execute([
    ':body' => $_POST['body'],
    ':image_filename' => $image_filename,
  ]);

  // 処理が終わったらリダイレクトする
  header("HTTP/1.1 302 Found");
  header("Location: ./bbsimagetest.php");
  return;
}

// いままで保存してきたものを取得
$select_sth = $dbh->prepare('SELECT * FROM bbs_entries ORDER BY created_at DESC');
$select_sth->execute();
?>

<!-- フォームのPOST先はこのファイル自身にする -->
<form method="POST" action="./bbsimagetest.php" enctype="multipart/form-data" id="uploadForm">
  <textarea name="body" required></textarea>
  <div style="margin: 1em 0;">
    <input type="file" id="fileInput" accept="image/*" name="image">
  </div>
  <button type="submit">送信</button>
</form>

<script>
  // 5MBの制限を設定（5MB = 5 * 1024 * 1024 = 5242880 bytes）
  const MAX_FILE_SIZE = 5 * 1024 * 1024;

  // フォームのsubmitイベントを監視
  document.getElementById('uploadForm').addEventListener('submit', function(event) {
    const fileInput = document.getElementById('fileInput');
    const file = fileInput.files[0]; // 選択されたファイル

    if (file) {
      if (file.size > MAX_FILE_SIZE) {
        event.preventDefault(); // フォームの送信を防止
        alert('ファイルサイズが大きすぎます。5MB以内のファイルを選択してください。');
      }
    }
  });
</script>

<hr>

<?php foreach($select_sth as $entry): ?>
  <dl style="margin-bottom: 1em; padding-bottom: 1em; border-bottom: 1px solid #ccc;">
    <dt>ID</dt>
    <dd><?= $entry['id'] ?></dd>
    <dt>日時</dt>
    <dd><?= $entry['created_at'] ?></dd>
    <dt>内容</dt>
    <dd>
      <?= nl2br(htmlspecialchars($entry['body'])) // 必ず htmlspecialchars() すること ?>
      <?php if (!empty($entry['image_filename'])): // 画像がある場合は img 要素を使って表示 ?>
        <div>
          <img src="/image/<?= $entry['image_filename'] ?>" style="max-height: 10em;">
        </div>
      <?php endif; ?>
    </dd>
  </dl>
<?php endforeach ?>

