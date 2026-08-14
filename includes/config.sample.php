<?php
/**
 * Configuración de la web pública de Camping Sopalmo.
 *
 * Copia este fichero como `config.php` y rellena los valores reales.
 * `config.php` NO debe subirse a ningún repositorio.
 *
 * La web lee (solo lectura) la misma base de datos que el PMS, para que
 * las tarifas y la disponibilidad que ve el cliente sean siempre las que
 * hay en el gestor. No escribe nada.
 */
declare(strict_types=1);

// ---- Base de datos (la misma del PMS) ----
define('DB_HOST', 'localhost');
define('DB_PORT', 3306);
define('DB_NAME', 'campings_PMS');
define('DB_USER', 'usuario_solo_lectura');
define('DB_PASS', 'PON_AQUI_LA_CONTRASENA');

// ---- Datos del negocio (se pintan en la web) ----
define('NEG_NOMBRE',    'Camping Sopalmo');
define('NEG_TELEFONO',  '950 47 84 13');
define('NEG_TEL_E164',  '34950478413');
define('NEG_MOVIL',     '660 73 53 68');
define('NEG_EMAIL',     'campingsopalmo@gmail.com');
define('NEG_DIRECCION', 'Sopalmo, 04638 Mojácar, Almería');
define('NEG_LAT',       37.0647459);
define('NEG_LON',       -1.8690642);
define('NEG_RAZON',     'Sopalmo Camping S.L.');
define('NEG_NIF',       'B04451753');

// ---- Comportamiento ----
// Minutos que se cachean las tarifas en disco (0 = sin caché).
define('CACHE_TARIFAS_MIN', 15);
// Mostrar errores por pantalla. SIEMPRE false en producción.
define('DEBUG', false);


function getPDO(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }
    $dsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT
         . ';dbname=' . DB_NAME . ';charset=utf8mb4';
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
    return $pdo;
}
