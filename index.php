<?php
require 'db.php';

$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
$clientUrl = $protocol . "192.168.4.166/queue/client.php";

$queueNameFile = 'queue_name.txt';
$queueName = file_exists($queueNameFile) ? file_get_contents($queueNameFile) : 'Main Service Counter';

if (isset($_POST['create_queue'])) {
    $queueName = trim($_POST['q_name'] ?? 'Main Service Counter');
    file_put_contents($queueNameFile, $queueName);
    $pdo->query("TRUNCATE TABLE tickets");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QRQ Queue Manager</title>
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <style>
        #qrcode, #qrcode * {
            width: 220px;
            height: 220px;
        }

        @media print {
            @page {
                size: auto;
                margin: 0mm;
            }
            .no-print { 
                display: none !important; 
            }
            body { 
                background: #ffffff !important;
                margin: 0 !important; 
                padding: 0 !important;
            }
            #print-placard {
                width: 100% !important;
                max-width: 100% !important;
                height: 100vh !important;
                position: absolute !important;
                left: 0 !important;
                top: 0 !important;
                box-shadow: none !important;
                border: none !important;
                background-color: #2563eb !important;
                -webkit-print-color-adjust: exact; 
                print-color-adjust: exact;
                display: flex !important;
                flex-direction: column !important;
                align-items: center !important;
                justify-content: center !important;
                padding: 4rem !important;
                box-sizing: border-box !important;
            }
            
            .qr-print-wrapper {
                width: 390px !important;
                height: 390px !important;
                display: flex !important;
                align-items: center !important;
                justify-content: center !important;
                background: white !important;
                border-radius: 2.5rem !important;
                box-shadow: none !important;
            }

            #qrcode, #qrcode * {
                width: 340px !important;
                height: 340px !important;
                max-width: 340px !important;
                max-height: 340px !important;
            }
        }
    </style>
