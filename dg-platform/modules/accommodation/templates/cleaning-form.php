<?php
/**
 * Cleaning checklist form — linked to accommodation + housekeeping.
 *
 * @var WP_Post[] $properties
 * @var WP_Post|null $selected_property
 * @var bool $lock_property
 * @var bool $standalone
 * @var array $task_categories
 */

if (!defined('ABSPATH')) {
    exit;
}

$selected_property = $args['property'] ?? null;
$lock_property = !empty($args['lock_property']);
$standalone = !empty($args['standalone']);
$task_categories = DG_Acc_Cleaning::task_categories();
$form_action = admin_url('admin-post.php');
$message = isset($_GET['cleaning_message']) ? sanitize_text_field(wp_unslash($_GET['cleaning_message'])) : '';
$message_type = sanitize_key($_GET['cleaning_status'] ?? '');
$access_code_required = DG_Acc_Cleaning::access_code_required();

if ($standalone) : ?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
  <title>Cleaning Checklist<?php echo $selected_property ? ' — ' . esc_html($selected_property->post_title) : ''; ?> | <?php bloginfo('name'); ?></title>
</head>
<body>
<?php else : ?>
<div class="dg-cleaning-form-wrap">
<?php endif; ?>
  <style>
    .dg-cleaning-form-wrap, .dg-cleaning-page {
      font-family: Inter, system-ui, sans-serif;
      background: #F7F4EE;
      color: #2F2F2F;
      padding: 2rem 1rem;
    }
    .dg-cleaning-page .container, .dg-cleaning-form-wrap .container {
      max-width: 900px;
      margin: 0 auto;
    }
    .dg-cleaning-header { text-align: center; margin-bottom: 2rem; }
    .dg-cleaning-header h1 {
      font-family: Georgia, 'Times New Roman', serif;
      font-size: 2rem;
      color: #1C2B2A;
      margin: 0 0 0.25rem;
    }
    .dg-cleaning-header p { color: #6B7A78; font-size: 0.85rem; margin: 0; }
    .dg-cleaning-notice {
      max-width: 900px;
      margin: 0 auto 1rem;
      padding: 0.85rem 1rem;
      border-radius: 12px;
      font-size: 0.9rem;
    }
    .dg-cleaning-notice.success { background: #E8F5E9; color: #1B5E20; border: 1px solid #A5D6A7; }
    .dg-cleaning-notice.error { background: #FFEBEE; color: #B71C1C; border: 1px solid #EF9A9A; }
    .dg-cleaning-info {
      background: #fff;
      border-radius: 16px;
      padding: 1rem;
      margin-bottom: 1.5rem;
      border: 1px solid #E0D6CC;
      display: grid;
      grid-template-columns: repeat(2, 1fr);
      gap: 1rem;
    }
    .dg-cleaning-field { display: flex; flex-direction: column; }
    .dg-cleaning-field label {
      font-size: 0.7rem;
      font-weight: 600;
      color: #B9A48A;
      text-transform: uppercase;
      letter-spacing: 1px;
    }
    .dg-cleaning-field input, .dg-cleaning-field select, .dg-cleaning-field textarea {
      background: #FCF9F5;
      border: 1px solid #E0D6CC;
      border-radius: 8px;
      padding: 0.5rem;
      font-family: inherit;
      font-size: 0.85rem;
      margin-top: 0.25rem;
    }
    .dg-cleaning-section {
      background: #fff;
      border-radius: 20px;
      padding: 1.25rem;
      margin-bottom: 1rem;
      border: 1px solid #E0D6CC;
    }
    .dg-cleaning-section h2 {
      font-family: Georgia, 'Times New Roman', serif;
      font-size: 1.2rem;
      color: #1C2B2A;
      margin: 0 0 1rem;
      padding-bottom: 0.5rem;
      border-bottom: 2px solid #B9A48A;
      display: inline-block;
    }
    .dg-cleaning-task-list { list-style: none; margin: 0; padding: 0; }
    .dg-cleaning-task-list li {
      display: flex;
      align-items: center;
      gap: 10px;
      padding: 0.6rem 0;
      border-bottom: 1px solid #EBE4D9;
      cursor: pointer;
    }
    .dg-cleaning-task-list li:last-child { border-bottom: none; }
    .dg-cleaning-task-list li.completed .task-text {
      text-decoration: line-through;
      color: #9EADA9;
    }
    .dg-cleaning-check {
      width: 22px;
      height: 22px;
      border: 2px solid #B9A48A;
      border-radius: 6px;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      background: #fff;
      font-size: 0.75rem;
      color: #fff;
      flex-shrink: 0;
    }
    .dg-cleaning-task-list li.completed .dg-cleaning-check {
      background: #B9A48A;
    }
    .dg-cleaning-progress {
      background: #E8EDEC;
      border-radius: 20px;
      height: 8px;
      margin-bottom: 1rem;
      overflow: hidden;
    }
    .dg-cleaning-progress-fill {
      background: #B9A48A;
      width: 0%;
      height: 100%;
      transition: width 0.3s;
    }
    .dg-cleaning-stats {
      display: flex;
      justify-content: space-between;
      font-size: 0.7rem;
      color: #6B7A78;
      margin-bottom: 1rem;
    }
    .dg-cleaning-actions {
      display: flex;
      gap: 1rem;
      margin-top: 1rem;
      flex-wrap: wrap;
    }
    .dg-cleaning-btn {
      flex: 1;
      min-width: 140px;
      padding: 0.75rem 1rem;
      border-radius: 40px;
      font-weight: 600;
      font-size: 0.85rem;
      cursor: pointer;
      text-align: center;
      border: none;
    }
    .dg-cleaning-btn-primary { background: #B9A48A; color: #fff; }
    .dg-cleaning-btn-secondary { background: #E8EDEC; color: #2F2F2F; }
    .dg-cleaning-btn:disabled { opacity: 0.55; cursor: not-allowed; }
    @media (max-width: 640px) {
      .dg-cleaning-info { grid-template-columns: 1fr; }
    }
  </style>

<?php if ($message) : ?>
  <div class="dg-cleaning-notice <?php echo $message_type === 'error' ? 'error' : 'success'; ?>">
    <?php echo esc_html(rawurldecode($message)); ?>
  </div>
<?php endif; ?>

<div class="<?php echo $standalone ? 'dg-cleaning-page' : 'dg-cleaning-form-wrap'; ?>">
  <div class="container">
    <div class="dg-cleaning-header">
      <h1>🧹 Cleaning Checklist</h1>
      <p><?php echo esc_html(get_bloginfo('name')); ?> — tap each task to mark complete, then submit to Housekeeping</p>
    </div>

    <form id="dgCleaningForm" method="post" action="<?php echo esc_url($form_action); ?>">
      <input type="hidden" name="action" value="<?php echo esc_attr(DG_Acc_Cleaning::ACTION); ?>">
      <?php wp_nonce_field(DG_Acc_Cleaning::ACTION); ?>
      <input type="text" name="website" value="" tabindex="-1" autocomplete="off" style="position:absolute;left:-9999px;" aria-hidden="true">
      <input type="hidden" name="tasks_json" id="tasks_json" value="">
      <input type="hidden" name="report_date" id="report_date" value="<?php echo esc_attr(current_time('Y-m-d')); ?>">

      <div class="dg-cleaning-info">
        <div class="dg-cleaning-field">
          <label for="accommodation_id">Accommodation</label>
          <?php if ($lock_property && $selected_property) : ?>
            <input type="hidden" name="accommodation_id" value="<?php echo (int) $selected_property->ID; ?>">
            <input type="text" value="<?php echo esc_attr($selected_property->post_title); ?>" readonly>
          <?php else : ?>
            <select name="accommodation_id" id="accommodation_id" required>
              <option value="">Select accommodation...</option>
              <?php foreach ($properties as $property) : ?>
                <option value="<?php echo (int) $property->ID; ?>" <?php selected($selected_property ? $selected_property->ID : 0, $property->ID); ?>>
                  <?php echo esc_html($property->post_title); ?>
                </option>
              <?php endforeach; ?>
            </select>
          <?php endif; ?>
        </div>
        <div class="dg-cleaning-field">
          <label for="date">Date</label>
          <input type="date" id="date" name="date" required>
        </div>
        <div class="dg-cleaning-field">
          <label for="cleaner">Cleaner name</label>
          <input type="text" id="cleaner" name="cleaner" placeholder="Enter your name" required>
        </div>
        <div class="dg-cleaning-field">
          <label for="departureTime">Guest departure time</label>
          <input type="text" id="departureTime" name="departure_time" placeholder="e.g. 10:00 AM">
        </div>
        <?php if ($access_code_required) : ?>
          <div class="dg-cleaning-field">
            <label for="access_code">Access code</label>
            <input type="password" id="access_code" name="access_code" required autocomplete="off">
          </div>
        <?php endif; ?>
      </div>

      <div class="dg-cleaning-stats">
        <span><span id="completedCount">0</span> / <span id="totalCount">0</span> tasks completed</span>
      </div>
      <div class="dg-cleaning-progress"><div class="dg-cleaning-progress-fill" id="progressFill"></div></div>

      <div id="checklist-container"></div>

      <div class="dg-cleaning-section">
        <label for="notes"><strong>📝 Notes / maintenance issues</strong></label>
        <textarea id="notes" name="notes" rows="3" placeholder="Report any damage, missing items, or maintenance issues..." style="width:100%;margin-top:0.5rem;"></textarea>
      </div>

      <div class="dg-cleaning-section" style="text-align:center;">
        <label for="signature"><strong>✍️ Cleaner signature</strong></label>
        <input type="text" id="signature" name="signature" placeholder="Type your full name as signature" required style="width:100%;margin-top:0.5rem;text-align:center;">
        <div class="dg-cleaning-actions">
          <button type="submit" class="dg-cleaning-btn dg-cleaning-btn-primary" id="submitReportBtn">✅ Submit to Housekeeping</button>
          <button type="button" class="dg-cleaning-btn dg-cleaning-btn-secondary" id="downloadReportBtn">📄 Download copy</button>
          <button type="button" class="dg-cleaning-btn dg-cleaning-btn-secondary" id="resetChecklistBtn">↻ Reset all</button>
        </div>
      </div>
    </form>
  </div>
</div>

<script>
(function () {
  const taskCategories = <?php echo wp_json_encode($task_categories); ?>;
  let tasks = [];

  function initTasks() {
    tasks = [];
    taskCategories.forEach(function (cat) {
      cat.tasks.forEach(function (task) {
        tasks.push({ text: task, completed: false });
      });
    });
    renderChecklist();
  }

  function renderChecklist() {
    const container = document.getElementById('checklist-container');
    container.innerHTML = '';
    let taskIndex = 0;

    taskCategories.forEach(function (cat) {
      const section = document.createElement('div');
      section.className = 'dg-cleaning-section';
      section.innerHTML = '<h2>' + cat.name + '</h2><ul class="dg-cleaning-task-list"></ul>';
      const ul = section.querySelector('.dg-cleaning-task-list');

      cat.tasks.forEach(function (taskText) {
        const li = document.createElement('li');
        li.className = tasks[taskIndex].completed ? 'completed' : '';
        li.innerHTML = '<span class="dg-cleaning-check" aria-hidden="true">✓</span><span class="task-text"></span>';
        li.querySelector('.task-text').textContent = taskText;
        (function (idx) {
          li.addEventListener('click', function () {
            tasks[idx].completed = !tasks[idx].completed;
            renderChecklist();
          });
        })(taskIndex);
        ul.appendChild(li);
        taskIndex++;
      });

      container.appendChild(section);
    });

    updateStats();
  }

  function updateStats() {
    const total = tasks.length;
    const completed = tasks.filter(function (t) { return t.completed; }).length;
    document.getElementById('completedCount').textContent = completed;
    document.getElementById('totalCount').textContent = total;
    document.getElementById('progressFill').style.width = ((completed / total) * 100) + '%';
    document.getElementById('submitReportBtn').disabled = completed < total;
  }

  function resetChecklist() {
    if (confirm('Reset all checklist items?')) {
      tasks.forEach(function (t) { t.completed = false; });
      renderChecklist();
    }
  }

  function getAccommodationLabel() {
    <?php if ($lock_property && $selected_property) : ?>
    return <?php echo wp_json_encode($selected_property->post_title); ?>;
    <?php else : ?>
    var select = document.getElementById('accommodation_id');
    if (!select || !select.selectedOptions.length) return 'Not specified';
    return select.selectedOptions[0].textContent.trim();
    <?php endif; ?>
  }

  function buildReportText() {
    const date = document.getElementById('date').value || new Date().toISOString().split('T')[0];
    const cleaner = document.getElementById('cleaner').value || 'Not specified';
    const dwelling = getAccommodationLabel();
    const departureTime = document.getElementById('departureTime').value || 'Not specified';
    const notes = document.getElementById('notes').value || 'None';
    const signature = document.getElementById('signature').value || 'Not signed';
    const completedTasks = tasks.filter(function (t) { return t.completed; }).map(function (t) { return '✓ ' + t.text; }).join('\n');
    const pendingTasks = tasks.filter(function (t) { return !t.completed; }).map(function (t) { return '☐ ' + t.text; }).join('\n');

    return [
      'CLEANING REPORT',
      '================',
      'Date: ' + date,
      'Accommodation: ' + dwelling,
      'Cleaner: ' + cleaner,
      'Guest departure: ' + departureTime,
      '',
      'COMPLETED TASKS (' + tasks.filter(function (t) { return t.completed; }).length + '/' + tasks.length + '):',
      completedTasks || 'None',
      '',
      'PENDING TASKS:',
      pendingTasks || 'None',
      '',
      'NOTES:',
      notes,
      '',
      'Signature: ' + signature,
      'Generated: ' + new Date().toLocaleString()
    ].join('\n');
  }

  function downloadReport() {
    const blob = new Blob([buildReportText()], { type: 'text/plain' });
    const a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = 'cleaning_report_' + (document.getElementById('date').value || 'report') + '.txt';
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(a.href);
  }

  document.getElementById('resetChecklistBtn').addEventListener('click', resetChecklist);
  document.getElementById('downloadReportBtn').addEventListener('click', downloadReport);
  document.getElementById('dgCleaningForm').addEventListener('submit', function (e) {
    const completed = tasks.filter(function (t) { return t.completed; }).length;
    if (completed < tasks.length) {
      e.preventDefault();
      alert('Please complete every checklist item before submitting.');
      return;
    }
    document.getElementById('tasks_json').value = JSON.stringify(tasks);
    document.getElementById('report_date').value = document.getElementById('date').value;
  });

  const dateInput = document.getElementById('date');
  if (dateInput && !dateInput.value) {
    dateInput.valueAsDate = new Date();
  }

  initTasks();
})();
</script>

<?php if ($standalone) : ?>
</body>
</html>
<?php else : ?>
</div>
<?php endif; ?>
