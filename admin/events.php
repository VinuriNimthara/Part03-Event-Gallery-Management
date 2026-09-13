```php
<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_login(['admin']);

$page_title = 'Event Management';
$active = 'events';

if (isset($_GET['delete'])) {
    $pdo->prepare("DELETE FROM events WHERE event_id=?")->execute([(int)$_GET['delete']]);
    set_flash('success', 'Event deleted.');
    redirect('/admin/events.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['event_id'] ?? '';
    $image = handle_upload('image', UPLOAD_EVENTS);

    $data = [
        trim($_POST['event_name']),
        trim($_POST['category']),
        trim($_POST['description']),
        $_POST['event_date'],
        $_POST['event_time'] ?: null,
        trim($_POST['venue']),
        $_POST['status']
    ];

    if ($id) {
        $sql = "UPDATE events 
                SET event_name=?, category=?, description=?, event_date=?, 
                    event_time=?, venue=?, status=?" 
                . ($image ? ", image_path=?" : "") . " 
                WHERE event_id=?";

        $params = $data;

        if ($image) {
            $params[] = $image;
        }

        $params[] = $id;

        $pdo->prepare($sql)->execute($params);
        set_flash('success', 'Event updated.');
    } else {
        $pdo->prepare("
            INSERT INTO events 
            (created_by, event_name, category, description, event_date, event_time, venue, status, image_path) 
            VALUES (?,?,?,?,?,?,?,?,?)
        ")->execute([
            $_SESSION['user_id'],
            ...$data,
            $image
        ]);

        set_flash('success', 'Event added.');
    }

    redirect('/admin/events.php');
}

$editing = null;

if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM events WHERE event_id=?");
    $stmt->execute([(int)$_GET['edit']]);
    $editing = $stmt->fetch();
}

$events = $pdo->query("SELECT * FROM events ORDER BY event_date DESC")->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<div class="card form-card">
  <div class="card-head">
    <h3><?= $editing ? '✏️ Edit Event' : '➕ Add New Event' ?></h3>
  </div>

  <form method="POST" enctype="multipart/form-data">

    <input type="hidden" name="event_id" value="<?= e($editing['event_id'] ?? '') ?>">

    <div class="form-row">

      <div class="form-group">
        <label>Event Name</label>
        <input 
          class="form-control" 
          name="event_name" 
          required 
          value="<?= e($editing['event_name'] ?? '') ?>"
        >
      </div>

      <div class="form-group">
        <label>Event Category</label>
        <select class="form-control" name="category" required>
          <?php
          $categories = [
              'Conference',
              'Workshop',
              'Seminar',
              'Sports',
              'Cultural',
              'Social',
              'Competition',
              'Other'
          ];

          $selectedCategory = $editing['category'] ?? 'Other';

          foreach ($categories as $category):
          ?>
            <option 
              value="<?= e($category) ?>"
              <?= $selectedCategory === $category ? 'selected' : '' ?>
            >
              <?= e($category) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

    </div>

    <div class="form-row">

      <div class="form-group">
        <label>Venue</label>
        <input 
          class="form-control" 
          name="venue" 
          value="<?= e($editing['venue'] ?? '') ?>"
        >
      </div>

      <div class="form-group">
        <label>Status</label>
        <select class="form-control" name="status">
          <option value="Upcoming" <?= (($editing['status'] ?? 'Upcoming') === 'Upcoming') ? 'selected' : '' ?>>
            Upcoming
          </option>
          <option value="Completed" <?= (($editing['status'] ?? '') === 'Completed') ? 'selected' : '' ?>>
            Completed
          </option>
          <option value="Cancelled" <?= (($editing['status'] ?? '') === 'Cancelled') ? 'selected' : '' ?>>
            Cancelled
          </option>
        </select>
      </div>

    </div>

    <div class="form-row">

      <div class="form-group">
        <label>Date</label>
        <input 
          type="date" 
          class="form-control" 
          name="event_date" 
          required 
          value="<?= e($editing['event_date'] ?? '') ?>"
        >
      </div>

      <div class="form-group">
        <label>Time</label>
        <input 
          type="time" 
          class="form-control" 
          name="event_time" 
          value="<?= e($editing['event_time'] ?? '') ?>"
        >
      </div>

    </div>

    <div class="form-group">
      <label>Description</label>
      <textarea 
        class="form-control" 
        name="description" 
        rows="3"
      ><?= e($editing['description'] ?? '') ?></textarea>
    </div>

    <div class="form-group">
      <label>Event Image (optional)</label>

      <input 
        type="file" 
        class="form-control" 
        name="image" 
        accept="image/*"
      >

      <?php if (!empty($editing['image_path'])): ?>
        <div style="margin-top:8px;">
          <img 
            src="<?= e(photo_url($editing['image_path'], 'events')) ?>" 
            style="max-width:160px;border-radius:12px;"
          >
        </div>
      <?php endif; ?>

    </div>

    <div style="display:flex; gap:10px;">
      <button class="btn" type="submit">
        <?= $editing ? 'Update Event' : 'Add Event' ?>
      </button>

      <?php if ($editing): ?>
        <a 
          href="<?= BASE_URL ?>/admin/events.php" 
          class="btn btn-outline"
        >
          Cancel
        </a>
      <?php endif; ?>
    </div>

  </form>
</div>

<div class="card">

  <div class="toolbar">
    <h3 style="margin:0;">
      All Events (<?= count($events) ?>)
    </h3>
  </div>

  <div class="table-wrap">

    <table>

      <thead>
        <tr>
          <th>Image</th>
          <th>Event</th>
          <th>Category</th>
          <th>Date</th>
          <th>Time</th>
          <th>Venue</th>
          <th>Status</th>
          <th>Actions</th>
        </tr>
      </thead>

      <tbody>

        <?php if (!$events): ?>

          <tr>
            <td colspan="8">
              <div class="empty-state">
                <div class="emoji">🎉</div>
                No events yet.
              </div>
            </td>
          </tr>

        <?php endif; ?>

        <?php foreach ($events as $ev): ?>

          <tr>

            <td>
              <?php if ($ev['image_path']): ?>

                <img 
                  src="<?= e(photo_url($ev['image_path'], 'events')) ?>" 
                  style="width:50px;height:50px;border-radius:10px;object-fit:cover;"
                >

              <?php else: ?>

                🎉

              <?php endif; ?>
            </td>

            <td>
              <b><?= e($ev['event_name']) ?></b>
            </td>

            <td>
              <span class="badge">
                <?= e($ev['category'] ?: 'Other') ?>
              </span>
            </td>

            <td>
              <?= fmt_date($ev['event_date']) ?>
            </td>

            <td>
              <?= $ev['event_time'] 
                  ? date('h:i A', strtotime($ev['event_time'])) 
                  : '—' ?>
            </td>

            <td>
              <?= e($ev['venue']) ?>
            </td>

            <td>
              <?= badge_status($ev['status']) ?>
            </td>

            <td class="actions">

              <a 
                class="btn btn-sm btn-light" 
                href="?edit=<?= $ev['event_id'] ?>"
              >
                Edit
              </a>

              <a 
                class="btn btn-sm btn-danger" 
                href="?delete=<?= $ev['event_id'] ?>" 
                data-confirm="Delete this event?"
              >
                Delete
              </a>

            </td>

          </tr>

        <?php endforeach; ?>

      </tbody>

    </table>

  </div>

</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
```