</head>
<body class="bg-gray-100 text-gray-800 min-h-screen font-sans">

    <div class="max-w-4xl mx-auto p-6">
        <div class="bg-white rounded-2xl shadow-sm p-6 border border-gray-200 mb-6 no-print">
            <h1 class="text-xl font-bold mb-4 text-gray-900">1. Define Your Queue Station</h1>
            <form method="POST" class="flex flex-col sm:flex-row gap-3">
                <input type="text" name="q_name" value="<?php echo htmlspecialchars($queueName); ?>" class="flex-1 px-4 py-2 border border-gray-300 rounded-xl focus:outline-blue-500 text-base" required>
                <button type="submit" name="create_queue" class="bg-blue-600 hover:bg-blue-700 text-white font-semibold px-6 py-2 rounded-xl transition">
                    Generate New Queue
                </button>
            </form>
        </div>

        <div class="grid md:grid-cols-2 gap-6">
            <div id="print-placard" class="bg-blue-600 text-white rounded-3xl p-8 text-center flex flex-col items-center justify-between shadow-lg border border-blue-700 min-h-[520px]">
                <div class="w-full">
                    <span class="bg-white/20 text-white text-xs font-black px-4 py-1.5 rounded-full tracking-widest uppercase">SCAN TO ENTER</span>
                    <h2 class="text-3xl font-black mt-6 mb-2 tracking-tight uppercase"><?php echo htmlspecialchars($queueName); ?></h2>
                    <p class="text-blue-100 text-sm mb-8 opacity-90">No account required. Scan the code below to take a virtual priority number instantly.</p>
                </div>
                
                <div class="qr-print-wrapper w-[260px] h-[260px] bg-white rounded-[2rem] shadow-xl flex items-center justify-center mx-auto">
                    <div id="qrcode" class="flex items-center justify-center"></div>
                </div>

                <div class="w-full mt-8 no-print">
                    <button onclick="window.print()" class="w-full bg-white text-blue-600 hover:bg-blue-50 font-black py-3 px-4 rounded-xl transition shadow">
                        Print Live Stand Placard
                    </button>
                </div>
            </div>

            <div class="bg-white rounded-2xl shadow-sm p-6 border border-gray-200 flex flex-col justify-between no-print">
                <div>
                    <div class="flex justify-between items-center mb-4">
                        <h2 class="font-bold text-gray-900 text-base">2. Line Operator Console</h2>
                        <a href="display.php" target="_blank" class="text-sm font-semibold text-blue-600 hover:underline">Open TV Panel &rarr;</a>
                    </div>
                    
                    <div class="bg-gray-50 border border-gray-200 rounded-xl p-4 mb-4 flex justify-between items-center">
                        <div>
                            <span class="text-xs uppercase font-bold text-green-600 tracking-wider">Now Serving</span>
                            <div id="admin-name" class="text-lg font-bold text-gray-700 mt-0.5">---</div>
                        </div>
                        <div id="admin-num" class="text-4xl font-black text-green-600">--</div>
                    </div>

                    <h3 class="text-xs uppercase font-bold text-gray-400 tracking-wider mb-2">Up Next In Line</h3>
                    <div id="admin-waitlist" class="space-y-2 max-h-44 overflow-y-auto"></div>
                </div>

                <div class="space-y-3 mt-6">
                    <button id="break-btn" onclick="toggleBreak()" class="w-full bg-amber-600 hover:bg-amber-700 text-white font-bold py-2.5 rounded-xl text-sm transition shadow shadow-amber-100 flex items-center justify-center gap-2">
                        GO ON BREAK
                    </button>
                    <button onclick="markDone()" class="w-full bg-green-600 hover:bg-green-700 text-white font-black py-3 rounded-xl text-md transition shadow-md flex items-center justify-center gap-2">
                        MARK TRANSACTION AS DONE
                    </button>
                    <button onclick="callNext()" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-black py-4 rounded-xl text-lg transition shadow-lg flex items-center justify-center gap-2">
                        CALL NEXT VISITOR
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        const qrContainer = document.getElementById("qrcode");
        new QRCode(qrContainer, {
            text: "<?php echo $clientUrl; ?>",
            width: 340, 
            height: 340,
            colorDark : "#000000",
            colorLight : "#ffffff",
            correctLevel : QRCode.CorrectLevel.H
        });

        async function fetchAdminData() {
            const res = await fetch('admin.php?action=status');
            const data = await res.json();

            const breakBtn = document.getElementById('break-btn');
            if (breakBtn) {
                if (data.onBreak) {
                    breakBtn.innerText = "RESUME SYSTEM SERVICE";
                    breakBtn.className = "w-full bg-slate-700 hover:bg-slate-800 text-white font-bold py-2.5 rounded-xl text-sm transition shadow flex items-center justify-center gap-2";
                    document.getElementById('admin-num').innerText = 'BRK';
                    document.getElementById('admin-name').innerText = 'System on Lunch Break';
                } else {
                    breakBtn.innerText = "GO ON LUNCH BREAK";
                    breakBtn.className = "w-full bg-amber-600 hover:bg-amber-700 text-white font-bold py-2.5 rounded-xl text-sm transition shadow flex items-center justify-center gap-2";
                }
            }

            if (!data.onBreak) {
                if (data.currentServing) {
                    const paddedServing = String(data.currentServing.ticket_number).padStart(3, '0');
                    document.getElementById('admin-num').innerText = paddedServing;
                    document.getElementById('admin-name').innerText = data.currentServing.client_name;
                } else {
                    document.getElementById('admin-num').innerText = '--';
                    document.getElementById('admin-name').innerText = 'No active lines';
                }
            }

            const waitlist = document.getElementById('admin-waitlist');
            waitlist.innerHTML = '';

            if (data.waitingList.length === 0) {
                waitlist.innerHTML = `<div class="text-sm text-gray-400 italic p-3 text-center">Queue is empty</div>`;
            } else {
                data.waitingList.forEach(ticket => {
                    const paddedWaitNum = String(ticket.ticket_number).padStart(3, '0');
                    waitlist.innerHTML += `
                        <div class="flex justify-between items-center bg-gray-50 border border-gray-100 p-2.5 rounded-lg text-sm">
                            <span class="font-medium text-gray-700">${ticket.client_name}</span>
                            <span class="font-bold text-blue-600">${paddedWaitNum}</span>
                        </div>`;
                });
            }
        }

        async function callNext() {
            await fetch('admin.php?action=next');
            fetchAdminData();
        }

        async function markDone() {
            await fetch('admin.php?action=done');
            fetchAdminData();
        }

        async function toggleBreak() {
            await fetch('admin.php?action=toggle_break');
            fetchAdminData();
        }

        fetchAdminData();
        setInterval(fetchAdminData, 3000);
    </script>
</body>
</html>