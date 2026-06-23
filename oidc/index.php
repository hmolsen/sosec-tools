<!doctype html>
<html lang="en">
	<head>
		<meta charset="utf-8">
		<meta name="viewport" content="width=device-width, initial-scale=1">
		<meta name="description" content="">
		<meta name="author" content="Hannes Molsen">
		<title>Software Security - OIDC Beispiel</title>
		<link rel="stylesheet" href="public/css/bootstrap.min.css" />
		<link rel="preload" href="public/fonts/memSYaGs126MiZpBA-UvWbX2vVnXBbObj2OVZyOOSr4dVJWUgsjZ0B4gaVI.woff2" as="font" type="font/woff2" crossorigin>
		<link rel="stylesheet" href="public/css/style.css" />
		<meta name="theme-color" content="#7B1717">
	</head>
	
	<body class="bg-light" onload="getCodeVerifier(); getRandomState(); getRandomNonce(); updateFormFieldsFromLocalStorage(); autoUpdateUrlOnFormChange();">
		<div class="container">
			<main>
				<div class="py-5 text-center">
					<img class="d-block mx-auto mb-4" src="public/img/software-security_logo.png" alt="" width="200px">
					<h2>OAuth2/OIDC Beispiel</h2>
					<p class="lead">Hier kannst du deinen OAuth2 / OpenID Connect Request zusammenbauen, um einen Authorization Code abzufragen und diesen dann gegen ein ID-Token einzutauschen.</p>
				</div>

				<div class="col-md-12 col-lg-12">
					<h4 class="mb-3">Client-Konfiguration</h4>
					<form class="needs-validation" method="GET" id="auth" action="https://dev-an3p41xzjonjbfd3.us.auth0.com/authorize/" novalidate>
						<div class="row g-3">
							<div class="col-12">
								<label for="oidcConfig" class="form-label">OpenID-Configuration URL <br><span class="text-muted">(Auth0: Advanced Settings -> Endpoints, Keycloak: Realm Settings -> Endpoints)</span></label>
								<input type="text" class="form-control" id="oidcConfig" placeholder="https://dev-an3p41xzjonjbfd3.us.auth0.com/.well-known/openid-configuration" value="" onInput="retrieveOidcEndpoints()" required>
								<div class="invalid-feedback">
									OpenID-Configuration Domain muss angegeben werden.
								</div>
							</div>

							<div class="col-12">
								<label for="clientId" class="form-label">Client ID <span class="text-muted">(Aus Applikation / Client Einstellungen)</span></label>
								<input type="text" class="form-control" id="clientId" name="client_id" placeholder="zTembCZ86LMQ907kUXo9An7lS97Dv6Rj" value="zTembCZ86LMQ907kUXo9An7lS97Dv6Rj" required>
								<div class="invalid-feedback">
									Client ID muss angegeben werden.
								</div>
							</div>

							<div class="col-12">
								<label for="nonce" class="form-label">Nonce <span class="text-muted">(Die Nonce kommt im ID-Token zurück, so kannst du in deiner Single-Page-Application überprüfen, dass dir keiner ein altes oder falsches ID-Token zurückgibt. Die Nonce wird also nur verpflichtend benötigt, wenn du ein Access-Token oder ID-Token zurückbekommen möchtest. Dies kannst du über den response_type steuern.)</span></label>
								<div class="input-group has-validation">
									<button class="btn btn-outline-secondary" type="button" id="button-addon1" onClick="getRandomNonce()" >🎲 Generieren</button>
									<div class="input-group-text"><input class="form-check-input" type="checkbox" id="withNonce"></div>
									<input type="text" class="form-control" id="nonce" name="nonce" placeholder="example-nonce" value="example-nonce" disabled>
									<div class="invalid-feedback">
										Nonce muss angegeben werden.
									</div>
								</div>			  
							</div>

							<div class="col-12">
								<label for="state" class="form-label">State <span class="text-muted">(Der State kommt im Redirect zur Callback-URL als Parameter zurück. Du kannst überprüfen, ob der dort erhaltene State zu dem State passt, den du hier gewürfelt hast. So kannst du verhindern, dass jeder von überall deine Callback-URL aufrufen kann. Diese Art Angriff nennt man auch Cross-Site Request Forgery, kurz CSRF.)</span></label>
								<div class="input-group has-validation">
									<button class="btn btn-outline-secondary" type="button" id="button-addon1" onClick="getRandomState()" >🎲 Generieren</button>
									<input type="text" class="form-control" id="state" name="state" placeholder="example-state" value="example-state">
									<div class="invalid-feedback">
										State muss angegeben werden.
									</div>
								</div>			  
							</div>

							<div class="col-12">
								<label for="codeVerifier" class="form-label">Code Verifier <span class="text-muted">(Zufallswert für das PKCE-Verfahren beim Authorization Code Flow. PKCE bedeutet Proof Key for Code Exchange, also ein Beweisschlüssel für den Tausch des Authorization Codes. Diesen Wert schickst du nicht mit an den Authorization Server, sondern berechnest zunächst die Code Challenge, also den SHA256-hash des verifiers. Den Verifier merkst du dir nur. Hier passiert das in LocalStorage, so dass wir auf der Callback-Seite wieder auf den Wert zugreifen können.)</span></label>
								<div class="input-group has-validation">
									<button class="btn btn-outline-secondary" type="button" id="button-addon1" onClick="getCodeVerifier()" >🎲 Generieren</button>
									<input type="text" class="form-control" id="codeVerifier" placeholder="" value="" onInput="getCodeChallenge()" required disabled>
									<div class="invalid-feedback">
										Code Verifier muss generiert oder eingegeben werden.
									</div>
								</div>
							</div>

							<div class="col-12">
								<label for="codeChallenge" class="form-label">Code Challenge <span class="text-muted">(SHA256 vom Code Verifier. Bei der Anfrage an den Authorization Server wird diese challenge mitgesendet. Auf der Callback-Seite kann man dann per POST-Request von Token Server den erhaltenen Authorization Code gegen ein Access Token tauschen. Bei diesem Request wird dann der Code Verifier mitgeschickt. So kann der Token Server überprüfen, dass die selbe Applikation das Token abruft, welche vorher den Authorization Code abgefragt hat. Das schützt davor, dass jemand den Authorization Code stiehlt und selbst gegen ein gültiges Access-Token tauscht.)</span></label>
								<div class="input-group has-validation">
									<button class="btn btn-outline-secondary" type="button" id="button-addon1" onClick="getCodeChallenge()">🔢 Berechnet</button>
									<input type="text" class="form-control" id="codeChallenge" name="code_challenge" placeholder="" value="" required>
									<div class="invalid-feedback">
										Code Challenge muss berechnet oder eingegeben werden.
									</div>
								</div>
							</div>

							<div class="col-12">
								<label for="codeChallengeMethod" class="form-label">Code Challenge Method <span class="text-muted">(Plain oder SHA256. Sollte immer SHA256 sein und ist deshalb hier nicht änderbar.)</label>
								<input type="text" class="form-control" id="codeChallengeMethod" name="code_challenge_method" placeholder="S256" value="S256" readonly>
								<div class="invalid-feedback">
									Code Challenge Method muss S256 sein.
								</div> 
							</div>

							<div class="col-12">
								<label for="redirectUri" class="form-label">Redirect-URI</label>
								<input type="text" class="form-control" id="redirectUri" name="redirect_uri" placeholder="https://cqrity.de/oidc/redirect.php" value="https://cqrity.de/oidc/redirect.php" readonly>
								<div class="invalid-feedback">
									Redirect-URI muss angegeben werden.
								</div> 
							</div>

							<div class="col-12">
								<label for="responseType" class="form-label">Response Type <span class="text-muted">(Mit Leerzeichen getrennt: code token id_token)</span></label>
								<input type="text" class="form-control" id="responseType" name="response_type" placeholder="code" value="code" required>
								<div class="invalid-feedback">
									Response Type muss code sein.
								</div> 
							</div>

							<div class="col-12">
								<label for="scope" class="form-label">Scope <span class="text-muted">(Mit Leerzeichen getrennt, z.B. "openid name given_name email")</span></label>
								<input type="text" class="form-control" id="responseType" name="scope" placeholder="openid" value="openid" required>
								<div class="invalid-feedback">
									Scope muss gesetzt sein, z.B. auf <code>openid</code>.
								</div> 
							</div>

							<button class="w-100 btn btn-primary btn-lg" type="submit">Authorization Code anfordern</button>
							
							<hr class="my-4">

							<p>Die per GET aufgerufene URL wird so aussehen:</p>
							<pre id="url"></pre>

						</div>
					</form>
				</div>
			</main>

			<footer class="my-5 pt-5 text-muted text-center text-small">
				<p class="mb-1">&copy; 2024 Software Security</p>
				<ul class="list-inline">
					<li class="list-inline-item"><a href="https://hannesmolsen.de/impressum.html">Impressum</a></li>
					<li class="list-inline-item"><a href="https://hannesmolsen.de/datenschutz.html">Datenschutz</a></li>
					<li class="list-inline-item"><a href="https://www.oose.de/seminar/web-authentifizierung">Schulung</a></li>
				</ul>
			</footer>
		</div>
		
		<script src="public/js/oidc.js"></script>
	</body>
</html>
