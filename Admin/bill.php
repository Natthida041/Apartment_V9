<?php
require_once 'config.php'; // Include the database configuration file

$selected_room = $selected_month = $selected_year = "";
$rooms = [];
$months = [];
$years = [];

// Function to convert month number to Thai month name
function getThaiMonth($month) {
    $thaiMonths = [
        1 => 'มกราคม', 2 => 'กุมภาพันธ์', 3 => 'มีนาคม', 4 => 'เมษายน',
        5 => 'พฤษภาคม', 6 => 'มิถุนายน', 7 => 'กรกฎาคม', 8 => 'สิงหาคม',
        9 => 'กันยายน', 10 => 'ตุลาคม', 11 => 'พฤศจิกายน', 12 => 'ธันวาคม'
    ];
    return isset($thaiMonths[$month]) ? $thaiMonths[$month] : '';
}

// Function to convert Gregorian year to Buddhist year
function getBuddhistYear($year) {
    return $year + 543;
}

// Fetch distinct rooms, months, and years from the database for dropdown
$roomQuery = "SELECT DISTINCT Room_number FROM users WHERE Room_number IN ('201', '202', '302', '303', '304', '305', '306', '203', '204', '205', '206', '301', 'S1', 'S2') ORDER BY Room_number";
$monthQuery = "SELECT DISTINCT month FROM bill ORDER BY month";
$yearQuery = "SELECT DISTINCT year FROM bill ORDER BY year";

if ($roomResult = $conn->query($roomQuery)) {
    while ($row = $roomResult->fetch_assoc()) {
        $rooms[] = $row['Room_number'];
    }
}
$rooms[] = "ทั้งหมด"; // Add the option for "ทั้งหมด"
if ($monthResult = $conn->query($monthQuery)) {
    while ($row = $monthResult->fetch_assoc()) {
        $months[] = $row['month'];
    }
}
if ($yearResult = $conn->query($yearQuery)) {
    while ($row = $yearResult->fetch_assoc()) {
        $years[] = $row['year'];
    }
}

// Handle the form submission
$bill_details = null;
$records = [];
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['view_bill'])) {
    $selected_room = $_POST['selected_room'];
    $selected_month = $_POST['selected_month'];
    $selected_year = $_POST['selected_year'];

    if ($selected_room == "ทั้งหมด") {
        // Fetch the latest bill for each room in the selected month and year
        $sql = "SELECT b.*, u.Room_number, u.First_name, u.Last_name, 
                       CASE 
                           WHEN u.Room_number IN ('201', '202', '302', '303', '304', '305', '306', '203', '204', '205', '206', '301') THEN u.water_was 
                           WHEN u.Room_number = 'S1' THEN b.water_cost 
                           ELSE b.water_cost 
                       END as water_cost_display
                FROM bill b
                LEFT JOIN users u ON b.user_id = u.id
                WHERE b.month = ? AND b.year = ? AND u.Room_number IN ('201', '202', '302', '303', '304', '305', '306', '203', '204', '205', '206', '301', 'S1', 'S2')
                AND (u.Room_number, b.id) IN (
                    SELECT u.Room_number, MAX(b.id)
                    FROM bill b
                    LEFT JOIN users u ON b.user_id = u.id
                    WHERE b.month = ? AND b.year = ?
                    GROUP BY u.Room_number
                )
                ORDER BY u.Room_number";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iiii", $selected_month, $selected_year, $selected_month, $selected_year);
    } else {
        // Fetch the latest bill for the selected room
        $sql = "SELECT b.*, u.Room_number, u.First_name, u.Last_name, 
                       CASE 
                           WHEN u.Room_number IN ('201', '202', '302', '303', '304', '305', '306', '203', '204', '205', '206', '301') THEN u.water_was 
                           WHEN u.Room_number = 'S1' THEN b.water_cost 
                           ELSE b.water_cost 
                       END as water_cost_display
                FROM bill b
                LEFT JOIN users u ON b.user_id = u.id
                WHERE u.Room_number = ? AND b.month = ? AND b.year = ?
                ORDER BY b.id DESC LIMIT 1";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sii", $selected_room, $selected_month, $selected_year);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    if ($selected_room == "ทั้งหมด") {
        while ($row = $result->fetch_assoc()) {
            $records[] = $row;
        }
    } else {
        $bill_details = $result->fetch_assoc();
    }
}

