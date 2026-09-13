<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_login(['teacher']);

$page_title = 'Gallery';
$active = 'gallery';

$albums = $pdo->query("SELECT a.*, (SELECT COUNT(*) FROM gallery g WHERE g.album_id=a.album_id) AS photo_count
                        FROM gallery_albums a ORDER BY a.created_at DESC")->fetchAll();

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
  <div class="card">
    <div class="card-head"><h3>🖼️ Photo Albums</h3></div>
    <?php if (!$albums): ?><div class="empty-state"><div class="emoji">🖼️</div>No albums have been shared yet.</div><?php endif; ?>
    <div class="album-grid">
      <?php foreach ($albums as $a): ?>
        <a href="?album=<?= $a['album_id'] ?>" class="album-card" style="display:block;">
          <div class="album-cover">
            <?php if ($a['cover_photo']): ?><img src="<?= e(photo_url($a['cover_photo'], 'albums')) ?>" alt=""><?php endif; ?>
          </div>
          <div class="album-title">
            <?= e($a['title']) ?>
            <small><?= (int)$a['photo_count'] ?> photo(s)</small>
          </div>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
<?php else: ?>
  <a href="<?= BASE_URL ?>/teacher/gallery.php" class="btn btn-sm btn-light" style="margin-bottom:16px;">← Back to Albums</a>
  <div class="card">
    <div class="card-head"><h3>🖼️ <?= e($selected_album['title']) ?></h3></div>
    <?php if (!$photos): ?><div class="empty-state"><div class="emoji">🖼️</div>No photos in this album yet.</div><?php endif; ?>
    <div class="gallery-grid">
      <?php foreach ($photos as $g): ?>
        <div class="gallery-item">
          <?php if ($g['file_type'] === 'video'): ?>
            <video src="<?= BASE_URL ?>/uploads/gallery/<?= e($g['file_path']) ?>" muted controls style="width:100%;height:100%;object-fit:cover;"></video>
          <?php else: ?>
            <img src="<?= BASE_URL ?>/uploads/gallery/<?= e($g['file_path']) ?>" alt="">
          <?php endif; ?>
          <div class="cap"><?= e($g['title'] ?: 'Untitled') ?></div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
