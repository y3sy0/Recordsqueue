<?php
require 'db.php';

$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
$host = '192.168.4.166'; 
$clientUrl = $protocol . $host . "/queue/client.php";

$queueNameFile = 'queue_name.txt';
$queueName = file_exists($queueNameFile) ? file_get_contents($queueNameFile) : 'Main Service Counter';

$onBreak = (file_exists('break_status.txt') && file_get_contents('break_status.txt') === 'yes');
$action = $_GET['action'] ?? '';

if (isset($_POST['create_queue'])) {
    $queueName = trim($_POST['q_name'] ?? 'Main Service Counter');
    file_put_contents($queueNameFile, $queueName);
    $pdo->query("TRUNCATE TABLE tickets");
    header("Location: admin.php");
    exit;
}

if ($action == 'status') {
    header('Content-Type: application/json');
    
    $recActive = $pdo->query("SELECT ticket_number FROM tickets WHERE status = 'calling' AND transaction_type = 'receiving' AND DATE(created_at) = CURRENT_DATE ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    $relActive = $pdo->query("SELECT ticket_number FROM tickets WHERE status = 'calling' AND transaction_type = 'releasing' AND DATE(created_at) = CURRENT_DATE ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'onBreak' => $onBreak,
        'recActive' => $recActive ? $recActive['ticket_number'] : null,
        'relActive' => $relActive ? $relActive['ticket_number'] : null
    ]);
    exit;
}

if ($action == 'next') {
    $type = isset($_GET['type']) ? strtolower(trim($_GET['type'])) : '';
    if ($type === 'receiving' || $type === 'releasing') {
        $stmt = $pdo->prepare("UPDATE tickets SET status = 'completed' WHERE status = 'calling' AND transaction_type = ? AND DATE(created_at) = CURRENT_DATE");
        $stmt->execute([$type]);

        $stmt = $pdo->prepare("SELECT id FROM tickets WHERE status = 'waiting' AND transaction_type = ? AND DATE(created_at) = CURRENT_DATE ORDER BY id ASC LIMIT 1");
        $stmt->execute([$type]);
        $nextTicket = $stmt->fetch();

        if ($nextTicket) {
            $stmt = $pdo->prepare("UPDATE tickets SET status = 'calling' WHERE id = ?");
            $stmt->execute([$nextTicket['id']]);
        }
    }
    header("Location: admin.php");
    exit;
}

if ($action == 'recall') {
    $type = isset($_GET['type']) ? strtolower(trim($_GET['type'])) : 'receiving';
    $stmt = $pdo->prepare("SELECT id FROM tickets WHERE status = 'calling' AND transaction_type = ? AND DATE(created_at) = CURRENT_DATE ORDER BY id DESC LIMIT 1");
    $stmt->execute([$type]);
    $currentTicket = $stmt->fetch();
    if ($currentTicket) {
        $stmt = $pdo->prepare("UPDATE tickets SET updated_at = NOW() WHERE id = ?");
        $stmt->execute([$currentTicket['id']]);
    }
    header("Location: admin.php");
    exit;
}

if ($action == 'complete') {
    $type = isset($_GET['type']) ? strtolower(trim($_GET['type'])) : 'receiving';
    $stmt = $pdo->prepare("UPDATE tickets SET status = 'completed' WHERE status = 'calling' AND transaction_type = ? AND DATE(created_at) = CURRENT_DATE");
    $stmt->execute([$type]);
    header("Location: admin.php");
    exit;
}

if ($action == 'toggle_break') {
    file_put_contents('break_status.txt', $onBreak ? 'no' : 'yes');
    header("Location: admin.php");
    exit;
}

$totalWaitingRec = $pdo->query("SELECT COUNT(*) FROM tickets WHERE status = 'waiting' AND transaction_type = 'receiving' AND DATE(created_at) = CURRENT_DATE")->fetchColumn();
$totalWaitingRel = $pdo->query("SELECT COUNT(*) FROM tickets WHERE status = 'waiting' AND transaction_type = 'releasing' AND DATE(created_at) = CURRENT_DATE")->fetchColumn();

