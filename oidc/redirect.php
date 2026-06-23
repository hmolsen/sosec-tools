<!doctype html>
<html lang="en">
	<head>
		<meta charset="utf-8">
		<meta name="viewport" content="width=device-width, initial-scale=1">
		<meta name="description" content="">
		<meta name="author" content="Hannes Molsen">
		<title>Software Security - OIDC Beispiel - Redirect</title>
		<link rel="stylesheet" href="public/css/bootstrap.min.css" />
		<link rel="preload" href="public/fonts/memSYaGs126MiZpBA-UvWbX2vVnXBbObj2OVZyOOSr4dVJWUgsjZ0B4gaVI.woff2" as="font" type="font/woff2" crossorigin>
		<link rel="stylesheet" href="public/css/style.css" />
		<meta name="theme-color" content="#7B1717">
	</head>

	<body class="bg-light" onLoad="updateFormFieldsFromLocalStorage(); updateVariablesFromRequest(); updateFormAction(); updateLogoutAnchor();">
		<div class="container">
			<nav class="pt-3 mb-2">
				<a href="/" class="text-muted text-decoration-none d-inline-flex align-items-center gap-1" style="font-size:0.875rem;">
					<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
						<path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
					</svg>
					All Tools
				</a>
			</nav>
			<main>
				<div class="py-5 text-center">
					<img class="d-block mx-auto mb-4" src="public/img/software-security_logo.png" alt="" width="200px">
					<h2>Callback-URL<br><span class="text-muted">vom Identity-Provider aufgerufen</span></h2>
					<p class="lead">An diese Seite wurden vom Identity Provider die im <code>response_type</code> angeforderten Werte geschickt.</p>
					<p id="retrieved_text"></p>
					<code id="retrieved_data"></code>
					<p>Du solltest noch überprüfen, dass der zurückgegebene State (<code id="state_from_response"></code>) mit dem abgeschickten State (<code id="state_from_ls"></code>) übereinstimmt, den du in der Authentifizierungsanfrage an den Identity-Provider gesendet hast, sonst könnte es sein, dass du gerade per CSRF angegriffen wirst.</p>
				</div>

				<div class="col-md-12 col-lg-12">
					<h4 class="mb-3">Client-Konfiguration</h4>
					<form class="needs-validation" method="POST" id="auth" action="" target="token_target" novalidate>
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
								<input type="text" class="form-control" id="clientId" name="client_id" placeholder="zTembCZ86LMQ907kUXo9An7lS97Dv6Rj" value="" required>
								<div class="invalid-feedback">
									Client ID muss angegeben werden.
								</div>
							</div>

							<div class="col-12">
								<label for="clientSecret" class="form-label">Client Secret <span class="text-muted">(Bei Confidential Client mitschicken, sonst nicht)</span></label>
								<div class="input-group">
									<div class="input-group-text"><input class="form-check-input" type="checkbox" id="withClientSecret" checked></div>
									<input type="text" class="form-control" id="clientSecret" name="client_secret" placeholder="mNANOuruwmWkdA-GzpTEaCEmTyO31hz0piT0O6RtDzAjQKYXQ2JdKe0v4kUY_2Rr" value="" required>
								</div>
								<div class="invalid-feedback">
									Client Secret muss angegeben werden.
								</div>
							</div>

							<div class="col-12">
								<label for="redirectUri" class="form-label">Redirect-URI</label>
								<input type="text" class="form-control" id="redirectUri" name="redirect_uri" placeholder="https://cqrity.de/oidc/redirect.php" value="https://cqrity.de/oidc/redirect.php" readonly required>
								<div class="invalid-feedback">
									Redirect-URI muss angegeben werden.
								</div> 
							</div>

							<div class="col-12">
								<label for="grantType" class="form-label">Grant Type</label>
								<input type="text" class="form-control" id="grantType" name="grant_type" placeholder="authorization_code" value="authorization_code" readonly required>
								<div class="invalid-feedback">
									Grant Type muss authorization_code sein.
								</div> 
							</div>

							<div class="col-12">
								<label for="code" class="form-label">Authorization Code</label>
								<input type="text" class="form-control" id="code" name="code" placeholder="code" value="" readonly required>
								<div class="invalid-feedback">
									Authorization Code muss aus Query-Parameter der Response kommen sein.
								</div> 
							</div>

							<div class="col-12">
								<label for="code" class="form-label">Code Verifier</label>
								<input type="text" class="form-control" id="codeVerifier" name="code_verifier" placeholder="code_verifier" value="code_verifier"  required>
								<div class="invalid-feedback">
									Code Verifier muss der selbe sein, wie auf der letzten Seite.
								</div> 
							</div>

							<hr class="my-4">

							<button class="btn btn-primary btn-lg" type="submit">Token per POST-Request vom Token Server abfragen</button>
						</div>
					</form>
					
					<hr class="my-4">
					<h4 class="mb-3">Token-Server Antwort:</h4>
					<code id="response">Request noch nicht abgeschickt...</code>
					<hr class="my-4">
					<div class="d-grid gap-2 col-6 mx-auto">
						<a class="btn btn-sm btn-secondary" href="/oidc" role="button">🔄️ Neuen Authorization Code abfragen</a>
						<a class="btn btn-sm btn-danger" id="logoutUrl" href="#" role="button">🚮 Auth0-Account ausloggen</a>
						<a class="btn btn-sm btn-secondary" id="debugIdToken" href="#" role="button" target="_blank">🪲 ID-Token debuggen</a>
						<a class="btn btn-sm btn-secondary" id="debugAccessToken" href="#" role="button" target="_blank">🪲 Access-Token debuggen</a>
					</div>
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


		<script src="public/js/callback.js"></script>
	</body>
</html>
