<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth-check.php';

//if (!hasRole(['admin', 'manager'])) {
if (!hasRole('admin', 'manager')) {
    header('Location: ' . BASE_URL . '/pages/dashboard.php');
    exit;
}

// Get filter parameters
$month = isset($_GET['month']) ? (int)$_GET['month'] : date('n');
$year = isset($_GET['year']) ? (int)$_GET['year'] : date('Y');
$project_id = isset($_GET['project_id']) ? (int)$_GET['project_id'] : null;
$user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : null;
$status = isset($_GET['status']) ? $_GET['status'] : null;
$role = isset($_GET['role']) ? $_GET['role'] : null;

// Build query
$db = new Database();
$pdo = $db->getConnection();

$query = "
    SELECT 
        ts.timesheet_id,
        u.user_id,
        u.first_name,
        u.last_name,
        u.email,
        u.role as user_role,
        p.project_id,
        p.project_name,
        ts.status,
        ts.month,
        ts.year,
        ts.submitted_at,
        ts.approved_at,
        pa.hours_allocated,
        CONCAT(approver.first_name, ' ', approver.last_name) as approver_name,
        te.*
    FROM timesheets ts
    JOIN timesheet_entries te ON ts.timesheet_id = te.timesheet_id
    JOIN users u ON ts.user_id = u.user_id
    JOIN projects p ON te.project_id = p.project_id
    LEFT JOIN project_allocations pa ON pa.user_id = ts.user_id 
        AND pa.project_id = p.project_id
        AND (ts.month BETWEEN MONTH(pa.start_date) AND MONTH(pa.end_date))
        AND (ts.year BETWEEN YEAR(pa.start_date) AND YEAR(pa.end_date))
    LEFT JOIN users approver ON ts.approved_by = approver.user_id
    WHERE ts.month = :month AND ts.year = :year
";

$params = [
    ':month' => $month,
    ':year' => $year
];

// Add filters
if ($project_id) {
    $query .= " AND p.project_id = :project_id";
    $params[':project_id'] = $project_id;
}

if ($user_id) {
    $query .= " AND u.user_id = :user_id";
    $params[':user_id'] = $user_id;
}

if ($status) {
    $query .= " AND ts.status = :status";
    $params[':status'] = $status;
}

if ($role) {
    $query .= " AND u.role = :role";
    $params[':role'] = $role;
}

try {
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $reportData = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Error generating report: " . $e->getMessage());
}

// Clean output buffer
if (ob_get_length()) {
    ob_end_clean();
}

// Create filename with filters and timestamp
$filename_parts = [
    'timesheet_report',
    $year . '-' . str_pad($month, 2, '0', STR_PAD_LEFT),
    $project_id ? 'project_' . $project_id : '',
    $user_id ? 'user_' . $user_id : '',
    $status ? 'status_' . $status : '',
    $role ? 'role_' . $role : '',
    date('Ymd_His')
];

$filename = implode('_', array_filter($filename_parts)) . '.xls';

// Set headers for Excel download
header('Content-Type: application/vnd.ms-excel');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

// Function to get day name
function getDayName($year, $month, $day)
{
    return date('D', mktime(0, 0, 0, $month, $day, $year));
}

