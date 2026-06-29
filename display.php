<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lobby TV Monitor</title>
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
</head>
<body class="bg-gray-100 text-gray-800 min-h-screen flex flex-col font-sans overflow-hidden p-8 select-none">

    <div class="flex-1 grid grid-cols-1 xl:grid-cols-3 gap-8 w-full h-full my-auto">
        <div id="tv-main-card" class="xl:col-span-2 bg-white border border-gray-200 rounded-[2.5rem] flex flex-col justify-center items-center p-12 text-center shadow-sm relative transition-all duration-300">
            <span id="tv-label" class="text-blue-600 text-3xl font-black tracking-widest uppercase mb-4">NOW SERVING</span>
            
            <div id="tv-num" class="text-[14rem] sm:text-[18rem] font-black tracking-tighter text-blue-600 leading-none drop-shadow-sm transition-all duration-500">
                --
            </div>
            
            <div id="tv-subtitle" class="text-3xl text-gray-400 font-bold mt-8 uppercase tracking-wide">
                Please proceed to the Records counter
            </div>
        </div>

        <div class="xl:col-span-1 bg-white border border-gray-200 rounded-[2.5rem] p-8 flex flex-col shadow-sm">
            <h2 class="text-gray-500 text-xl font-bold tracking-widest uppercase mb-6 border-b border-gray-100 pb-4 text-center">
                NEXT IN LINE
            </h2>
            
            <div id="tv-waitlist" class="space-y-4 flex-1 flex flex-col justify-start"></div>
            
            <div class="text-center text-xs font-bold text-gray-400 mt-4 uppercase tracking-widest">
                Scan placard QR to get a ticket
            </div>
        </div>
    </div>

    <script>
        let lastId = null;

        async function updateTV() {
            try {
                const res = await fetch('admin.php?action=status');
                const data = await res.json();

                const mainCard = document.getElementById('tv-main-card');
                const label = document.getElementById('tv-label');
                const numContainer = document.getElementById('tv-num');
                const subtitle = document.getElementById('tv-subtitle');

                // --- INTEGRATED: Global Lunch Break Screen Adjuster ---
                if (data.onBreak) {
                    label.innerText = "COUNTER STATUS";
                    label.className = "text-amber-600 text-3xl font-black tracking-widest uppercase mb-4";
                    
                    numContainer.innerText = "BREAK";
                    numContainer.className = "text-[11rem] sm:text-[14rem] font-black tracking-widest text-amber-500 leading-none drop-shadow-sm animate-pulse font-mono";
                    
                    subtitle.innerText = "Staff are away on a lunch break. Service will resume shortly.";
                    subtitle.className = "text-3xl text-amber-700/70 font-bold mt-8 uppercase tracking-wide";
                    
                    mainCard.className = "xl:col-span-2 bg-amber-50/50 border border-amber-200 rounded-[2.5rem] flex flex-col justify-center items-center p-12 text-center shadow-inner relative transition-all duration-300";
                } else {
                    // Restore original class layouts when break is toggled off
                    label.innerText = "NOW SERVING";
                    label.className = "text-blue-600 text-3xl font-black tracking-widest uppercase mb-4";
                    
                    subtitle.className = "text-3xl text-gray-400 font-bold mt-8 uppercase tracking-wide";
                    subtitle.innerText = "Please proceed to the Records counter";
                    
                    mainCard.className = "xl:col-span-2 bg-white border border-gray-200 rounded-[2.5rem] flex flex-col justify-center items-center p-12 text-center shadow-sm relative transition-all duration-300";

                    // Handle regular queue processing parameters safely
                    if (data.currentServing) {
                        const formattedNum = String(data.currentServing.ticket_number).padStart(3, '0');
                        numContainer.innerText = formattedNum;
                        numContainer.className = "text-[14rem] sm:text-[18rem] font-black tracking-tighter text-blue-600 leading-none drop-shadow-sm transition-all duration-500";
                        
                        if (data.currentServing.id !== lastId) {
                            lastId = data.currentServing.id;
                            numContainer.classList.add('scale-110', 'text-green-600');
                            playLobbyChime();
                            setTimeout(() => {
                                numContainer.classList.remove('scale-110', 'text-green-600');
                            }, 1200);
                        }
                    } else {
                        numContainer.innerText = '--';
                        numContainer.className = "text-[14rem] sm:text-[18rem] font-black tracking-tighter text-blue-600 leading-none drop-shadow-sm transition-all duration-500";
                    }
                }

                // Render the side waitlist (kept populated so users see their placement order)
                const list = document.getElementById('tv-waitlist');
                list.innerHTML = '';

                if (data.waitingList.length === 0) {
                    list.innerHTML = `<div class="text-gray-300 text-xl italic text-center my-auto">No pending tickets</div>`;
                } else {
                    data.waitingList.slice(0, 4).forEach(ticket => {
                        const paddedListItem = String(ticket.ticket_number).padStart(3, '0');
                        list.innerHTML += `
                            <div class="flex justify-center items-center bg-gray-50 border border-gray-100 p-5 rounded-2xl">
                                <span class="text-5xl font-black text-gray-700 font-mono">${paddedListItem}</span>
                            </div>`;
                    });
                }
            } catch(e) { console.error(e); }
        }

        function playLobbyChime() {
            const audioCtx = new (window.AudioContext || window.webkitAudioContext)();
            const osc = audioCtx.createOscillator();
            const gainNode = audioCtx.createGain();
            osc.connect(gainNode); gainNode.connect(audioCtx.destination);
            osc.type = 'sine';
            osc.frequency.setValueAtTime(523.25, audioCtx.currentTime); 
            osc.frequency.setValueAtTime(659.25, audioCtx.currentTime + 0.15); 
            gainNode.gain.setValueAtTime(0.25, audioCtx.currentTime);
            gainNode.gain.exponentialRampToValueAtTime(0.01, audioCtx.currentTime + 0.6);
            osc.start(); osc.stop(audioCtx.currentTime + 0.6);
        }

        updateTV();
        setInterval(updateTV, 2000);
    </script>
</body>
</html>