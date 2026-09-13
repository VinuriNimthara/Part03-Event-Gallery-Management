<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_login(['teacher']);

$page_title = 'Upcoming Events';
$active = 'events';

$events = $pdo->query("SELECT * FROM events ORDER BY event_date DESC")->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<div class="card">
  <div class="card-head"><h3>🎉 School Events</h3></div>
  <?php if (!$events): ?><div class="empty-state"><div class="emoji">🎈</div>No events yet.</div><?php endif; ?>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Image</th><th>Event</th><th>Date</th><th>Time</th><th>Venue</th><th>Status</th></tr></thead>
      <tbody>
        <?php foreach ($events as $ev): ?>
          <tr>
            <td><?php if ($ev['image_path']): ?><img src="<?= e(photo_url($ev['image_path'], 'events')) ?>" style="width:50px;height:50px;border-radius:10px;object-fit:cover;"><?php else: ?>🎉<?php endif; ?></td>
            <td><b><?= e($ev['event_name']) ?></b><div style="font-size:.76rem; color:var(--muted);"><?= e($ev['description']) ?></div></td>
            <td><?= fmt_date($ev['event_date']) ?></td>
            <td><?= $ev['event_time'] ? date('h:i A', strtotime($ev['event_time'])) : '—' ?></td>
            <td><?= e($ev['venue']) ?></td>
            <td><?= badge_status($ev['status']) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