// Start Excel content
echo '<html xmlns:x="urn:schemas-microsoft-com:office:excel">
<head>
    <!--[if gte mso 9]>
    <xml>
        <x:ExcelWorkbook>
            <x:ExcelWorksheets>
                <x:ExcelWorksheet>
                    <x:Name>Timesheet Report</x:Name>
                    <x:WorksheetOptions>
                        <x:DisplayGridlines/>
                    </x:WorksheetOptions>
                </x:ExcelWorksheet>
            </x:ExcelWorksheets>
        </x:ExcelWorkbook>
    </xml>
    <![endif]-->
    <style>
        table {
            border-collapse: collapse;
            width: 100%;
            font-family: Arial, sans-serif;
        }
        th, td {
            border: 1px solid #dddddd;
            text-align: center;
            padding: 8px;
            font-size: 12px;
        }
        th {
            background-color: #f2f2f2;
            font-weight: bold;
        }
        tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        .report-title {
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 10px;
            text-align: center;
        }
        .report-subtitle {
            font-size: 16px;
            margin-bottom: 15px;
            text-align: center;
        }
        .report-info {
            margin-bottom: 20px;
            font-size: 12px;
        }
        .report-info div {
            margin-bottom: 5px;
        }
        .day-header {
            font-size: 10px;
            line-height: 1.2;
        }
        .day-number {
            font-weight: bold;
        }
    </style>
</head>
<body>';

// Report title and period
echo '<div class="report-title">Employee Timesheet Report</div>';
echo '<div class="report-subtitle">' . date('F Y', mktime(0, 0, 0, $month, 1, $year)) . '</div>';

// Report metadata
echo '<div class="report-info">';
echo '<div><strong>Generated on:</strong> ' . date('Y-m-d H:i:s') . '</div>';
echo '<div><strong>Filters applied:</strong> ';
$filters = [];
if ($project_id) $filters[] = 'Project: ' . $project_id;
if ($user_id) $filters[] = 'User: ' . $user_id;
if ($status) $filters[] = 'Status: ' . ucfirst($status);
if ($role) $filters[] = 'Role: ' . ucfirst($role);
echo count($filters) ? implode(', ', $filters) : 'None';
echo '</div>';
echo '</div>';

// Start data table
echo "<table>
    <tr>
        <th rowspan='2'>Employee</th>
        <th rowspan='2'>First Name</th>
        <th rowspan='2'>Last Name</th>
        <th rowspan='2'>Email</th>
        <th rowspan='2'>Role</th>
        <th rowspan='2'>Project</th>
        <th rowspan='2'>Status</th>
        <th rowspan='2'>Submitted</th>
        <th rowspan='2'>Approver</th>
        <th rowspan='2'>Approved</th>
        <th rowspan='2'>Allocated Hours</th>";

// Add day headers (two rows)
$daysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, $year);
echo '<th colspan="' . $daysInMonth . '">Daily Hours</th>';
echo '<th rowspan="2">Total Hours</th>';
echo '</tr><tr>';

// Add day sub-headers with date and day name
for ($day = 1; $day <= $daysInMonth; $day++) {
    $dayName = getDayName($year, $month, $day);
    echo "<th class='day-header'><span class='day-number'>$day</span><br>$dayName</th>";
}

echo "</tr>";

foreach ($reportData as $row) {
    echo "<tr>
            <td>" . htmlspecialchars($row['first_name'] . ' ' . $row['last_name']) . "</td>
            <td>" . htmlspecialchars($row['first_name']) . "</td>
            <td>" . htmlspecialchars($row['last_name']) . "</td>
            <td>" . htmlspecialchars($row['email']) . "</td>
            <td>" . htmlspecialchars($row['user_role']) . "</td>
            <td>" . htmlspecialchars($row['project_name']) . "</td>
            <td>" . htmlspecialchars($row['status']) . "</td>
            <td>" . ($row['submitted_at'] ? date('m/d/Y H:i', strtotime($row['submitted_at'])) : '') . "</td>
            <td>" . htmlspecialchars($row['approver_name'] ?? '') . "</td>
            <td>" . ($row['approved_at'] ? date('m/d/Y H:i', strtotime($row['approved_at'])) : '') . "</td>
            <td>" . $row['hours_allocated'] . "</td>";

    // Add day values
    for ($day = 1; $day <= $daysInMonth; $day++) {
        echo "<td>" . ($row["day_$day"] ?? '') . "</td>";
    }

    echo "<td>" . $row['total_hours'] . "</td></tr>";
}

echo "</table></body></html>";
exit;