$recActiveNum = $pdo->query("SELECT ticket_number FROM tickets WHERE status = 'calling' AND transaction_type = 'receiving' AND DATE(created_at) = CURRENT_DATE ORDER BY id DESC LIMIT 1")->fetchColumn();
$relActiveNum = $pdo->query("SELECT ticket_number FROM tickets WHERE status = 'calling' AND transaction_type = 'releasing' AND DATE(created_at) = CURRENT_DATE ORDER BY id DESC LIMIT 1")->fetchColumn();

$currentRecDisplay = $recActiveNum ? 'REC-' . str_pad($recActiveNum, 3, '0', STR_PAD_LEFT) : '--';
$currentRelDisplay = $relActiveNum ? 'REL-' . str_pad($relActiveNum, 3, '0', STR_PAD_LEFT) : '--';

$nextRecStmt = $pdo->prepare("SELECT ticket_number FROM tickets WHERE status = 'waiting' AND transaction_type = 'receiving' AND DATE(created_at) = CURRENT_DATE ORDER BY id ASC LIMIT 3");
$nextRecStmt->execute();
$recList = $nextRecStmt->fetchAll(PDO::FETCH_COLUMN);
$recTrailHtml = !empty($recList) ? implode(', ', array_map(fn($n) => 'REC-'.str_pad($n,3,'0',STR_PAD_LEFT), $recList)) : 'None pending';

$nextRelStmt = $pdo->prepare("SELECT ticket_number FROM tickets WHERE status = 'waiting' AND transaction_type = 'releasing' AND DATE(created_at) = CURRENT_DATE ORDER BY id ASC LIMIT 3");
$nextRelStmt->execute();
$relList = $nextRelStmt->fetchAll(PDO::FETCH_COLUMN);
$relTrailHtml = !empty($relList) ? implode(', ', array_map(fn($n) => 'REL-'.str_pad($n,3,'0',STR_PAD_LEFT), $relList)) : 'None pending';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Queue</title>
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <style>
        #qrcode, #qrcode * {
            width: 180px;
            height: 180px;
        }
        @media print {
            @page { size: auto; margin: 0mm; }
            .no-print { display: none !important; }
            body { background: #ffffff !important; margin: 0 !important; padding: 0 !important; }
            #print-placard {
                width: 100% !important; max-width: 100% !important; height: 100vh !important;
                position: absolute !important; left: 0 !important; top: 0 !important;
                box-shadow: none !important; border: none !important; background-color: #2563eb !important;
                -webkit-print-color-adjust: exact; print-color-adjust: exact;
                display: flex !important; flex-direction: column !important; align-items: center !important; justify-content: center !important; padding: 4rem !important;
            }
            .qr-print-wrapper { width: 320px !important; height: 320px !important; background: white !important; border-radius: 2rem !important; display: flex !important; align-items: center !important; justify-content: center !important; }
            #qrcode, #qrcode * { width: 260px !important; height: 260px !important; }
        }
    </style>
