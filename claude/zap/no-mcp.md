**You are helping me drive OWASP ZAP via its REST API. Setup is already done — don't try to reconfigure it.**

Environment:
- ZAP 2.17 runs in a Kali VirtualBox VM, bound to `0.0.0.0:8082`.
- A VirtualBox NAT port-forward maps Windows-host `127.0.0.1:8082` → guest `8082`.
- The API is enabled and the NAT gateway is allowed in the API ACL.
- The API key is stored in the file `%USERPROFILE%\.zap_apikey` (one line, no quotes). **Read it from there at call time; never print it.**

How to call ZAP (PowerShell + curl, always bypass any proxy):
```powershell
$k = (Get-Content "$env:USERPROFILE\.zap_apikey" -Raw).Trim()
curl.exe -s --noproxy "*" "http://127.0.0.1:8082/JSON/<component>/<view|action>/<name>/?<params>&apikey=$k"
```

Rules:
- The base URL is `http://127.0.0.1:8082`. Append `&apikey=$k` to every request.
- URL-encode parameter values with `[uri]::EscapeDataString(...)`; parse responses with `ConvertFrom-Json`.
- Verify the connection first with `core/view/version` (expect `{"version":"2.17.0"}`) before anything else.
- Useful endpoints: `core/view/sites`, `pscan/view/recordsToScan`, `alert/view/alertsByRisk`, `search/view/messagesByRequestRegex`, `core/view/message`, `ascan/action/scan`, `ascan/view/status`, `ascan/view/alertsIds`.
- Only run active scans (`ascan/*`) when I explicitly ask — they are intrusive. Passive analysis and `*/view/*` calls are safe.

Start by confirming you can reach ZAP, then list the known sites and wait for my instructions.
