<?php
require 'db.php';

if (isset($_GET['action']) && $_GET['action'] == 'join') {
    header('Content-Type: application/json');
    
    $stmt = $pdo->query("SELECT MAX(ticket_number) AS max_num FROM tickets WHERE DATE(created_at) = CURRENT_DATE");
    $row = $stmt->fetch();
    $next_num = ($row['max_num']) ? $row['max_num'] + 1 : 1;

    $stmt = $pdo->prepare("INSERT INTO tickets (ticket_number, client_name, status) VALUES (?, 'Visitor', 'waiting')");
    $stmt->execute([$next_num]);
    $lastId = $pdo->lastInsertId();

    echo json_encode(['id' => $lastId, 'ticket_number' => $next_num]);
    exit;
}

if (isset($_GET['action']) && $_GET['action'] == 'ticket_status') {
    header('Content-Type: application/json');
    $id = $_GET['id'] ?? 0;

    $stmt = $pdo->prepare("SELECT * FROM tickets WHERE id = ?");
    $stmt->execute([$id]);
    $myTicket = $stmt->fetch();

    if (!$myTicket) {
        echo json_encode(['error' => 'Not found']);
        exit;
    }

    $stmt = $pdo->prepare("SELECT COUNT(*) AS ahead FROM tickets WHERE status = 'waiting' AND ticket_number < ? AND DATE(created_at) = CURRENT_DATE");
    $stmt->execute([$myTicket['ticket_number']]);
    $aheadCount = $stmt->fetch()['ahead'];

    $stmt = $pdo->query("SELECT ticket_number FROM tickets WHERE status = 'calling' AND DATE(created_at) = CURRENT_DATE ORDER BY id DESC LIMIT 1");
    $servingRow = $stmt->fetch();
    $currentServing = $servingRow ? $servingRow['ticket_number'] : 'None';

    // --- INTEGRATED PHP BACKEND: Check the text file for break status ---
    $onBreak = (file_exists('break_status.txt') && file_get_contents('break_status.txt') === 'yes');

    echo json_encode([
        'myTicket' => $myTicket,
        'aheadCount' => $aheadCount,
        'currentServing' => $currentServing,
        'onBreak' => $onBreak // <-- Sending this to your script now!
    ]);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Get Ticket Number</title>
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
</head>
<body class="bg-gray-50 flex items-center justify-center min-h-screen p-4 font-sans selection:bg-none">

    <div id="alert-screen" class="hidden fixed inset-0 bg-green-600 z-50 flex flex-col items-center justify-center p-6 text-center text-white">
        <h1 class="text-5xl font-black tracking-tight mb-4">IT'S YOUR TURN!</h1>
        <p class="text-xl font-medium opacity-90 mb-8">Please proceed to the service counter immediately.</p>
        <div id="alert-number" class="text-8xl font-black bg-white text-green-700 px-8 py-4 rounded-3xl shadow-xl font-mono">---</div>
    </div>

    <div class="w-full max-w-md bg-white rounded-2xl shadow-xl p-6 text-center border border-gray-100">
        <div id="join-form">
            <h1 class="text-3xl font-extrabold text-blue-600 mb-2">Records Queue</h1>
            <p class="text-gray-500 mb-8">Tap below to secure your position in the Records virtual line.</p>
            <button onclick="joinQueue()" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-black text-xl py-5 rounded-2xl shadow-xl shadow-blue-200 transition transform active:scale-95">
                GET TICKET NUMBER
            </button>
        </div>

        <div id="ticket-status" class="hidden">
            <h2 class="text-xl font-bold text-gray-400 uppercase tracking-widest text-sm">Your Virtual Token</h2>
            <div id="my-number" class="text-7xl font-black text-blue-600 my-4 font-mono">---</div>
            
            <div class="grid grid-cols-2 gap-4 border-t border-gray-100 pt-6 mt-6">
                <div class="bg-blue-50 p-4 rounded-xl">
                    <span class="block text-xs text-blue-500 uppercase tracking-wider font-bold">Now Serving</span>
                    <span id="live-serving" class="text-2xl font-black text-blue-900 font-mono">---</span>
                </div>
                <div class="bg-orange-50 p-4 rounded-xl">
                    <span class="block text-xs text-orange-500 uppercase tracking-wider font-bold">People Ahead</span>
                    <span id="live-ahead" class="text-2xl font-black text-orange-900 font-mono">---</span>
                </div>
            </div>
            <p class="text-xs text-gray-400 mt-6">Leave this window open. Your phone will ring when you are called.</p>
        </div>
    </div>

    <script>
        let myTicketId = localStorage.getItem('php_queue_id');
        let alarmInterval = null;

        if (myTicketId) {
            showTicketView();
            startPolling();
        }

        async function joinQueue() {
            try { playPhoneNotification(true); } catch(e){}

            const res = await fetch('client.php?action=join', { method: 'POST' });
            const data = await res.json();
            
            if(data.id) {
                myTicketId = data.id;
                localStorage.setItem('php_queue_id', myTicketId);
                showTicketView();
                startPolling();
            }
        }

        function showTicketView() {
            document.getElementById('join-form').classList.add('hidden');
            document.getElementById('ticket-status').classList.remove('hidden');
        }

        async function updateLiveStatus() {
            if(!myTicketId) return;

            // Fetch data from client API (which now includes onBreak data)
            const res = await fetch(`client.php?action=ticket_status&id=${myTicketId}`);
            const data = await res.json();
            
            if(data.error) {
                localStorage.removeItem('php_queue_id');
                location.reload();
                return;
            }

            // --- INTEGRATED: Client Lunch Break Notice Interceptor ---
            const breakNoticeId = 'client-break-overlay';
            let breakOverlay = document.getElementById(breakNoticeId);

            if (data.onBreak) {
                // Shut down active sound chimes instantly if a break starts while ringing
                if (alarmInterval) {
                    clearInterval(alarmInterval);
                    alarmInterval = null;
                }
                
                // Add a full screen lock overlay to inform the client
                if (!breakOverlay) {
                    breakOverlay = document.createElement('div');
                    breakOverlay.id = breakNoticeId;
                    breakOverlay.className = "fixed inset-0 bg-slate-900/95 backdrop-blur-md z-50 flex flex-col items-center justify-center p-6 text-center animate-fade-in";
                    breakOverlay.innerHTML = `
                        <div class="bg-slate-800 border border-amber-500/30 p-8 rounded-3xl max-w-sm shadow-2xl text-white">
                            <div class="text-5xl mb-4 animate-bounce">☕</div>
                            <h2 class="text-xl font-black text-amber-400 uppercase tracking-wide">Counter on Break</h2>
                            <p class="text-slate-300 text-sm mt-3 leading-relaxed">
                                Our staff are currently away on a lunch break. Please keep hold of your number! Service will pick up right where it left off shortly.
                            </p>
                        </div>`;
                    document.body.appendChild(breakOverlay);
                }
                return; // Stop rendering any numbers while break is active
            } else {
                // Remove screen lock layout once the break has finished
                if (breakOverlay) {
                    breakOverlay.remove();
                }
            }
            
            // --- Normal Queue Operation Continues Here Safely ---
            const formattedMyNum = String(data.myTicket.ticket_number).padStart(3, '0');
            document.getElementById('my-number').innerText = formattedMyNum;
            
            if (data.currentServing && data.currentServing !== 'None') {
                const formattedServing = String(data.currentServing).padStart(3, '0');
                document.getElementById('live-serving').innerText = formattedServing;
            } else {
                document.getElementById('live-serving').innerText = '---';
            }
            
            document.getElementById('live-ahead').innerText = data.aheadCount;
            
            if(data.myTicket.status === 'calling') {
                document.getElementById('alert-number').innerText = formattedMyNum;
                document.getElementById('alert-screen').classList.remove('hidden');
                
                if (!alarmInterval) {
                    playPhoneNotification(false);
                    alarmInterval = setInterval(() => {
                        playPhoneNotification(false);
                    }, 2200);
                }
            } else if(data.myTicket.status === 'completed') {
                if (alarmInterval) {
                    clearInterval(alarmInterval);
                    alarmInterval = null;
                }
                document.getElementById('alert-screen').classList.add('hidden');
                document.getElementById('my-number').className = "text-7xl font-black text-gray-300 my-4 line-through font-mono";
            }
        }

        function playPhoneNotification(silent = false) {
            const context = new (window.AudioContext || window.webkitAudioContext)();
            
            if (silent) {
                const osc = context.createOscillator();
                const gain = context.createGain();
                osc.connect(gain); gain.connect(context.destination);
                gain.gain.setValueAtTime(0.001, context.currentTime);
                osc.start(); osc.stop(context.currentTime + 0.1);
                return;
            }

            const now = context.currentTime;
            
            const osc1 = context.createOscillator();
            const gain1 = context.createGain();
            osc1.type = 'sine';
            osc1.frequency.setValueAtTime(659.25, now);
            
            osc1.connect(gain1);
            gain1.connect(context.destination);
            
            gain1.gain.setValueAtTime(0.4, now);
            gain1.gain.exponentialRampToValueAtTime(0.001, now + 0.6);
            
            osc1.start(now);
            osc1.stop(now + 0.6);

            const delay = 0.15;
            const osc2 = context.createOscillator();
            const gain2 = context.createGain();
            osc2.type = 'sine';
            osc2.frequency.setValueAtTime(783.99, now + delay);
            
            osc2.connect(gain2);
            gain2.connect(context.destination);
            
            gain2.gain.setValueAtTime(0.001, now + delay);
            gain2.gain.linearRampToValueAtTime(0.5, now + delay + 0.05); 
            gain2.gain.exponentialRampToValueAtTime(0.001, now + delay + 0.8);
            
            osc2.start(now + delay);
            osc2.stop(now + delay + 0.8);
        }

        function startPolling() {
            updateLiveStatus();
            setInterval(updateLiveStatus, 2500);
        }
    </script>
</body>
</html>