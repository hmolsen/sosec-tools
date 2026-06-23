<?php

// index.php interface configuration
$title = "Software Security OIDC Demo";
$img = "https://hannesmolsen.de/images/android-icon-192x192.png";
$scopeInfo = "This service requires the following permissions for your account:";

// Client configuration
$issuer = "https://dev-an3p41xzjonjbfd3.us.auth0.com/";
$clientId = "zTembCZ86LMQ907kUXo9An7lS97Dv6Rj";
// $clientSecret = "some-client-secret";  // comment if you are using PKCE
$pkceCodeChallengeMethod = "S256";   // uncomment to use PKCE
$redirectPage = "auth.php";  // select between "refreshtoken.php" and "auth.php"
$redirectUrl = "https://cqrity.de/oidc/" . $redirectPage;
// add scopes as keys and a friendly message of the scope as value
$scopesDefine = array(
    'openid' => 'log in using your identity',
    'email' => 'read your email address',
    'profile' => 'read your basic profile info',
);
// refreshtoken.php interface configuration
$refreshTokenNote = "NOTE: New refresh tokens expire in 12 months.";
$accessTokenNote = "NOTE: New access tokens expire in 1 hour.";
$manageTokenNote = "You can manage your refresh tokens in the following link: ";
$manageTokens = $issuer . "manage/user/services";
$sessionName = "oidc";  // This value must be the same with the name of the parent directory
$sessionLifetime = 60 * 60;  // must be equal to access token validation time in seconds
$bannerText = "";
$bannerType = "info";  // Select one of "info", "warning", "error" or "success"
$allowIntrospection = false;
$enableActiveTokensTable = false;  // This option works only for MITREid Connect based OPs
$showIdToken = false;
