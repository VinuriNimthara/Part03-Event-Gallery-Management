<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_login(['admin']);

$page_title = 'Gallery Management';
$active = 'gallery';

// ---------- Album actions ----------
if (isset($_GET['delete_album'])) {
    $photos = $pdo->prepare("SELECT file_path FROM gallery WHERE album_id=?");
    $photos->execute([(int)$_GET['delete_album']]);
    foreach ($photos->fetchAll() as $p) { if (file_exists(UPLOAD_GALLERY . $p['file_path'])) @unlink(UPLOAD_GALLERY . $p['file_path']); }
    $pdo->prepare("DELETE FROM gallery_albums WHERE album_id=?")->execute([(int)$_GET['delete_album']]);
    set_flash('success', 'Album and its photos deleted.');
    redirect('/admin/gallery.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_album'])) {
    $album_id = $_POST['album_id'] ?? '';
    $title = trim($_POST['title']);
    $cover = handle_upload('cover_photo', UPLOAD_ALBUMS);

    if ($album_id) {
        $sql = "UPDATE gallery_albums SET title=?" . ($cover ? ", cover_photo=?" : "") . " WHERE album_id=?";
        $params = [$title];
        if ($cover) $params[] = $cover;
        $params[] = $album_id;
        $pdo->prepare($sql)->execute($params);
        set_flash('success', 'Album updated.');
    } else {
        $pdo->prepare("INSERT INTO gallery_albums (title, cover_photo, created_by) VALUES (?,?,?)")
            ->execute([$title, $cover, $_SESSION['user_id']]);
        set_flash('success', 'Album created.');
    }
    redirect('/admin/gallery.php');
}

// ---------- Photo actions (inside an album) ----------
if (isset($_GET['delete_photo'])) {
    $album = $_GET['album'] ?? '';
    $stmt = $pdo->prepare("SELECT file_path FROM gallery WHERE gallery_id=?");
    $stmt->execute([(int)$_GET['delete_photo']]);
    $row = $stmt->fetch();
    if ($row && file_exists(UPLOAD_GALLERY . $row['file_path'])) @unlink(UPLOAD_GALLERY . $row['file_path']);
    $pdo->prepare("DELETE FROM gallery WHERE gallery_id=?")->execute([(int)$_GET['delete_photo']]);
    set_flash('success', 'Photo removed.');
    redirect('/admin/gallery.php?album=' . $album);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_photos'])) {
    $album_id = $_POST['album_id'];
    $count = 0;
    if (!empty($_FILES['photos']['name'][0])) {
        foreach ($_FILES['photos']['name'] as $i => $name) {
            if (empty($name)) continue;
            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            $allowed = ['jpg','jpeg','png','gif','webp','mp4'];
            if (!in_array($ext, $allowed)) continue;
            $filename = uniqid('gal_') . '.' . $ext;
            if (!is_dir(UPLOAD_GALLERY)) @mkdir(UPLOAD_GALLERY, 0777, true);
            if (move_uploaded_file($_FILES['photos']['tmp_name'][$i], UPLOAD_GALLERY . $filename)) {
                $pdo->prepare("INSERT INTO gallery (album_id, uploaded_by, title, file_path, file_type) VALUES (?,?,?,?,?)")
                    ->execute([$album_id, $_SESSION['user_id'], trim($_POST['title'] ?? ''), $filename, $ext === 'mp4' ? 'video' : 'image']);
                $count++;
            }
        }
    }
    set_flash('success', $count . ' photo(s) uploaded.');
    redirect('/admin/gallery.php?album=' . $album_id);
}

