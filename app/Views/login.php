<?php
$titulo = 'Iniciar sesión';
$claseBody = 'pagina-login';
require __DIR__ . '/partials/cabecera.php';
?>
<div class="login-screen">
    <aside class="login-aside" aria-label="SFATSA">
        <div class="login-aside__brand">SFATSA</div>
        <div class="login-aside__copy">
            <span class="eyebrow eyebrow--light">SISTEMA DE ADMINISTRACIÓN</span>
            <h1>Control claro.<br> Gestión confiable.</h1>
            <p>Acceso institucional para la administración de usuarios y conductores.</p>
        </div>
        <span class="login-aside__footer">SFATSA · Acceso institucional</span>
    </aside>
    <main class="login-main">
        <section class="login-card" aria-labelledby="login-title">
            <span class="login-card__index">01 / LOGIN</span>
            <h2 id="login-title">Bienvenido</h2>
            <p class="login-card__intro">Ingrese sus credenciales para acceder a SFATSA.</p>
            <?php if ($error !== ''): ?>
                <div class="sfatsa-alert" id="login-error" role="alert" tabindex="-1" data-error-focus>
                    <span class="alert__mark" aria-hidden="true">!</span>
                    <span><?= $escapar($error) ?></span>
                </div>
            <?php endif; ?>
            <form action="/?ruta=login" method="post" data-form-acceso>
                <input type="hidden" name="csrf" value="<?= $escapar($token) ?>">
                <div class="login-fields">
                    <div class="field">
                        <label class="form-label field__label" for="usuario">Usuario <span class="required" aria-hidden="true">*</span></label>
                        <input class="form-control field__control" id="usuario" name="usuario" type="text"
                               value="<?= $escapar($usuario) ?>" placeholder="Ingrese su usuario"
                               autocomplete="username" autocapitalize="none" spellcheck="false" maxlength="50" required<?= $error !== '' ? ' aria-invalid="true" aria-describedby="login-error"' : '' ?>>
                    </div>
                    <div class="field">
                        <label class="form-label field__label" for="contrasena">Contraseña <span class="required" aria-hidden="true">*</span></label>
                        <input class="form-control field__control" id="contrasena" name="contrasena" type="password"
                               placeholder="Ingrese su contraseña" autocomplete="current-password" required<?= $error !== '' ? ' aria-invalid="true" aria-describedby="login-error"' : '' ?>>
                    </div>
                </div>
                <button class="btn button button--primary w-100" type="submit" data-submit>
                    <span class="loading-indicator" aria-hidden="true" hidden></span>
                    <span data-button-label>Iniciar sesión</span>
                </button>
                <span class="visually-hidden" role="status" aria-live="polite" data-submit-status></span>
            </form>
            <p class="login-help">Si tiene problemas para acceder, contacte al administrador del sistema.</p>
        </section>
    </main>
</div>
<?php require __DIR__ . '/partials/pie.php'; ?>