$conn->close();

// Function to get current Thai month and Buddhist year
function getCurrentThaiMonthYear() {
    $currentMonth = date('n'); // Get current month as a number
    $currentYear = date('Y') + 543; // Get current year and convert to Buddhist year
    $thaiMonths = [
        1 => 'มกราคม', 2 => 'กุมภาพันธ์', 3 => 'มีนาคม', 4 => 'เมษายน', 5 => 'พฤษภาคม', 6 => 'มิถุนายน',
        7 => 'กรกฎาคม', 8 => 'สิงหาคม', 9 => 'กันยายน', 10 => 'ตุลาคม', 11 => 'พฤศจิกายน', 12 => 'ธันวาคม'
    ];
    return [$thaiMonths[$currentMonth], $currentYear];
}

list($currentThaiMonth, $currentBuddhistYear) = getCurrentThaiMonthYear();
?>


<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ใบเสร็จ</title>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;600&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="bill.css">
</head>
<body>
    <nav class="navbar">
        <span class="navbar-brand">เจ้าสัว Apartment</span>
        <div class="navbar-menu">
            <a href="logout.php">ออกจากระบบ</a>
        </div>
    </nav>
    
    <div class="container">
        <aside class="sidebar">
            <div class="sidebar-menu">
                <a href="index.php"><i class="fas fa-home"></i>หน้าหลัก</a>
                <a href="crud.php"><i class="fas fa-users"></i>จัดการข้อมูลผู้ใช้</a>
                <a href="total.php"><i class="fas fa-tint"></i>การคำนวณค่าน้ำ-ค่าไฟ</a>
                <a href="bill.php"><i class="fas fa-file-invoice"></i>พิมพ์เอกสาร</a>
                <a href="bill_back.php"><i class="fas fa-history"></i> รายการย้อนหลัง</a>
                <a href=""><i class="fas fa-chart-bar"></i> สรุปยอดรายเดือน/ปี</a>
            </div>
        </aside>

        <main class="content">
            <h1>เลือกข้อมูลสำหรับการออกใบเสร็จรับเงิน</h1>
            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                <div class="form-group">
                    <label for="selected_room">หมายเลขห้อง:</label>
                    <select id="selected_room" name="selected_room" required>
                        <?php foreach ($rooms as $room): ?>
                            <option value="<?php echo $room; ?>" <?php echo $room == $selected_room ? 'selected' : ''; ?>><?php echo $room; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="selected_month">เดือน:</label>
                    <select id="selected_month" name="selected_month" required>
                        <?php foreach ($months as $month): ?>
                            <option value="<?php echo $month; ?>" <?php echo $month == $selected_month ? 'selected' : ''; ?>><?php echo getThaiMonth($month); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="selected_year">ปี:</label>
                    <select id="selected_year" name="selected_year" required>
                        <?php foreach ($years as $year): ?>
                            <option value="<?php echo $year; ?>" <?php echo $year == $selected_year ? 'selected' : ''; ?>><?php echo getBuddhistYear($year); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" name="view_bill">ดูใบเสร็จรับเงิน</button>
            </form>
            <?php if ($selected_room == "ทั้งหมด" && !empty($records)): ?>
                <h2>ผลลัพธ์:</h2>
                <table class="table">
                    <thead>
                        <tr>
                            <th>หมายเลขห้อง</th>
                            <th>เดือน</th>
                            <th>ปี</th>
                            <th>ค่าไฟฟ้า</th>
                            <th>ค่าน้ำ</th>
                            <th>ค่าห้อง</th>
                            <th>ค่าใช้จ่ายทั้งหมด</th>
                            <th>พิมพ์ใบเสร็จ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($records as $record): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($record['Room_number']); ?></td>
                                <td><?php echo getThaiMonth(htmlspecialchars($record['month'])); ?></td>
                                <td><?php echo getBuddhistYear(htmlspecialchars($record['year'])); ?></td>
                                <td><?php echo htmlspecialchars($record['electric_cost']); ?> บาท</td>
                                <td><?php echo htmlspecialchars($record['water_cost_display']); ?> บาท</td>
                                <td><?php echo htmlspecialchars($record['room_cost']); ?> บาท</td>
                                <td><?php echo htmlspecialchars($record['total_cost']); ?> บาท</td>
                                <td>
                                    <button class="btn-action btn-warning" onclick="printRoomBill('<?php echo $record['Room_number']; ?>', '<?php echo $record['month']; ?>', '<?php echo $record['year']; ?>')">พิมพ์ใบเสร็จ</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <div class="print-button">
                    <button onclick="printAllBills()">พิมพ์ใบเสร็จทั้งหมด</button>
                </div>
                <?php elseif (!empty($bill_details)): ?>
                <div class="printable">
                    <h2>ใบเสร็จรับเงิน</h2>
                    <p>หมายเลขห้อง: <?php echo htmlspecialchars($bill_details['Room_number']); ?></p>
                    <p>ชื่อ: <?php echo htmlspecialchars($bill_details['First_name']); ?> <?php echo htmlspecialchars($bill_details['Last_name']); ?></p>
                    <p>เดือน/ปี: <?php echo getThaiMonth($selected_month); ?> <?php echo getBuddhistYear($selected_year); ?></p>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>รายการ</th>
                                <th>จำนวนหน่วย</th>
                                <th>ราคาต่อหน่วย</th>
                                <th>รวม(บาท)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>ค่าไฟฟ้า</td>
                                <td><?php echo htmlspecialchars($bill_details['electric_cost']); ?></td>
                                <td></td>
                                <td><?php echo htmlspecialchars($bill_details['electric_cost']); ?></td>
                            </tr>
                            <tr>
                                <td>ค่าน้ำ</td>
                                <td><?php echo htmlspecialchars($bill_details['water_cost_display']); ?></td>
                                <td></td>
                                <td><?php echo htmlspecialchars($bill_details['water_cost_display']); ?></td>
                            </tr>
                            <tr>
                                <td>ค่าห้อง</td>
                                <td><?php echo htmlspecialchars($bill_details['room_cost']); ?></td>
                                <td></td>
                                <td><?php echo htmlspecialchars($bill_details['room_cost']); ?></td>
                            </tr>
                        </tbody>
                    </table>
                    <div class="total">
                        <strong>ยอดรวม: <?php echo htmlspecialchars($bill_details['total_cost']); ?> บาท</strong>
                    </div>
                    <div class="payment-info">
                        <p><strong>โอนเงินเข้าบัญชี ธ.กรุงไทย (ชื่อบัญชี คุณกรรณิกา กุลจินต์)</strong></p>
                        <p>เลขบัญชี 915-1-16412-4 เท่านั้น ! (ชำระทุกวันที่ 1-5 นะคะ)</p>
                    </div>
                </div>
                <div class="print-button">
                    <button onclick="window.print()">พิมพ์ใบเสร็จ</button>
                </div>
            <?php endif; ?>
        </main>
    </div>
    <script>
        function printRoomBill(roomNumber, month, year) {
            var url = 'print_receipt.php?room=' + roomNumber + '&month=' + month + '&year=' + year;
            window.open(url, '_blank');
        }

        function printAllBills() {
            var url = 'print_receipt.php?room=ทั้งหมด&month=<?php echo $selected_month; ?>&year=<?php echo $selected_year; ?>';
            window.open(url, '_blank');
        }
    </script>

</body>
</html>
