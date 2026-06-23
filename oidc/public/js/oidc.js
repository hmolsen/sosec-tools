const oidcConfig = document.getElementById('oidcConfig');

// Example starter JavaScript for disabling form submissions if there are invalid fields
(() => {
    'use strict'

    // Fetch all the forms we want to apply custom Bootstrap validation styles to
    const forms = document.querySelectorAll('.needs-validation')

        // Loop over them and prevent submission
        Array.from(forms).forEach(form => {
            form.addEventListener('submit', event => {
                if (!form.checkValidity()) {
                    event.preventDefault()
                    event.stopPropagation()
                }

                form.classList.add('was-validated')

                // Store relevant values in localStorage
                localStorage.setItem("oidcConfig", document.getElementById("oidcConfig").value);
                localStorage.setItem("clientId", document.getElementById("clientId").value);
                localStorage.setItem("codeVerifier", document.getElementById("codeVerifier").value);
                localStorage.setItem("state", document.getElementById("state").value);
            }, false)
        })
})()

function autoUpdateUrlOnFormChange() {
    var form = document.getElementById("auth");
    form.addEventListener("input", function (event) {
        updateUrl();
    });
    updateUrl();
}

function updateUrl() {
    var form = document.getElementById("auth");
    document.getElementById("url").innerText = form.action + "\n  ?" + new URLSearchParams(new FormData(form)).toString().replace(/&/g, '\n  &');
}

// GENERATING CODE VERIFIER
function dec2hex(dec) {
    return ("0" + dec.toString(16)).substr(-2);
}

function generateRandomBytes(num_bytes) {
    var array = new Uint32Array(num_bytes);
    window.crypto.getRandomValues(array);
    return Array.from(array, dec2hex).join("");
}

function generateCodeVerifier() {
    return generateRandomBytes(56 / 2);
}

function getRandomNonce() {
    document.getElementById("nonce").value = generateRandomBytes(8);
	updateUrl();
}

function getRandomState() {
    document.getElementById("state").value = generateRandomBytes(8);
	updateUrl();
}

function getCodeVerifier() {
    document.getElementById("codeVerifier").value = generateCodeVerifier();
    getCodeChallenge();
}

// GENERATING CODE CHALLENGE FROM VERIFIER
function sha256(plain) {
    // returns promise ArrayBuffer
    const encoder = new TextEncoder();
    const data = encoder.encode(plain);
    return window.crypto.subtle.digest("SHA-256", data);
}

function base64urlencode(a) {
    var str = "";
    var bytes = new Uint8Array(a);
    var len = bytes.byteLength;
    for (var i = 0; i < len; i++) {
        str += String.fromCharCode(bytes[i]);
    }
    return btoa(str)
    .replace(/\+/g, "-")
    .replace(/\//g, "_")
    .replace(/=+$/, "");
}

async function generateCodeChallengeFromVerifier(v) {
    var hashed = await sha256(v);
    var base64encoded = base64urlencode(hashed);
    return base64encoded;
}

async function getCodeChallenge() {
    let codeVerifier = document.getElementById("codeVerifier").value;
    try {
        let codeChallenge = await generateCodeChallengeFromVerifier(
                codeVerifier);
        document.getElementById("codeChallenge").value = codeChallenge;
        updateUrl();
    } catch (e) {
        document.getElementById("codeChallenge").value = JSON.stringify(e);
    }
}

function updateFormAction() {
    document.getElementById("auth").action = localStorage.getItem("authorization_endpoint");
}

function retrieveOidcEndpoints() {
	fetch(oidcConfig.value)
	.then(response => response.json())  // Antwort als JSON parsen
	.then(data => {
		localStorage.setItem("authorization_endpoint", data.authorization_endpoint);
		localStorage.setItem("token_endpoint", data.token_endpoint);
		localStorage.setItem("end_session_endpoint", data.end_session_endpoint);
		updateFormAction();
		updateUrl();
	})
	.catch(error => {
		console.error('Fehler:', error);
	});
}

// Zugriff auf Checkbox und Eingabefeld
const withNonce = document.getElementById('withNonce');
const nonce = document.getElementById('nonce');

// Event Listener für Checkbox
withNonce.addEventListener('change', function() {
	// Toggle der disabled-Eigenschaft des Eingabefelds
	nonce.disabled = !withNonce.checked;
});

function updateFormFieldsFromLocalStorage() {
    document.getElementById("oidcConfig").value = localStorage.getItem("oidcConfig");
    document.getElementById("clientId").value = localStorage.getItem("clientId");
	updateFormAction();
}