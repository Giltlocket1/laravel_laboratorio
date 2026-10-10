<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<meta name="csrf-token" content="{{ csrf_token() }}"><meta name="theme-color" content="#1b396a">
<meta name="description" content="LabKeep ITMA 2. Gestión de laboratorios, materiales y equipos.">
<title>LabKeep · ITMA 2</title>
<link rel="icon" href="/icon.svg" type="image/svg+xml"><link rel="manifest" href="/manifest.webmanifest">
<link rel="stylesheet" href="/assets/app.css">
<script src="/assets/qrcode.min.js" defer></script><script src="/assets/html5-qrcode.min.js" defer></script>
<script src="/assets/app.js" defer></script>
</head>
<body>
<a class="skip-link" href="#main">Ir al contenido</a>
<div id="login-page" class="login-page" hidden>
<section class="login-story"><div class="wordmark"><span class="brand-symbol">L<span>2</span></span><div>ITMA 2<small>GESTIÓN INSTITUCIONAL</small></div></div><div><p class="eyebrow">LABKEEP / LABORATORIOS</p><h1>El conocimiento<br>empieza con<br><em>el cuidado.</em></h1><p>Un espacio para organizar los recursos que hacen posible aprender, experimentar e innovar.</p></div><span class="login-foot">Inventarios · Préstamos · Trazabilidad</span></section>
<section class="login-form"><div><p class="eyebrow">BIENVENIDO A LABKEEP</p><h2>Tu laboratorio,<br>en orden.</h2><p class="muted">Accede con tu cuenta institucional.</p>
<form id="login-form"><label>Usuario<input name="username" autocomplete="username" required autofocus></label><label>Contraseña<input name="password" type="password" autocomplete="current-password" required></label><p id="login-error" role="alert" class="error"></p><button class="primary" type="submit">Ingresar <span>→</span></button></form><p class="small muted">Si necesitas una cuenta o recuperar el acceso, comunícate con la administración del laboratorio.</p></div><footer>ITMA 2 <span>•</span> Sistema de gestión de recursos</footer></section>
</div>
<div id="app-shell" hidden>
<aside class="sidebar"><a href="#home" class="wordmark"><span class="brand-symbol">L<span>2</span></span><div>LabKeep<small>ITMA 2 · LABORATORIOS</small></div></a><div class="sidebar-rule"></div><p class="nav-label">ESPACIO DE TRABAJO</p><nav id="desktop-nav" aria-label="Navegación principal"></nav><div class="sidebar-bottom"><div class="institution-seal">ITMA <b>2</b></div><p>Recursos que impulsan<br>el aprendizaje.</p><small>Gestión institucional</small></div></aside>
<div class="workspace">
<header class="topbar"><div class="mobile-brand">LabKeep <span>ITMA 2</span></div><div class="breadcrumb">Gestión de recursos <span>/</span> <b id="breadcrumb">Inicio</b></div><div class="top-actions"><label class="sr-only" for="inventory-select">Seleccionar inventario</label><select id="inventory-select"></select><button id="account-button" class="avatar" aria-label="Mi cuenta"></button></div></header>
<main id="main" tabindex="-1"><div id="view" aria-live="polite"><p>Cargando…</p></div></main>
<footer class="workspace-footer"><span>LabKeep · ITMA 2</span><span>Gestión responsable, conocimiento compartido.</span></footer>
</div><nav id="mobile-nav" class="mobile-nav" aria-label="Navegación móvil"></nav>
</div>
<dialog id="dialog"><div class="dialog-head"><h2 id="dialog-title"></h2><button id="close-dialog" class="icon-button" aria-label="Cerrar">×</button></div><div id="dialog-content"></div></dialog>
<div id="toast" role="status" hidden></div>
</body></html>

