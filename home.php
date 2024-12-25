<?php
session_start();

// Ensure $_SESSION['tasks'] is always an array
if (!isset($_SESSION['tasks']) || !is_array($_SESSION['tasks'])) {
    $_SESSION['tasks'] = [];
}

// Initialize today's date
$today = date('Y-m-d');

// Add task
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add') {
    $title = $_POST['title'] ?? '';
    $task_date = $_POST['task_date'] ?? $today; // Default to today
    if (!empty($title)) {
        // Store task as an associative array with 'title' and 'date'
        $_SESSION['tasks'][] = ['title' => $title, 'date' => $task_date];
    }
}

// Delete task
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    $task_index = $_POST['task_index'] ?? -1;
    if (is_numeric($task_index) && isset($_SESSION['tasks'][$task_index])) {
        // Remove the task at the given index
        unset($_SESSION['tasks'][$task_index]);
        // Re-index the array to maintain sequential keys
        $_SESSION['tasks'] = array_values($_SESSION['tasks']);
    }
}

// Determine which view to show: Home or Upcoming Tasks
$view = $_GET['view'] ?? 'home';

// Filter tasks for today if view is 'today'
$tasks_to_display = $view === 'home' || $view === 'today'
    ? array_filter($_SESSION['tasks'], function ($task) use ($today) {
        return isset($task['date']) && $task['date'] === $today;
    })
    : $_SESSION['tasks'];

// Group tasks by date for upcoming tasks
$tasks_by_date = [];
foreach ($_SESSION['tasks'] as $task) {
    if (isset($task['date']) && isset($task['title'])) {
        $date = $task['date'];
        if (!isset($tasks_by_date[$date])) {
            $tasks_by_date[$date] = [];
        }
        $tasks_by_date[$date][] = $task['title'];
    }
}

// Pass tasks to JavaScript for upcoming tasks calendar
$tasks_json = json_encode($tasks_by_date);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Task Manager</title>
</head>
<body>

<h1>Task Manager</h1>

<!-- Navigation Links -->
<p>
    <a href="?view=home">Home</a> | 
    <a href="?view=today">Today's Tasks</a> | 
    <a href="?view=upcoming">Upcoming Tasks</a>
</p>

<?php if ($view === 'home' || $view === 'today') : ?>

    <!-- Add Task Form -->
    <h2>Add a Task</h2>
    <form method="POST">
        <input type="hidden" name="action" value="add">
        <input type="text" name="title" placeholder="Task Title" required>
        <input type="date" name="task_date" value="<?= htmlspecialchars($today); ?>" required>
        <button type="submit">Add Task</button>
    </form>

    <!-- Task List -->
    <h2><?= $view === 'today' ? "Today's Tasks ($today)" : 'All Tasks'; ?></h2>
    <ul>
        <?php if (empty($tasks_to_display)) : ?>
            <li>No tasks found!</li>
        <?php else : ?>
            <?php foreach ($tasks_to_display as $index => $task) : ?>      
                <?php if (is_array($task) && isset($task['title'], $task['date'])) : ?>
                    <li>
                        <?= htmlspecialchars($task['title']); ?> (Date: <?= htmlspecialchars($task['date']); ?>)
                        <form method="POST" style="display:inline;">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="task_index" value="<?= $index; ?>">
                            <button type="submit">Delete</button>
                        </form>
                    </li>
                <?php endif; ?>
            <?php endforeach; ?>
        <?php endif; ?>
    </ul>

<?php elseif ($view === 'upcoming') : ?>

    <!-- Add Task Form (for Upcoming Tasks) -->
    <h2>Add a Task for Upcoming Date</h2>
    <form method="POST">
        <input type="hidden" name="action" value="add">
        <input type="text" name="title" placeholder="Task Title" required>
        <input type="date" name="task_date" required>
        <button type="submit">Add Task</button>
    </form>

    <!-- Calendar for Upcoming Tasks -->
    <h2>Upcoming Tasks</h2>
    <div id="calendar"></div>

    <!-- Task Details -->
    <div id="task-details">
        <h2>Tasks for Selected Day</h2>
        <ul id="task-list"></ul>
    </div>

    <script>
        const tasks = <?= $tasks_json; ?>;
        const calendar = document.getElementById('calendar');
        const taskList = document.getElementById('task-list');

        // Generate calendar for the current month
        const today = new Date();
        const currentMonth = today.getMonth();
        const currentYear = today.getFullYear();

        const firstDayOfMonth = new Date(currentYear, currentMonth, 1);
        const lastDayOfMonth = new Date(currentYear, currentMonth + 1, 0);

        const daysInMonth = lastDayOfMonth.getDate();
        const startDay = firstDayOfMonth.getDay();

        // Fill calendar with days
        for (let i = 0; i < startDay; i++) {
            const blankDay = document.createElement('div');
            calendar.appendChild(blankDay);
        }

        for (let day = 1; day <= daysInMonth; day++) {
            const date = new Date(currentYear, currentMonth, day).toISOString().split('T')[0];
            const dayDiv = document.createElement('div');
            dayDiv.textContent = day;

            if (tasks[date]) {
                dayDiv.classList.add('has-task');
            }

            dayDiv.addEventListener('click', () => {
                displayTasks(date);
            });

            calendar.appendChild(dayDiv);
        }

        // Display tasks for a selected day
        function displayTasks(date) {
            taskList.innerHTML = ''; // Clear the task list
            const tasksForDate = tasks[date] || [];
            tasksForDate.forEach(task => {
                const li = document.createElement('li');
                li.textContent = task;
                taskList.appendChild(li);
            });
        }
    </script>

<?php endif; ?>

</body>
</html>