<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#7B1717">
    <title>Software Security – Certificate Generator</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preload" href="/commons/fonts/memSYaGs126MiZpBA-UvWbX2vVnXBbObj2OVZyOOSr4dVJWUgsjZ0B4gaVI.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="/commons/style.css">
    <style>
        #logBox { max-height: 28rem; }
        .log-line      { padding: 0.125rem 0; }
        .log-line.acme { color: #17177B; }
        .log-line.err  { color: #7B1717; font-weight: 600; }
        .log-line.ok   { color: #177B17; font-weight: 600; }
        .log-line.head { color: #000; font-weight: 700; padding-top: 0.5rem; }
        .spin { animation: spin 0.9s linear infinite; }
        @keyframes spin { to { transform: rotate(360deg); } }
    </style>
</head>
<body class="min-h-screen p-4 md:p-8">

    <nav class="mb-6">
        <a href="/" class="inline-flex items-center gap-2 text-sm font-medium text-gray-500 hover:text-[#7B1717] transition-colors">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
            </svg>
            All Tools
        </a>
    </nav>

    <header class="text-center mb-10">
        <img src="https://hannesmolsen.de/images/software-security_logo.png" alt="Software Security" class="mx-auto h-20 mb-4 rounded-lg" />
        <h1 class="text-4xl font-extrabold text-gray-900 mb-2">Certificate Generator</h1>
        <p class="text-xl text-gray-500">Issue fresh Let&rsquo;s Encrypt certificates for the training VM domains</p>
    </header>

    <main class="max-w-4xl mx-auto space-y-6">

        <!-- ══ Credentials ═══════════════════════════════════════════════════ -->
        <div class="bg-white p-6 md:p-8 rounded-xl shadow-2xl">
            <h2 class="text-2xl font-bold text-black mb-6 border-b pb-2">KAS Credentials</h2>

            <div class="p-3 rounded-lg border text-sm bg-[#E6E6F8] text-[#17177B] border-[#17177B] mb-6">
                Issues certificates for <strong>vulnerads.de</strong> and <strong>attacat.de</strong> via the
                Let&rsquo;s Encrypt <code class="font-mono">dns-01</code> challenge. The
                <code class="font-mono">_acme-challenge</code> TXT records are created and removed automatically
                through the All-Inkl KAS API, and the result is packed into
                <code class="font-mono">certs.tar</code> for the VMs to pick up at boot.
            </div>

            <form id="certForm" action="certgen_post.php" method="post" autocomplete="off">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">

                    <div>
                        <label for="username" class="block text-sm font-medium text-gray-700 mb-1">KAS User</label>
                        <input type="text" name="username" id="username" value="w0060714" readonly
                            class="w-full p-3 border border-gray-300 rounded-lg bg-[#E7E6E6] text-gray-600 font-mono text-sm shadow-sm cursor-not-allowed" />
                        <p class="mt-1 text-xs text-gray-400">Fixed server-side</p>
                    </div>

                    <div>
                        <label for="password" class="block text-sm font-medium text-gray-700 mb-1">KAS Password</label>
                        <div class="relative">
                            <input type="password" name="password" id="password" required
                                autocomplete="current-password" placeholder="••••••••••••"
                                class="w-full p-3 pr-11 border border-gray-300 rounded-lg focus:ring-[#17177B] focus:border-[#17177B] transition shadow-sm font-mono text-sm bg-[#E7E6E6]" />
                            <button type="button" id="togglePw" title="Show / hide password"
                                class="absolute inset-y-0 right-0 px-3 flex items-center text-gray-400 hover:text-[#17177B] transition-colors">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                </svg>
                            </button>
                        </div>
                    </div>

                    <div>
                        <label for="otp" class="block text-sm font-medium text-gray-700 mb-1">2FA One-Time PIN</label>
                        <input type="text" name="otp" id="otp" required inputmode="numeric" pattern="[0-9]*"
                            maxlength="6" autocomplete="one-time-code" placeholder="123456"
                            class="w-full p-3 border border-gray-300 rounded-lg focus:ring-[#17177B] focus:border-[#17177B] transition shadow-sm font-mono text-sm tracking-[0.3em] bg-[#E7E6E6]" />
                        <p class="mt-1 text-xs text-gray-400">Valid for ~30 seconds</p>
                    </div>
                </div>

                <div class="mt-6 flex flex-wrap items-center gap-3">
                    <button type="submit" id="submitBtn"
                        class="inline-flex items-center gap-2 px-4 py-3 bg-[#7B1717] text-white font-semibold rounded-lg hover:bg-[#C05C5C] transition duration-150 shadow-md focus:outline-none focus:ring-2 focus:ring-[#7B1717] focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed">
                        <svg id="submitIcon" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M10.125 2.25h-4.5c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125v-9M10.125 2.25h.375a9 9 0 019 9v.375M10.125 2.25A3.375 3.375 0 0113.5 5.625v1.5c0 .621.504 1.125 1.125 1.125h1.5a3.375 3.375 0 013.375 3.375M9 15l2.25 2.25L15 12" />
                        </svg>
                        <span id="submitLabel">Generate Certificates</span>
                    </button>

                    <button type="button" id="resetBtn"
                        class="px-4 py-3 border-2 border-gray-300 text-gray-700 font-semibold rounded-lg hover:border-gray-400 hover:bg-gray-50 transition duration-150">
                        Clear
                    </button>

                    <p class="text-xs text-gray-400 ml-auto">A run can take several minutes while DNS propagates.</p>
                </div>
            </form>

            <div id="status" class="hidden mt-6"></div>
        </div>

        <!-- ══ Run log ═══════════════════════════════════════════════════════ -->
        <div id="logCard" class="hidden bg-white p-6 md:p-8 rounded-xl shadow-2xl">
            <div class="flex items-center justify-between gap-3 mb-6 border-b pb-2">
                <h2 class="text-2xl font-bold text-black">Run Log</h2>
                <button type="button" id="copyLog"
                    class="px-3 py-1.5 border-2 border-gray-300 text-gray-700 text-sm font-semibold rounded-lg hover:border-gray-400 hover:bg-gray-50 transition duration-150">
                    Copy log
                </button>
            </div>
            <div id="logBox" class="p-4 rounded-lg bg-[#E7E6E6] font-mono text-xs leading-relaxed overflow-auto whitespace-pre-wrap"></div>
        </div>

        <p class="text-center text-xs text-gray-400">
            Credentials are posted straight to <code class="font-mono">certgen_post.php</code> on this host and are
            never stored in the browser.
        </p>

    </main>

    <footer class="mt-12 py-6 text-center text-sm text-gray-500">
        <p class="mb-2">&copy; 2026 Software Security</p>
        <ul class="flex justify-center gap-6 list-none p-0">
            <li><a href="https://hannesmolsen.de/impressum.html" class="hover:underline">Impressum</a></li>
            <li><a href="https://hannesmolsen.de/datenschutz.html" class="hover:underline">Datenschutz</a></li>
            <li><a href="https://hannesmolsen.de" class="hover:underline">Hannes Molsen</a></li>
        </ul>
    </footer>

    <script>
        // ── DOM refs ───────────────────────────────────────────────────────────
        const certForm    = document.getElementById('certForm');
        const passwordEl  = document.getElementById('password');
        const otpEl       = document.getElementById('otp');
        const togglePw    = document.getElementById('togglePw');
        const submitBtn   = document.getElementById('submitBtn');
        const submitIcon  = document.getElementById('submitIcon');
        const submitLabel = document.getElementById('submitLabel');
        const resetBtn    = document.getElementById('resetBtn');
        const statusEl    = document.getElementById('status');
        const logCard     = document.getElementById('logCard');
        const logBox      = document.getElementById('logBox');
        const copyLog     = document.getElementById('copyLog');

        const STATUS_STYLES = {
            success: 'bg-[#E6F3E6] text-[#177B17] border-[#177B17]',
            error:   'bg-[#F8E6E6] text-[#7B1717] border-[#7B1717]',
            warning: 'bg-[#FFFDE6] text-[#DFDF17] border-[#DFDF17]',
            info:    'bg-[#E6E6F8] text-[#17177B] border-[#17177B]',
        };

        let running = false;

        // ── Status box ─────────────────────────────────────────────────────────
        function showStatus(msg, type = 'info') {
            statusEl.className = `mt-6 p-3 rounded-lg border text-sm ${STATUS_STYLES[type]}`;
            statusEl.textContent = msg;
            statusEl.classList.remove('hidden');
        }

        function hideStatus() {
            statusEl.classList.add('hidden');
        }

        // ── Log rendering ──────────────────────────────────────────────────────
        // certgen_post.php streams plain HTML (<pre>, <br>, <b>). Strip the markup
        // and render every line as text, so nothing from the response is ever
        // inserted as live HTML.
        function classifyLine(line) {
            if (/^\[\[ACME\]\]/.test(line))                  return 'acme';
            if (/fehler|error|exception|failed/i.test(line)) return 'err';
            if (/smoooooothly|still good/i.test(line))       return 'ok';
            if (/^={2,}$/.test(line) || /^Generating Certificate for/i.test(line)) return 'head';
            return '';
        }

        function appendLines(lines) {
            const atBottom = logBox.scrollHeight - logBox.scrollTop - logBox.clientHeight < 40;
            for (const raw of lines) {
                const line = raw.replace(/<[^>]*>/g, '').replace(/&nbsp;/g, ' ').trim();
                if (!line) continue;
                const div = document.createElement('div');
                div.className = `log-line ${classifyLine(line)}`;
                div.textContent = line;
                logBox.appendChild(div);
            }
            if (atBottom) logBox.scrollTop = logBox.scrollHeight;
        }

        function setRunning(on) {
            running = on;
            submitBtn.disabled = on;
            submitLabel.textContent = on ? 'Generating…' : 'Generate Certificates';
            submitIcon.classList.toggle('spin', on);
        }

        // ── Actions ────────────────────────────────────────────────────────────
        togglePw.addEventListener('click', () => {
            passwordEl.type = passwordEl.type === 'password' ? 'text' : 'password';
            passwordEl.focus();
        });

        otpEl.addEventListener('input', () => {
            otpEl.value = otpEl.value.replace(/\D/g, '').slice(0, 6);
        });

        resetBtn.addEventListener('click', () => {
            if (running) return;
            passwordEl.value = '';
            otpEl.value = '';
            logBox.textContent = '';
            logCard.classList.add('hidden');
            hideStatus();
            passwordEl.focus();
        });

        copyLog.addEventListener('click', () => {
            const ta = document.createElement('textarea');
            ta.value = logBox.innerText;
            ta.style.position = 'fixed';
            ta.style.left = '-9999px';
            document.body.appendChild(ta);
            ta.select();
            try {
                document.execCommand('copy');
                showStatus('Run log copied to clipboard.', 'success');
            } catch (e) {
                showStatus('Could not copy the log.', 'error');
            }
            document.body.removeChild(ta);
        });

        certForm.addEventListener('submit', async (e) => {
            // No fetch/stream support — fall through to a plain form POST.
            if (!window.fetch || !window.ReadableStream) return;
            e.preventDefault();
            if (running) return;

            if (!passwordEl.value || otpEl.value.length !== 6) {
                showStatus('Enter the KAS password and a 6-digit one-time PIN.', 'warning');
                return;
            }

            setRunning(true);
            hideStatus();
            logBox.textContent = '';
            logCard.classList.remove('hidden');
            showStatus('Contacting the KAS API and Let’s Encrypt — this can take a few minutes.', 'info');

            try {
                const res = await fetch(certForm.action, {
                    method: 'POST',
                    body: new FormData(certForm),
                });
                if (!res.ok) throw new Error(`Server responded with HTTP ${res.status}`);

                const reader  = res.body.getReader();
                const decoder = new TextDecoder();
                let buffer = '';
                let full   = '';

                // Split on <br> — that is how certgen_post.php delimits its output.
                for (;;) {
                    const { done, value } = await reader.read();
                    if (done) break;
                    const chunk = decoder.decode(value, { stream: true });
                    buffer += chunk;
                    full   += chunk;
                    const parts = buffer.split(/<br\s*\/?>/i);
                    buffer = parts.pop();
                    appendLines(parts);
                }
                appendLines([buffer]);

                if (/smoooooothly/i.test(full)) {
                    showStatus('Done — certs.tar has been rebuilt. The VMs will pick it up on next boot.', 'success');
                } else if (/still good/i.test(full)) {
                    showStatus('Nothing to do: an existing certificate still has more than 80 days left, so the run stopped early and certs.tar was not rebuilt.', 'warning');
                } else if (/fehler|exception/i.test(full)) {
                    showStatus('The run reported an error — check the log below.', 'error');
                } else {
                    showStatus('The run ended without a success marker — check the log below.', 'warning');
                }
            } catch (err) {
                const msg = err && err.message ? err.message : String(err);
                appendLines([msg]);
                showStatus('Request failed: ' + msg, 'error');
            } finally {
                setRunning(false);
                otpEl.value = '';
            }
        });

        window.addEventListener('beforeunload', (e) => {
            if (running) { e.preventDefault(); e.returnValue = ''; }
        });
    </script>
</body>
</html>
