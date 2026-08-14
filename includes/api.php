<?php
/**
 * Utilidades comunes de las APIs públicas.
 *
 * Nota de seguridad: el código anterior devolvía $e->getMessage() al
 * cliente, lo que filtraba nombres de tablas y detalles del servidor.
 * Aquí el error se registra en el log de PHP y al visitante se le da
 * un mensaje genérico.
 */
declare(strict_types=1);

require_once __DIR__ . '/config.php';

// Este hosting trae serialize_precision=17, y con eso json_encode convierte
// 4.40 en 4.4000000000000004. Con -1 usa la representación más corta que
// reproduce el mismo número, que es lo que queremos en un JSON de precios.
ini_set('serialize_precision', '-1');

function api_cabeceras(): void
{
    header('Content-Type: application/json; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: same-origin');
}

// Sin tipo de retorno `never`: es de PHP 8.1 y el hosting corre 7.4.
function api_ok(array $datos)
{
    api_cabeceras();
    echo json_encode(['ok' => true] + $datos, JSON_UNESCAPED_UNICODE);
    exit;
}

function api_error(string $msg, int $codigo = 400, ?Throwable $e = null)
{
    if ($e !== null) {
        error_log('[web] ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    }
    http_response_code($codigo);
    api_cabeceras();
    echo json_encode([
        'ok'  => false,
        'msg' => DEBUG && $e ? $e->getMessage() : $msg,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

/** Cuerpo JSON del POST, o [] si no hay. */
function api_entrada(): array
{
    $raw = file_get_contents('php://input') ?: '';
    if ($raw === '') {
        return [];
    }
    if (strlen($raw) > 8192) {
        api_error('Petición demasiado grande.', 413);
    }
    $d = json_decode($raw, true);
    return is_array($d) ? $d : [];
}

/** Valida una fecha YYYY-MM-DD real. */
function api_fecha(array $in, string $campo): string
{
    $v = trim((string) ($in[$campo] ?? ''));
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $v)) {
        api_error("Fecha de $campo no válida.");
    }
    [$y, $m, $d] = array_map('intval', explode('-', $v));
    if (!checkdate($m, $d, $y)) {
        api_error("Fecha de $campo no válida.");
    }
    return $v;
}

/** Entero acotado. */
function api_int(array $in, string $campo, int $min, int $max, int $def = 0): int
{
    $v = $in[$campo] ?? $def;
    if (!is_numeric($v)) {
        return $def;
    }
    return max($min, min($max, (int) $v));
}

/**
 * Caché en disco muy simple para respuestas que cambian poco.
 * Devuelve el contenido cacheado o null.
 */
function api_cache_leer(string $clave, int $minutos): ?string
{
    if ($minutos <= 0) {
        return null;
    }
    $f = sys_get_temp_dir() . '/websopalmo_' . preg_replace('/\W/', '', $clave) . '.json';
    if (is_file($f) && (time() - filemtime($f)) < $minutos * 60) {
        $c = file_get_contents($f);
        return $c === false ? null : $c;
    }
    return null;
}

function api_cache_guardar(string $clave, string $contenido, int $minutos): void
{
    if ($minutos <= 0) {
        return;
    }
    $f = sys_get_temp_dir() . '/websopalmo_' . preg_replace('/\W/', '', $clave) . '.json';
    @file_put_contents($f, $contenido, LOCK_EX);
}
