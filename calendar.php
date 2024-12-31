<?php
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
session_start();

requireAuth();
$userId = $_SESSION['user_id'];
$conn = getDBConnection();

// Get user's teams
$stmt = $conn->prepare("
    SELECT DISTINCT t.team_id, t.team_name 
    FROM teams t 
    JOIN user_roles ur ON t.team_id = ur.team_id 
    WHERE ur.user_id = ?
");
$stmt->execute([$userId]);
$teams = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get selected month/year
$month = isset($_GET['month']) ? intval($_GET['month']) : date('n');
$year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');

// Get shifts for the month
$startDate = date('Y-m-d', mktime(0, 0, 0, $month, 1, $year));
$endDate = date('Y-m-t', mktime(0, 0, 0, $month, 1, $year));

$stmt = $conn->prepare("
    SELECT s.*, t.team_name, u.username
    FROM shifts s
    JOIN teams t ON s.team_id = t.team_id
    JOIN users u ON s.user_id = u.user_id
    WHERE s.start_time BETWEEN ? AND ?
    AND (s.team_id IN (
        SELECT team_id FROM user_roles WHERE user_id = ?
    ))
    AND s.status = 'approved'
");
$stmt->execute([$startDate, $endDate, $userId]);
$shifts = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Organize shifts by date
$shiftsByDate = [];
foreach ($shifts as $shift) {
    $date = date('Y-m-d', strtotime($shift['start_time']));
    if (!isset($shiftsByDate[$date])) {
        $shiftsByDate[$date] = [];
    }
    $shiftsByDate[$date][] = $shift;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shift Calendar - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .calendar-day { min-height: 100px; }
        .shift-item {
            font-size: 0.8em;
            padding: 2px 4px;
            margin: 1px 0;
            border-radius: 3px;
        }
        .shift-regular { background-color: #e3f2fd; }
        .shift-adhoc { background-color: #fff3e0; }
    </style>
</head>
<body>
    <?php include 'components/nav.php'; ?>
    
    <div class="container mt-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1>Shift Calendar</h1>
            <div>
                <form method="GET" class="d-flex">
                    <select name="month" class="form-select me-2">
                        <?php for ($i = 1; $i <= 12; $i++): ?>
                            <option value="<?php echo $i; ?>" <?php echo $month == $i ? 'selected' : ''; ?>>
                                <?php echo date('F', mktime(0, 0, 0, $i, 1)); ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                    <select name="year" class="form-select me-2">
                        <?php for ($i = date('Y') - 1; $i <= date('Y') + 1; $i++): ?>
                            <option value="<?php echo $i; ?>" <?php echo $year == $i ? 'selected' : ''; ?>>
                                <?php echo $i; ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                    <button type="submit" class="btn btn-primary">Go</button>
                </form>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-bordered">
                <thead>
                    <tr>
                        <?php
                        $days = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
                        foreach ($days as $day) {
                            echo "<th class='text-center'>{$day}</th>";
                        }
                        ?>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $firstDay = date('w', strtotime($startDate));
                    $lastDay = date('t', strtotime($startDate));
                    $currentDay = 1;
                    $currentDate = null;

                    for ($i = 0; $i < 6; $i++) {
                        echo "<tr>";
                        for ($j = 0; $j < 7; $j++) {
                            if (($i === 0 && $j < $firstDay) || ($currentDay > $lastDay)) {
                                echo "<td></td>";
                            } else {
                                $currentDate = date('Y-m-d', mktime(0, 0, 0, $month, $currentDay, $year));
                                echo "<td class='calendar-day'>";
                                echo "<div class='text-end'>{$currentDay}</div>";
                                
                                if (isset($shiftsByDate[$currentDate])) {
                                    foreach ($shiftsByDate[$currentDate] as $shift) {
                                        $class = $shift['shift_type'] === 'regular' ? 'shift-regular' : 'shift-adhoc';
                                        echo "<div class='shift-item {$class}'>";
                                        echo htmlspecialchars($shift['username']) . " - " . 
                                             htmlspecialchars($shift['team_name']);
                                        echo "</div>";
                                    }
                                }
                                
                                echo "</td>";
                                $currentDay++;
                            }
                        }
                        echo "</tr>";
                        if ($currentDay > $lastDay) break;
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 