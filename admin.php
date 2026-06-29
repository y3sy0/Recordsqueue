<?php
require 'db.php';

$breakFile = 'break_status.txt';
$isOnBreak = file_exists($breakFile) ? file_get_contents($breakFile) : 'no';

// Action API endpoints
if (isset($_GET['action'])) {
    header('Content-Type: application/json');

    if ($_GET['action'] == 'status') {
        $stmt = $pdo->query("SELECT * FROM tickets WHERE status = 'calling' AND DATE(created_at) = CURRENT_DATE ORDER BY id DESC LIMIT 1");
        $current = $stmt->fetch();

        $stmt = $pdo->query("SELECT * FROM tickets WHERE status = 'waiting' AND DATE(created_at) = CURRENT_DATE ORDER BY ticket_number ASC LIMIT 5");
        $waiting = $stmt->fetchAll();

        echo json_encode([
            'currentServing' => $current, 
            'waitingList' => $waiting,
            'onBreak' => ($isOnBreak === 'yes')
        ]);
        exit;
    }

    if ($_GET['action'] == 'next') {
        if (file_exists($breakFile)) { file_put_contents($breakFile, 'no'); }
        
        $pdo->query("UPDATE tickets SET status = 'completed' WHERE status = 'calling' AND DATE(created_at) = CURRENT_DATE");

        $stmt = $pdo->query("SELECT id FROM tickets WHERE status = 'waiting' AND DATE(created_at) = CURRENT_DATE ORDER BY ticket_number ASC LIMIT 1");
        $next = $stmt->fetch();

        if ($next) {
            $stmt = $pdo->prepare("UPDATE tickets SET status = 'calling' WHERE id = ?");
            $stmt->execute([$next['id']]);
        }
        echo json_encode(['success' => true]);
        exit;
    }

    if ($_GET['action'] == 'done') {
        $pdo->query("UPDATE tickets SET status = 'completed' WHERE status = 'calling' AND DATE(created_at) = CURRENT_DATE");
        echo json_encode(['success' => true]);
        exit;
    }

    if ($_GET['action'] == 'toggle_break') {
        $currentStatus = file_exists($breakFile) ? file_get_contents($breakFile) : 'no';
        $newStatus = ($currentStatus === 'yes') ? 'no' : 'yes';
        file_put_contents($breakFile, $newStatus);
        echo json_encode(['success' => true, 'onBreak' => ($newStatus === 'yes')]);
        exit;
    }

    if ($_GET['action'] == 'reset') {
        file_put_contents($breakFile, 'no');
        $pdo->query("TRUNCATE TABLE tickets");
        echo json_encode(['success' => true]);
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Queue Console (Admin)</title>
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
</head>
<body class="bg-slate-900 text-white min-h-screen p-6 font-sans">

    <div class="max-w-4xl mx-auto">
        <header class="flex justify-between items-center mb-10 border-b border-slate-800 pb-5">
            <h1 class="text-2xl font-black text-blue-400 tracking-wide">QRQ ADMIN CONSOLE</h1>
            <button onclick="resetQueue()" class="bg-red-900/40 hover:bg-red-800 text-red-300 px-4 py-2 rounded-lg text-sm transition">Clear Database</button>
        </header>

        <div class="grid md:grid-cols-3 gap-6">
            <div class="bg-slate-800 rounded-2xl p-6 flex flex-col justify-between border border-slate-700 min-h-[340px]">
                <div>
                    <h2 class="text-slate-400 font-bold text-xs uppercase tracking-wider mb-2">Controls</h2>
                    <p class="text-sm text-slate-400">Manage active queue step progressions.</p>
                </div>
                
                <div class="space-y-3 w-full">
                    <button id="break-btn" onclick="toggleBreak()" class="w-full bg-amber-700 hover:bg-amber-600 text-white font-extrabold py-3 rounded-xl text-md shadow-md transition active:scale-95">
                        GO ON BREAK
                    </button>
                    <button onclick="markDone()" class="w-full bg-green-700 hover:bg-green-600 text-white font-extrabold py-3 rounded-xl text-md shadow-md transition active:scale-95">
                        MARK DONE
                    </button>
                    <button onclick="callNext()" class="w-full bg-blue-600 hover:bg-blue-500 text-white font-extrabold py-4 rounded-xl text-lg shadow-lg transition active:scale-95">
                        CALL NEXT
                    </button>
                </div>
            </div>

            <div class="md:col-span-2 bg-slate-800 rounded-2xl p-6 border border-slate-700">
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-slate-400 font-bold text-xs uppercase tracking-wider">Current Status</h2>
                    <span id="break-badge" class="hidden bg-amber-500 text-slate-950 text-xs font-black px-2.5 py-1 rounded-full uppercase tracking-wider">On Break</span>
                </div>
                
                <div class="bg-slate-950 rounded-xl p-6 mb-6 flex justify-between items-center border border-slate-800">
                    <div>
                        <span class="text-xs font-bold text-green-400 block uppercase tracking-widest mb-1">Now Serving</span>
                        <div id="current-name" class="text-xl font-bold text-slate-300">No one being served</div>
                    </div>
                    <div id="current-number" class="text-5xl font-black text-green-400">--</div>
                </div>

                <h3 class="text-xs font-bold text-slate-400 uppercase tracking-widest mb-3">Up Next In Line</h3>
                <div id="waiting-list" class="space-y-2"></div>
            </div>
        </div>
    </div>

    <script>
        async function fetchAdminStatus() {
            const res = await fetch('admin.php?action=status');
            const data = await res.json();

            // Update break visuals in admin
            const breakBtn = document.getElementById('break-btn');
            const breakBadge = document.getElementById('break-badge');
            if (data.onBreak) {
                breakBtn.innerText = "⚡ RESUME SERVICE";
                breakBtn.className = "w-full bg-slate-600 hover:bg-slate-500 text-white font-extrabold py-3 rounded-xl text-md shadow-md transition active:scale-95";
                breakBadge.classList.remove('hidden');
            } else {
                breakBtn.innerText = "☕ GO ON BREAK";
                breakBtn.className = "w-full bg-amber-700 hover:bg-amber-600 text-white font-extrabold py-3 rounded-xl text-md shadow-md transition active:scale-95";
                breakBadge.classList.add('hidden');
            }

            if (data.currentServing) {
                document.getElementById('current-number').innerText = `#${String(data.currentServing.ticket_number).padStart(3, '0')}`;
                document.getElementById('current-name').innerText = data.currentServing.client_name;
            } else {
                document.getElementById('current-number').innerText = '--';
                document.getElementById('current-name').innerText = data.currentServing ? 'No one being served' : (data.onBreak ? 'Counter on Break' : 'No one being served');
            }

            const listContainer = document.getElementById('waiting-list');
            listContainer.innerHTML = '';

            if(data.waitingList.length === 0) {
                listContainer.innerHTML = `<div class="text-sm text-slate-500 italic p-3 bg-slate-950 rounded-lg">Line is empty.</div>`;
            } else {
                data.waitingList.forEach(ticket => {
                    listContainer.innerHTML += `
                        <div class="flex justify-between items-center bg-slate-950 p-3 rounded-lg text-sm border border-slate-900">
                            <span class="font-medium text-slate-300">${ticket.client_name}</span>
                            <span class="font-mono font-bold text-blue-400">#${String(ticket.ticket_number).padStart(3, '0')}</span>
                        </div>`;
                });
            }
        }

        async function callNext() {
            await fetch('admin.php?action=next');
            fetchAdminStatus();
        }

        async function markDone() {
            await fetch('admin.php?action=done');
            fetchAdminStatus();
        }

        async function toggleBreak() {
            await fetch('admin.php?action=toggle_break');
            fetchAdminStatus();
        }

        async function resetQueue() {
            if(confirm("Truncate table data and clear queue numbers?")) {
                await fetch('admin.php?action=reset');
                fetchAdminStatus();
            }
        }

        fetchAdminStatus();
        setInterval(fetchAdminStatus, 3000);
    </script>
</body>
</html>