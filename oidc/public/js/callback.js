const oidcConfig = document.getElementById('oidcConfig');

(() => {
    'use strict'

    // Fetch all the forms we want to apply custom Bootstrap validation styles to
    const forms = document.querySelectorAll('.needs-validation')

        // Loop over them and prevent submission
        Array.from(forms).forEach(form => {
            form.addEventListener('submit', event => {
				event.preventDefault()
				event.stopPropagation()
                
				if (!form.checkValidity()) {
                    return;
                }

                form.classList.add('was-validated')
				
				const responseCodeBlock = document.getElementById('response');
				const formData = new FormData(form);
				const urlEncodedData = new URLSearchParams(formData).toString();
				
				fetch(form.action, {
					method: 'POST',  // Methode POST
					headers: {
						'Content-Type': 'application/x-www-form-urlencoded'  // URL-encoded Daten senden
					},
					body: urlEncodedData  // Daten als URL-encoded senden
				})
				.then(response => response.json())  // Antwort als JSON parsen
				.then(data => {
					// Antwort in den Absatz schreiben
					responseCodeBlock.innerText = JSON.stringify(data, null, 2);
					updateIdTokenDebugAnchor(data.id_token);
					updateAccessTokenDebugAnchor(data.access_token);
				})
				.catch(error => {
					console.error('Fehler:', error);
					responseCodeBlock.innerText = 'Fehler: ' + error;
				});

                // Store relevant values in localStorage
                localStorage.setItem("oidcConfig", document.getElementById("oidcConfig").value);
                localStorage.setItem("clientId", document.getElementById("clientId").value);
                localStorage.setItem("clientSecret", document.getElementById("clientSecret").value);
                localStorage.setItem("codeVerifier", document.getElementById("codeVerifier").value);
                localStorage.setItem("state", document.getElementById("state").value);
            }, false)
        })
})()

function updateVariablesFromRequest() {
	let responseContent;
	if (window.location.hash) {
		responseContent = new URLSearchParams(window.location.hash.substring(1));
	} else if (window.location.search) {
		responseContent = new URLSearchParams(window.location.search);
	}
	
	const error = responseContent.get('error');
	const error_description = responseContent.get('error_description');
	if (error) {
		document.getElementById("retrieved_text").innerHTML = "<div class=\"alert alert-danger\" role=\"alert\"><p>Da ist etwas schiefgegangen.</p><hr><code>error=" + error + ", error_description=" + error_description + "</code></div>";
	}
	
	const code = responseContent.get('code');
	document.getElementById("code").value = code;
	const access_token = responseContent.get('access_token');
	updateAccessTokenDebugAnchor(access_token);
	const id_token = responseContent.get('id_token');
	updateIdTokenDebugAnchor(id_token);
	const state = responseContent.get('state');
	document.getElementById("state_from_response").innerText = state;
	
	if (responseContent) {
		localStorage.setItem("retrieved_access_token", access_token);
		localStorage.setItem("retrieved_id_token", id_token);
		localStorage.setItem("retrieved_code", code);
		localStorage.setItem("retrieved_state", state);		
	}
	
	if ( !access_token && !id_token && code) {
		document.getElementById("retrieved_text").innerHTML = "Du hast einen Authorization Code zurückbekommen. Diesen kannst du zusammen mit dem Client Secret nutzen, damit dein <em>Server</em> einen POST-Request an den Token Server absetzt, um den Code gegen ein Access/ID Token einzutauschen. Diesen Flow nutzt man für Confidential Clients, da er das Client Secret benötigt. Man nennt den Flow <strong>\"Authorization Code Flow\"</strong>.";
		document.getElementById("retrieved_data").innerText = "authorization_code=" + code;
	} else if ( !access_token && id_token && !code) {
		document.getElementById("retrieved_text").innerHTML = "Du hast ein ID Token zurückbekommen. Diesen kannst du direkt in deiner Single Page Application nutzen, um zu sehen, welcher User gerade eingeloggt ist. Diesen Flow nennt man <strong>\"Implicit Flow\"</strong>, weil das Token direkt (implizit) vom Authorization Server zurückgeschickt wird, obwohl dafür eigentlich der Token Server zuständig ist. Hier ist dein ID-Token:";
		document.getElementById("retrieved_data").innerText = "id_token=" + id_token;
	} else if ( code && (access_token || id_token)) {
		document.getElementById("retrieved_text").innerHTML = "Du hast einen Authorization Code zurückbekommen. Diesen kannst du nutzen, um ihn beim Token Server gegen ein Access Token und ein ID Token einzutauschen. Diesen Flow nennt man eigentlich \"Authorization Code Flow\". Du hast aber auch - genau wie beim \"Implicit Flow\" - mindestens ein Token direkt vom Authorization Server zurückbekommen. Da so beide Flows quasi gemischt sind, nennt man diesen Flow <strong>\"Hybrid Flow\"</strong>.";
		document.getElementById("retrieved_data").innerText = "id_token=" + id_token + "\n----\naccess_token=" + access_token + "\n----\ncode=" + code;
	}
}

function updateFormFieldsFromLocalStorage() {
    document.getElementById("oidcConfig").value = localStorage.getItem("oidcConfig");
    document.getElementById("clientId").value = localStorage.getItem("clientId");
    document.getElementById("clientSecret").value = localStorage.getItem("clientSecret");
    document.getElementById("codeVerifier").value = localStorage.getItem("codeVerifier");
    document.getElementById("state_from_ls").innerText = localStorage.getItem("state");
	updateLogoutAnchor();
}


function updateFormAction() {
    document.getElementById("auth").action = localStorage.getItem("token_endpoint");
}

function updateLogoutAnchor() {
    document.getElementById("logoutUrl").href = localStorage.getItem("end_session_endpoint");// + "?post_logout_redirect_uri=https%3A%2F%2Fcqrity.de%2Foidc";
}

function updateIdTokenDebugAnchor(token) {
    document.getElementById("debugIdToken").href = "https://jwt.io/#id_token=" + token;
}

function updateAccessTokenDebugAnchor(token) {
    document.getElementById("debugAccessToken").href = "https://jwt.io/#id_token=" + token;
}

// Zugriff auf Checkbox und Eingabefeld
const withClientSecret = document.getElementById('withClientSecret');
const clientSecret = document.getElementById('clientSecret');

// Event Listener für Checkbox
withClientSecret.addEventListener('change', function() {
	// Toggle der disabled-Eigenschaft des Eingabefelds
	clientSecret.disabled = !withClientSecret.checked;
});

function retrieveOidcEndpoints() {
	fetch(oidcConfig.value)
	.then(response => response.json())  // Antwort als JSON parsen
	.then(data => {
		localStorage.setItem("authorization_endpoint", data.authorization_endpoint);
		localStorage.setItem("token_endpoint", data.token_endpoint);
		localStorage.setItem("end_session_endpoint", data.end_session_endpoint);
		updateFormAction();
		updateLogoutAnchor();
	})
	.catch(error => {
		console.error('Fehler:', error);
	});
}