</head>
<body class="bg-gray-50 p-4 sm:p-8 min-h-screen font-sans text-gray-800">
    <div class="max-w-5xl mx-auto">
        
        <div class="bg-white rounded-3xl shadow-sm p-6 border border-gray-200 mb-6 no-print flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
            <div class="flex-1 w-full">
                <h1 class="text-sm font-bold uppercase tracking-wider text-gray-400 mb-2">1. Station Config & Control</h1>
                <form method="POST" class="flex flex-col sm:flex-row gap-3 w-full">
                    <input type="text" name="q_name" value="<?php echo htmlspecialchars($queueName); ?>" class="flex-1 px-4 py-2.5 border border-gray-300 rounded-xl focus:outline-blue-500 font-medium text-base text-gray-800" required>
                    <button type="submit" name="create_queue" onclick="return confirm('Generate New Queue? This updates station name and truncates/resets all active rows.')" class="bg-blue-600 hover:bg-blue-700 text-white font-bold px-6 py-2.5 rounded-xl transition shadow active:scale-95 text-sm uppercase tracking-wide">
                        Generate New Queue
                    </button>
                </form>
            </div>
            <div class="no-print pt-0 md:pt-7 flex items-center gap-2">
                <a href="display.php" target="_blank" class="px-5 py-3 font-bold text-xs uppercase tracking-wider rounded-xl transition bg-blue-50 text-blue-600 hover:bg-blue-100 border border-blue-200 shadow-sm active:scale-95 inline-block">
                    📺 Open TV Display
                </a>
                
                <a href="admin.php?action=toggle_break" class="px-6 py-3 font-bold text-xs uppercase tracking-wider rounded-xl transition shadow-sm inline-block active:scale-95 <?php echo $onBreak ? 'bg-amber-500 text-white' : 'bg-slate-900 text-white'; ?>">
                    <?php echo $onBreak ? '☕ Resume Lines' : '🛑 Go On Lunch Break'; ?>
                </a>
            </div>
        </div>

        <div id="print-placard" class="bg-blue-600 text-white rounded-3xl p-6 text-center flex flex-col sm:flex-row items-center justify-between shadow-lg border border-blue-700 mb-8 min-h-[240px]">
            <div class="text-left max-w-md p-2">
                <span class="bg-white/20 text-white text-[10px] font-black px-3 py-1 rounded-full tracking-widest uppercase">SCAN TO ENTER LAYOUT</span>
                <h2 class="text-2xl font-black mt-3 mb-1 tracking-tight uppercase"><?php echo htmlspecialchars($queueName); ?></h2>
                <p class="text-blue-100 text-xs opacity-90 max-w-sm mb-4">Scan code to take a virtual priority voucher instantly. Managed side-by-side below.</p>
                <button onclick="window.print()" class="no-print bg-white text-blue-600 hover:bg-blue-50 font-black text-xs py-2.5 px-4 rounded-xl transition shadow uppercase tracking-wide">
                    🖨 Print Stand Placard
                </button>
            </div>
            
            <div class="qr-print-wrapper p-4 bg-white rounded-2xl shadow-md flex items-center justify-center mt-4 sm:mt-0">
                <div id="qrcode"></div>
            </div>
        </div>

        <div style="display: flex; gap: 24px; width: 100%;" class="no-print" id="dashboard-split-panels">
            
            <div class="bg-white border border-gray-200 rounded-[2.5rem] p-8 flex flex-col justify-between shadow-sm relative overflow-hidden" style="flex: 1;">
                <div class="absolute top-0 left-0 right-0 h-2 bg-blue-500"></div>
                <div>
                    <div class="flex justify-between items-center mb-6">
                        <span class="bg-blue-50 text-blue-600 px-4 py-1.5 rounded-full text-xs font-black tracking-widest uppercase">STATION 1: RECEIVING</span>
                        <span class="text-xs font-bold text-gray-400 bg-gray-50 px-3 py-1.5 rounded-lg border">Waiting: <strong class="text-blue-600 font-black text-sm"><?php echo $totalWaitingRec; ?></strong></span>
                    </div>
                    <div class="text-center bg-gradient-to-b from-gray-50 to-white border rounded-2xl py-6 mb-4">
                        <span class="block text-xs font-bold tracking-widest text-gray-400 uppercase mb-1">Now Calling</span>
                        <div class="text-6xl font-black font-mono text-blue-600"><?php echo $currentRecDisplay; ?></div>
                    </div>
                    <div class="grid grid-cols-2 gap-3 mb-6">
                        <a href="admin.php?action=recall&type=receiving" class="text-center font-bold text-xs bg-gray-100 text-gray-600 py-3 rounded-xl uppercase tracking-wider transition">🔄 Recall</a>
                        <a href="admin.php?action=complete&type=receiving" class="text-center font-bold text-xs bg-gray-100 text-gray-600 py-3 rounded-xl uppercase tracking-wider transition">✅ Close</a>
                    </div>
                </div>
                <div>
                    <div class="mb-4 text-xs font-bold text-gray-400 uppercase px-1">Up Next: <span class="font-mono text-gray-600"><?php echo $recTrailHtml; ?></span></div>
                    <a href="admin.php?action=next&type=receiving" class="w-full bg-blue-600 hover:bg-blue-700 text-white text-center font-black text-xl py-5 rounded-2xl shadow-lg block uppercase tracking-wide">Call Next Visitor</a>
                </div>
            </div>

            <div class="bg-white border border-gray-200 rounded-[2.5rem] p-8 flex flex-col justify-between shadow-sm relative overflow-hidden" style="flex: 1;">
                <div class="absolute top-0 left-0 right-0 h-2 bg-emerald-500"></div>
                <div>
                    <div class="flex justify-between items-center mb-6">
                        <span class="bg-emerald-50 text-emerald-600 px-4 py-1.5 rounded-full text-xs font-black tracking-widest uppercase">STATION 2: RELEASING</span>
                        <span class="text-xs font-bold text-gray-400 bg-gray-50 px-3 py-1.5 rounded-lg border">Waiting: <strong class="text-emerald-600 font-black text-sm"><?php echo $totalWaitingRel; ?></strong></span>
                    </div>
                    <div class="text-center bg-gradient-to-b from-gray-50 to-white border rounded-2xl py-6 mb-4">
                        <span class="block text-xs font-bold tracking-widest text-gray-400 uppercase mb-1">Now Calling</span>
                        <div class="text-6xl font-black font-mono text-emerald-600"><?php echo $currentRelDisplay; ?></div>
                    </div>
                    <div class="grid grid-cols-2 gap-3 mb-6">
                        <a href="admin.php?action=recall&type=releasing" class="text-center font-bold text-xs bg-gray-100 text-gray-600 py-3 rounded-xl uppercase tracking-wider transition">🔄 Recall</a>
                        <a href="admin.php?action=complete&type=releasing" class="text-center font-bold text-xs bg-gray-100 text-gray-600 py-3 rounded-xl uppercase tracking-wider transition">✅ Close</a>
                    </div>
                </div>
                <div>
                    <div class="mb-4 text-xs font-bold text-gray-400 uppercase px-1">Up Next: <span class="font-mono text-gray-600"><?php echo $relTrailHtml; ?></span></div>
                    <a href="admin.php?action=next&type=releasing" class="w-full bg-emerald-600 hover:bg-emerald-700 text-white text-center font-black text-xl py-5 rounded-2xl shadow-lg block uppercase tracking-wide">Call Next Visitor</a>
                </div>
            </div>

        </div>
    </div>

    <script>
        const qrContainer = document.getElementById("qrcode");
        new QRCode(qrContainer, {
            text: "<?php echo $clientUrl; ?>",
            width: 260,
            height: 260,
            colorDark : "#000000",
            colorLight : "#ffffff",
            correctLevel : QRCode.CorrectLevel.H
        });

        async function fetchLiveQueueUpdates() {
            try {
                const response = await fetch('admin.php?action=status');
                if (!response.ok) return;
                
                const refreshRes = await fetch('admin.php');
                const htmlText = await refreshRes.text();
                
                const parser = new DOMParser();
                const doc = parser.parseFromString(htmlText, 'text/html');
                
                const currentPanels = document.getElementById("dashboard-split-panels");
                const targetPanels = doc.getElementById("dashboard-split-panels");
                if (currentPanels && targetPanels) {
                    currentPanels.innerHTML = targetPanels.innerHTML;
                }

            } catch (error) {
                console.log("Queue sync error: ", error);
            }
        }

        setInterval(fetchLiveQueueUpdates, 3000);
    </script>
</body>
</html>