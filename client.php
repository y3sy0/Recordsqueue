<?php
require 'db.php';

if (isset($_GET['action']) && $_GET['action'] == 'save_subscription') {
    header('Content-Type: application/json');
    $data = json_decode(file_get_contents('php://input'), true);
    $ticketId = $data['id'] ?? 0;
    $subscriptionJson = json_encode($data['subscription'] ?? []);

    if ($ticketId && $subscriptionJson) {
        $stmt = $pdo->prepare("UPDATE tickets SET push_subscription = ? WHERE id = ?");
        $stmt->execute([$subscriptionJson, $ticketId]);
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Missing data']);
    }
    exit;
}

if (isset($_GET['action']) && $_GET['action'] == 'join') {
    header('Content-Type: application/json');
    $type = $_GET['type'] ?? 'receiving'; 
    
    $stmt = $pdo->prepare("SELECT MAX(ticket_number) AS max_num FROM tickets WHERE transaction_type = ? AND DATE(created_at) = CURRENT_DATE");
    $stmt->execute([$type]);
    $row = $stmt->fetch();
    $next_num = ($row['max_num']) ? $row['max_num'] + 1 : 1;

    $stmt = $pdo->prepare("INSERT INTO tickets (ticket_number, transaction_type, client_name, status) VALUES (?, ?, 'Visitor', 'waiting')");
    $stmt->execute([$next_num, $type]);
    $lastId = $pdo->lastInsertId();

    echo json_encode(['id' => $lastId, 'ticket_number' => $next_num, 'type' => $type]);
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

    $stmt = $pdo->prepare("SELECT COUNT(*) AS ahead FROM tickets WHERE status = 'waiting' AND transaction_type = ? AND ticket_number < ? AND DATE(created_at) = CURRENT_DATE");
    $stmt->execute([$myTicket['transaction_type'], $myTicket['ticket_number']]);
    $aheadCount = $stmt->fetch()['ahead'];

    $stmt = $pdo->prepare("SELECT ticket_number, transaction_type FROM tickets WHERE status = 'calling' AND DATE(created_at) = CURRENT_DATE ORDER BY id DESC LIMIT 1");
    $stmt->execute();
    $servingRow = $stmt->fetch();
    
    $currentServing = 'None';
    if ($servingRow) {
        $prefix = ($servingRow['transaction_type'] == 'receiving') ? 'REC-' : 'REL-';
        $currentServing = $prefix . str_pad($servingRow['ticket_number'], 3, '0', STR_PAD_LEFT);
    }

    $onBreak = (file_exists('break_status.txt') && file_get_contents('break_status.txt') === 'yes');

    echo json_encode([
        'myTicket' => $myTicket,
        'aheadCount' => $aheadCount,
        'currentServing' => $currentServing,
        'onBreak' => $onBreak
    ]);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Priority Queue Voucher</title>
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
</head>
<body class="bg-gray-100 flex items-center justify-center min-h-screen p-4 font-sans selection:bg-none">

    <div id="alert-screen" class="hidden fixed inset-0 z-50 flex flex-col items-center justify-center p-6 text-center text-white animate-pulse">
        <div class="text-6xl mb-4">🔔</div>
        <h1 class="text-4xl font-black tracking-tight mb-2">IT'S YOUR TURN!</h1>
        <p id="alert-counter-msg" class="text-lg font-bold opacity-95 mb-6 uppercase tracking-wide">Please proceed immediately.</p>
        <div id="alert-number" class="text-6xl font-black bg-white px-8 py-4 rounded-2xl shadow-2xl font-mono tracking-tight">---</div>
    </div>

    <div class="w-full max-w-sm bg-white rounded-[2rem] shadow-xl p-6 text-center border border-gray-200/60 relative overflow-hidden flex flex-col justify-between min-h-[460px]">
        
        <div id="join-form" class="my-auto">
            <div class="w-12 h-12 bg-blue-50 text-blue-600 rounded-2xl flex items-center justify-center mx-auto mb-4 font-bold text-xl shadow-sm border border-blue-100">🏷️</div>
            <h1 class="text-2xl font-black text-gray-800 tracking-tight mb-1">Records Queue</h1>
            <p class="text-sm text-gray-400 mb-8 px-4">Select your required transaction path below to take a virtual live position token.</p>
            
            <div class="space-y-3.5">
                <button onclick="joinQueue('receiving')" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold text-base py-4 px-6 rounded-xl shadow-md transition-all active:scale-95 tracking-wide uppercase cursor-pointer">
                    Receiving Counter
                </button>
                <button onclick="joinQueue('releasing')" class="w-full bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-base py-4 px-6 rounded-xl shadow-md transition-all active:scale-95 tracking-wide uppercase cursor-pointer">
                    Releasing Counter
                </button>
            </div>
        </div>

        <div id="ticket-status" class="hidden my-auto flex flex-col justify-between h-full">
            <div>
                <span id="ticket-counter-heading" class="bg-gray-50 text-gray-400 border border-gray-200/60 px-4 py-1.5 rounded-full text-xs font-black tracking-widest uppercase inline-block mb-2">
                    Your Token
                </span>
                <div id="my-number" class="text-5xl font-black font-mono tracking-tighter my-4 drop-shadow-sm">---</div>
            </div>
            
            <div class="grid grid-cols-2 gap-3 border-t border-gray-100 pt-6 mt-4">
                <div class="bg-blue-50/60 border border-blue-100/50 p-4 rounded-2xl">
                    <span class="block text-[10px] text-blue-500 uppercase tracking-widest font-bold mb-1">Now Serving</span>
                    <span id="live-serving" class="text-xl font-black text-blue-900 font-mono">---</span>
                </div>
                <div class="bg-amber-50/60 border border-amber-100/50 p-4 rounded-2xl">
                    <span class="block text-[10px] text-amber-600 uppercase tracking-widest font-bold mb-1">People Ahead</span>
                    <span id="live-ahead" class="text-xl font-black text-amber-900 font-mono">---</span>
                </div>
            </div>
            
            <div class="mt-6 pt-2">
                <p class="text-[11px] text-gray-400 leading-relaxed bg-gray-50 py-2.5 px-4 border border-gray-100 rounded-xl">
                    Please keep this browser active. Your device will buzz and chime continuously when your number is called.
                </p>
            </div>
        </div>
    </div>

    <script>
        let myTicketId = localStorage.getItem('php_queue_id');
        let alarmInterval = null;

        if (myTicketId) {
            showTicketView();
            startPolling();
            setupPushNotifications(myTicketId);
        }

        async function joinQueue(type) {
            try { playPhoneNotification(true); } catch(e){}

            const res = await fetch(`client.php?action=join&type=${type}`, { method: 'POST' });
            const data = await res.json();
            
            if(data.id) {
                myTicketId = data.id;
                localStorage.setItem('php_queue_id', myTicketId);
                showTicketView();
                startPolling();
                setupPushNotifications(myTicketId);
            }
        }

        function showTicketView() {
            document.getElementById('join-form').classList.add('hidden');
            document.getElementById('ticket-status').classList.remove('hidden');
        }

        async function updateLiveStatus() {
            if(!myTicketId) return;

            const res = await fetch(`client.php?action=ticket_status&id=${myTicketId}`);
            const data = await res.json();
            
            if(data.error) {
                localStorage.removeItem('php_queue_id');
                location.reload();
                return;
            }

            // --- Break Handling Notice ---
            const breakNoticeId = 'client-break-overlay';
            let breakOverlay = document.getElementById(breakNoticeId);

            if (data.onBreak) {
                if (alarmInterval) { clearInterval(alarmInterval); alarmInterval = null; }
                if (!breakOverlay) {
                    breakOverlay = document.createElement('div');
                    breakOverlay.id = breakNoticeId;
                    breakOverlay.className = "fixed inset-0 bg-slate-900/95 backdrop-blur-sm z-50 flex flex-col items-center justify-center p-6 text-center animate-fade-in";
                    breakOverlay.innerHTML = `
                        <div class="bg-white border border-amber-200 p-8 rounded-3xl max-w-xs shadow-2xl text-center">
                            <div class="text-5xl mb-4 animate-bounce">☕</div>
                            <h2 class="text-xl font-black text-amber-500 uppercase tracking-wide">Counters on Break</h2>
                            <p class="text-gray-500 text-sm mt-3 leading-relaxed">Our personnel are currently away on a short scheduled lunch break. Please retain your screen token status.</p>
                        </div>`;
                    document.body.appendChild(breakOverlay);
                }
                return; 
            } else if (breakOverlay) {
                breakOverlay.remove();
            }
            
            const prefix = (data.myTicket.transaction_type === 'receiving') ? 'REC-' : 'REL-';
            const counterName = (data.myTicket.transaction_type === 'receiving') ? 'Receiving Counter' : 'Releasing Counter';
            const formattedMyNum = prefix + String(data.myTicket.ticket_number).padStart(3, '0');
            
            document.getElementById('my-number').innerText = formattedMyNum;
            document.getElementById('ticket-counter-heading').innerText = `Your Token (${prefix.replace('-','')})`;
            
            if (data.myTicket.transaction_type === 'releasing') {
                document.getElementById('my-number').className = "text-5xl font-black text-emerald-600 my-4 font-mono tracking-tight";
            } else {
                document.getElementById('my-number').className = "text-5xl font-black text-blue-600 my-4 font-mono tracking-tight";
            }

            document.getElementById('live-serving').innerText = data.currentServing;
            document.getElementById('live-ahead').innerText = data.aheadCount;
            
            if(data.myTicket.status === 'calling') {
                document.getElementById('alert-number').innerText = formattedMyNum;
                document.getElementById('alert-counter-msg').innerText = `Please proceed to the ${counterName} immediately.`;
                document.getElementById('alert-screen').classList.remove('hidden');
                
                if (data.myTicket.transaction_type === 'releasing') {
                    document.getElementById('alert-screen').className = "fixed inset-0 bg-emerald-600 z-50 flex flex-col items-center justify-center p-6 text-center text-white";
                    document.getElementById('alert-number').className = "text-6xl font-black bg-white text-emerald-700 px-8 py-4 rounded-2xl shadow-2xl font-mono tracking-tight";
                } else {
                    document.getElementById('alert-screen').className = "fixed inset-0 bg-blue-600 z-50 flex flex-col items-center justify-center p-6 text-center text-white";
                    document.getElementById('alert-number').className = "text-6xl font-black bg-white text-blue-700 px-8 py-4 rounded-2xl shadow-2xl font-mono tracking-tight";
                }

                if (!alarmInterval) {
                    playPhoneNotification(false);
                    alarmInterval = setInterval(() => { playPhoneNotification(false); }, 2200);
                }
            } else {
                if (alarmInterval) { 
                    clearInterval(alarmInterval); 
                    alarmInterval = null; 
                }
                document.getElementById('alert-screen').classList.add('hidden');

                if (data.myTicket.status === 'completed' || data.myTicket.status === 'done') {
                    myTicketId = null; 
                    if (window.pollingInterval) {
                        clearInterval(window.pollingInterval);
                        window.pollingInterval = null;
                    }
                    
                    const container = document.getElementById('ticket-status');
                    container.className = "my-auto flex flex-col justify-between h-full items-center animate-fade-in";
                    container.innerHTML = `
                        <div class="py-4">
                            <div class="text-5xl mb-4 animate-bounce">✨</div>
                            <span class="bg-gray-100 text-gray-500 border border-gray-200 px-4 py-1.5 rounded-full text-xs font-black tracking-widest uppercase inline-block mb-3">
                                Session Finished
                            </span>
                            <h2 class="text-2xl font-black text-gray-800 tracking-tight">Thank you!</h2>
                            <p class="text-sm text-gray-400 mt-2 px-2 leading-relaxed">
                                Your transaction has been successfully processed at the counter. We value your time.
                            </p>
                        </div>
                        
                        <div class="w-full mt-6 border-t border-gray-100 pt-6">
                            <button onclick="resetToNewTransaction()" class="w-full bg-gray-900 hover:bg-black text-white font-bold text-base py-3.5 px-6 rounded-xl shadow-md transition-all cursor-pointer active:scale-95 tracking-wide uppercase">
                                New Transaction
                            </button>
                        </div>
                    `;
                } else if (data.myTicket.status === 'waiting') {
                    if (data.myTicket.transaction_type === 'releasing') {
                        document.getElementById('my-number').className = "text-5xl font-black text-emerald-600 my-4 font-mono tracking-tight";
                    } else {
                        document.getElementById('my-number').className = "text-5xl font-black text-blue-600 my-4 font-mono tracking-tight";
                    }
                }
            }
        }

        async function setupPushNotifications(ticketId) {
            if (!('serviceWorker' in navigator) || !('PushManager' in window)) return;
            try {
                const registration = await navigator.serviceWorker.register('sw.js');
                const permission = await Notification.requestPermission();
                if (permission !== 'granted') return;

                let subscription = await registration.pushManager.getSubscription();
                if (!subscription) {
                    subscription = await registration.pushManager.subscribe({
                        userVisibleOnly: true,
                        applicationServerKey: urlBase64ToUint8Array('BEl62OhAdg1t9vJFujmQsW5A0dBvjQQclgWDf97X9xH7wR0kO2v0w8vE5z_zQp_g9H8vE5z_zQp_g9H8vE5z_w')
                    });
                }

                await fetch('client.php?action=save_subscription', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id: ticketId, subscription: subscription })
                });
            } catch (error) { console.error(error); }
        }

        function urlBase64ToUint8Array(base64String) {
            const padding = '='.repeat((4 - base64String.length % 4) % 4);
            const base64 = (base64String + padding).replace(/\-/g, '+').replace(/_/g, '/');
            const rawData = window.atob(base64);
            const outputArray = new Uint8Array(rawData.length);
            for (let i = 0; i < rawData.length; ++i) { outputArray[i] = rawData.charCodeAt(i); }
            return outputArray;
        }

        function playPhoneNotification(silent = false) {
            const context = new (window.AudioContext || window.webkitAudioContext)();
            if (silent) {
                const osc = context.createOscillator(); const gain = context.createGain();
                osc.connect(gain); gain.connect(context.destination);
                gain.gain.setValueAtTime(0.001, context.currentTime);
                osc.start(); osc.stop(context.currentTime + 0.1); return;
            }
            const now = context.currentTime;
            const osc1 = context.createOscillator(); const gain1 = context.createGain();
            osc1.type = 'sine'; osc1.frequency.setValueAtTime(659.25, now);
            osc1.connect(gain1); gain1.connect(context.destination);
            gain1.gain.setValueAtTime(0.4, now); gain1.gain.exponentialRampToValueAtTime(0.001, now + 0.6);
            osc1.start(now); osc1.stop(now + 0.6);
        }

        function startPolling() { 
            updateLiveStatus(); 
            window.pollingInterval = setInterval(updateLiveStatus, 2500); 
        }

        function resetToNewTransaction() {
            localStorage.removeItem('php_queue_id');
            window.location.replace(window.location.pathname);
        }
    </script>
</body>
</html>