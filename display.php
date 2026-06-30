<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Queue Panel Display</title>
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
</head>
<body class="bg-gray-100 text-gray-800 min-h-screen flex flex-col font-sans overflow-hidden select-none">

    <div id="tv-break-overlay" class="hidden fixed inset-0 bg-amber-500/95 backdrop-blur-md z-50 flex flex-col items-center justify-center p-12 text-center text-white">
        <div class="text-9xl mb-6 animate-bounce">☕</div>
        <h1 class="text-6xl font-black uppercase tracking-widest">System on Break</h1>
        <p class="text-2xl text-amber-50 font-bold mt-6 uppercase tracking-wide max-w-2xl leading-relaxed">
            Staff members are currently away on lunch break. Service counters will resume shortly.
        </p>
    </div>

    <div class="flex-1 grid grid-cols-1 lg:grid-cols-2 gap-8 p-8 w-full h-full items-stretch">
        
        <div id="panel-receiving" class="bg-white border-4 border-blue-200 rounded-[3rem] flex flex-col justify-between p-12 text-center shadow-md relative transition-all duration-300">
            <div class="absolute inset-0 bg-blue-500/5 rounded-[2.8rem] pointer-events-none"></div>
            <div>
                <span class="bg-blue-50 text-blue-600 border border-blue-200 px-8 py-3 rounded-full text-2xl font-black tracking-widest uppercase inline-block">
                    STATION 1: RECEIVING
                </span>
            </div>
            
            <div class="my-auto py-6 flex flex-col items-center justify-center overflow-hidden">
                <div id="rec-num" class="text-[9vw] font-black tracking-tighter text-blue-600 leading-none font-mono drop-shadow-[0_4px_12px_rgba(37,99,235,0.15)] transition-all duration-500 whitespace-nowrap w-full">
                    --
                </div>
                <div id="rec-status-text" class="text-3xl text-gray-400 font-bold mt-4 uppercase tracking-wider">
                    Waiting for next token
                </div>
            </div>

            <div class="border-t border-gray-100 pt-6">
                <span class="text-xs font-bold text-gray-400 uppercase tracking-widest block mb-2">Up Next In Line</span>
                <div id="rec-next" class="text-2xl font-bold font-mono text-gray-600 bg-gray-50 py-2.5 rounded-xl border border-gray-100">None pending</div>
            </div>
        </div>

        <div id="panel-releasing" class="bg-white border-4 border-emerald-200 rounded-[3rem] flex flex-col justify-between p-12 text-center shadow-md relative transition-all duration-300">
            <div class="absolute inset-0 bg-emerald-500/5 rounded-[2.8rem] pointer-events-none"></div>
            <div>
                <span class="bg-emerald-50 text-emerald-600 border border-emerald-200 px-8 py-3 rounded-full text-2xl font-black tracking-widest uppercase inline-block">
                    STATION 2: RELEASING
                </span>
            </div>
            
            <div class="my-auto py-6 flex flex-col items-center justify-center overflow-hidden">
                <div id="rel-num" class="text-[9vw] font-black tracking-tighter text-emerald-600 leading-none font-mono drop-shadow-[0_4px_12px_rgba(5,150,105,0.15)] transition-all duration-500 whitespace-nowrap w-full">
                    --
                </div>
                <div id="rel-status-text" class="text-3xl text-gray-400 font-bold mt-4 uppercase tracking-wider">
                    Waiting for next token
                </div>
            </div>

            <div class="border-t border-gray-100 pt-6">
                <span class="text-xs font-bold text-gray-400 uppercase tracking-widest block mb-2">Up Next In Line</span>
                <div id="rel-next" class="text-2xl font-bold font-mono text-gray-600 bg-gray-50 py-2.5 rounded-xl border border-gray-100">None pending</div>
            </div>
        </div>

    </div>

    <script>
        let lastRecNum = null;
        let lastRelNum = null;

        async function updateTV() {
            try {
                // Fetch current system settings and real-time status arrays from admin api
                const res = await fetch('admin.php?action=status');
                if (!res.ok) return;
                const data = await res.json();

                // 1. Handle Global Lunch Break State
                const breakOverlay = document.getElementById('tv-break-overlay');
                if (data.onBreak) {
                    breakOverlay.classList.remove('hidden');
                    return;
                } else {
                    breakOverlay.classList.add('hidden');
                }

                // 2. Map structural API tokens exactly to elements
                const recActive = data.recActive; // matches admin.php endpoint array mapping
                const relActive = data.relActive; 

                // --- PROCESS RECEIVING COUNTER DISPLAY ---
                const recNumContainer = document.getElementById('rec-num');
                const recStatusText = document.getElementById('rec-status-text');
                const recPanel = document.getElementById('panel-receiving');

                if (recActive) {
                    const formattedRec = 'REC-' + String(recActive).padStart(3, '0');
                    recNumContainer.innerText = formattedRec;
                    recStatusText.innerText = "Proceed to Records Receiving Counter";
                    recStatusText.classList.replace('text-gray-400', 'text-blue-500');
                    
                    if (recActive !== lastRecNum) {
                        lastRecNum = recActive;
                        triggerChimeEffect(recNumContainer, recPanel, 'border-blue-500', 'bg-blue-50/50');
                    }
                } else {
                    recNumContainer.innerText = '--';
                    recStatusText.innerText = "Waiting for next token";
                    recStatusText.classList.replace('text-blue-500', 'text-gray-400');
                    lastRecNum = null;
                }

                // --- PROCESS RELEASING COUNTER DISPLAY ---
                const relNumContainer = document.getElementById('rel-num');
                const relStatusText = document.getElementById('rel-status-text');
                const relPanel = document.getElementById('panel-releasing');

                if (relActive) {
                    const formattedRel = 'REL-' + String(relActive).padStart(3, '0');
                    relNumContainer.innerText = formattedRel;
                    relStatusText.innerText = "Proceed to Records Releasing Counter";
                    relStatusText.classList.replace('text-gray-400', 'text-emerald-500');
                    
                    if (relActive !== lastRelNum) {
                        lastRelNum = relActive;
                        triggerChimeEffect(relNumContainer, relPanel, 'border-emerald-500', 'bg-emerald-50/50');
                    }
                } else {
                    relNumContainer.innerText = '--';
                    relStatusText.innerText = "Waiting for next token";
                    relStatusText.classList.replace('text-emerald-500', 'text-gray-400');
                    lastRelNum = null;
                }

                // 3. To pull the dynamic custom "Up Next" text trails, fetch a clean HTML snippet
                const pageFetch = await fetch('admin.php');
                const htmlText = await pageFetch.text();
                const parser = new DOMParser();
                const doc = parser.parseFromString(htmlText, 'text/html');
                
                // Surgical text interpolation matching target structure
                const panels = doc.getElementById('dashboard-split-panels');
                if (panels) {
                    const upNextItems = panels.querySelectorAll('.font-mono.text-gray-600');
                    if (upNextItems.length >= 2) {
                        document.getElementById('rec-next').innerText = upNextItems[0].innerText;
                        document.getElementById('rel-next').innerText = upNextItems[1].innerText;
                    }
                }

            } catch(e) { console.error("Display sync error: ", e); }
        }

        function triggerChimeEffect(numNode, panelNode, borderHighlight, bgHighlight) {
            playLobbyChime();
            numNode.classList.add('scale-105');
            panelNode.classList.add(borderHighlight, bgHighlight);
            setTimeout(() => {
                numNode.classList.remove('scale-105');
                panelNode.classList.remove(borderHighlight, bgHighlight);
            }, 1500);
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

        // Initialize setup loops
        updateTV();
        setInterval(updateTV, 2500);
    </script>
</body>
</html>