$albums = $pdo->query("SELECT a.*, (SELECT COUNT(*) FROM gallery g WHERE g.album_id=a.album_id) AS photo_count
                        FROM gallery_albums a ORDER BY a.created_at DESC")->fetchAll();

$editing_album = null;
if (isset($_GET['edit_album'])) {
    $stmt = $pdo->prepare("SELECT * FROM gallery_albums WHERE album_id=?");
    $stmt->execute([(int)$_GET['edit_album']]);
    $editing_album = $stmt->fetch();
}

$selected_album = null;
$photos = [];
if (isset($_GET['album'])) {
    $stmt = $pdo->prepare("SELECT * FROM gallery_albums WHERE album_id=?");
    $stmt->execute([(int)$_GET['album']]);
    $selected_album = $stmt->fetch();
    if ($selected_album) {
        $stmt2 = $pdo->prepare("SELECT * FROM gallery WHERE album_id=? ORDER BY gallery_id DESC");
        $stmt2->execute([$selected_album['album_id']]);
        $photos = $stmt2->fetchAll();
    }
}

include __DIR__ . '/../includes/header.php';
?>

<?php if (!$selected_album): ?>

  <div class="card form-card">
    <div class="card-head"><h3><?= $editing_album ? '✏️ Edit Album' : '➕ Create New Album' ?></h3></div>
    <form method="POST" enctype="multipart/form-data">
      <input type="hidden" name="save_album" value="1">
      <input type="hidden" name="album_id" value="<?= e($editing_album['album_id'] ?? '') ?>">
      <div class="form-group"><label>Album Title</label><input class="form-control" name="title" required value="<?= e($editing_album['title'] ?? '') ?>"></div>
      <div class="form-group">
        <label>Cover Photo</label>
        <input type="file" class="form-control" name="cover_photo" accept="image/*">
        <?php if (!empty($editing_album['cover_photo'])): ?>
          <div style="margin-top:8px;"><img src="<?= e(photo_url($editing_album['cover_photo'], 'albums')) ?>" style="max-width:140px;border-radius:12px;"></div>
        <?php endif; ?>
      </div>
      <div style="display:flex; gap:10px;">
        <button class="btn" type="submit"><?= $editing_album ? 'Update Album' : 'Create Album' ?></button>
        <?php if ($editing_album): ?><a href="<?= BASE_URL ?>/admin/gallery.php" class="btn btn-outline">Cancel</a><?php endif; ?>
      </div>
    </form>
  </div>

  <div class="card">
    <div class="card-head"><h3>🖼️ All Albums (<?= count($albums) ?>)</h3></div>
    <?php if (!$albums): ?><div class="empty-state"><div class="emoji">🖼️</div>No albums created yet.</div><?php endif; ?>
    <div class="album-grid">
      <?php foreach ($albums as $a): ?>
        <div class="album-card">
          <a href="?album=<?= $a['album_id'] ?>" class="album-cover">
            <?php if ($a['cover_photo']): ?><img src="<?= e(photo_url($a['cover_photo'], 'albums')) ?>" alt=""><?php endif; ?>
          </a>
          <div class="album-title">
            <?= e($a['title']) ?>
            <small><?= (int)$a['photo_count'] ?> photo(s)</small>
          </div>
          <div class="actions" style="padding:0 14px 14px;">
            <a class="btn btn-sm btn-light" href="?album=<?= $a['album_id'] ?>">Open</a>
            <a class="btn btn-sm btn-light" href="?edit_album=<?= $a['album_id'] ?>">Edit</a>
            <a class="btn btn-sm btn-danger" href="?delete_album=<?= $a['album_id'] ?>" data-confirm="Delete this album and all its photos?">Delete</a>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>

<?php else: ?>

  <a href="<?= BASE_URL ?>/admin/gallery.php" class="btn btn-sm btn-light" style="margin-bottom:16px;">← Back to Albums</a>

  <div class="card form-card">
    <div class="card-head"><h3>📤 Upload Photos to "<?= e($selected_album['title']) ?>"</h3></div>
    <form method="POST" enctype="multipart/form-data">
      <input type="hidden" name="upload_photos" value="1">
      <input type="hidden" name="album_id" value="<?= $selected_album['album_id'] ?>">
      <div class="form-group"><label>Caption (applies to all uploaded photos)</label><input class="form-control" name="title"></div>
      <div class="form-group"><label>Select Photos / Videos</label><input type="file" class="form-control" name="photos[]" multiple accept="image/*,video/mp4" required></div>
      <button class="btn" type="submit">Upload</button>
    </form>
  </div>

  <div class="card">
    <div class="card-head"><h3>Photos (<?= count($photos) ?>)</h3></div>
    <?php if (!$photos): ?><div class="empty-state"><div class="emoji">🖼️</div>No photos in this album yet.</div><?php endif; ?>
    <div class="gallery-grid">
      <?php foreach ($photos as $g): ?>
        <div class="gallery-item">
          <?php if ($g['file_type'] === 'video'): ?>
            <video src="<?= BASE_URL ?>/uploads/gallery/<?= e($g['file_path']) ?>" muted style="width:100%;height:100%;object-fit:cover;"></video>
          <?php else: ?>
            <img src="<?= BASE_URL ?>/uploads/gallery/<?= e($g['file_path']) ?>" alt="">
          <?php endif; ?>
          <div class="cap"><?= e($g['title'] ?: 'Untitled') ?></div>
          <a href="?album=<?= $selected_album['album_id'] ?>&delete_photo=<?= $g['gallery_id'] ?>" data-confirm="Delete this photo?" class="btn btn-sm btn-danger" style="position:absolute; top:8px; right:8px;">✕</a>
        </div>
      <?php endforeach; ?>
    </div>
  </div>

<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
