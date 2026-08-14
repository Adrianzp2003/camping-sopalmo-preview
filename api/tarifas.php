<?php
/**
 * Tarifas vigentes, leídas del gestor.
 *
 * Es lo que hace que la web no se quede nunca desfasada: si recepción
 * cambia un precio en el PMS, aquí sale cambiado como mucho 15 minutos
 * después (CACHE_TARIFAS_MIN).
 */
declare(strict_types=1);

require_once __DIR__ . '/../includes/api.php';
require_once __DIR__ . '/../includes/disponibilidad.php';

$cache = api_cache_leer('tarifasv2', CACHE_TARIFAS_MIN);
if ($cache !== null) {
    api_cabeceras();
    header('X-Cache: HIT');
    echo $cache;
    exit;
}

try {
    $pdo = getPDO();

    // --- Camping: concepto x temporada ---
    $camping = ['baja' => [], 'alta' => []];
    $st = $pdo->query("SELECT concepto, temporada, precio FROM tarifas_temporada");
    foreach ($st->fetchAll() as $r) {
        $t = $r['temporada'] === 'alta' ? 'alta' : 'baja';
        $camping[$t][$r['concepto']] = round((float) $r['precio'], 2);
    }

    // --- Casas rurales: temporada x tipo x pax ---
    $rural = ['baja' => [], 'alta' => []];
    $st = $pdo->query("SELECT temporada, tipo, pax, precio, min_noches
                         FROM tarifas_rural ORDER BY temporada, tipo, pax");
    foreach ($st->fetchAll() as $r) {
        $t = $r['temporada'] === 'alta' ? 'alta' : 'baja';
        $rural[$t][] = [
            'tipo'       => (string) $r['tipo'],
            'pax'        => (int) $r['pax'],
            'precio'     => round((float) $r['precio'], 2),
            'min_noches' => (int) $r['min_noches'],
        ];
    }

    // --- Larga estancia (invierno): tramos por nº de días ---
    $invierno = [];
    $st = $pdo->query("SELECT dias_min, dias_max, precio_noche, incluye_personas, incluye_parcela
                         FROM tarifas_invierno ORDER BY dias_min ASC");
    foreach ($st->fetchAll() as $r) {
        $invierno[] = [
            'dias_min'         => (int) $r['dias_min'],
            'dias_max'         => (int) $r['dias_max'],
            'precio_noche'     => round((float) $r['precio_noche'], 2),
            'incluye_personas' => (int) $r['incluye_personas'],
            'incluye_parcela'  => (bool) $r['incluye_parcela'],
        ];
    }

    $salida = [
        'ok'           => true,
        'camping'      => $camping,
        'rural'        => $rural,
        'invierno'     => $invierno,
        'casas'        => array_values(unidades_rurales($pdo)),
        'capacidad'    => capacidad_parcelas($pdo),
        'actualizado'  => date('c'),
    ];

    $json = json_encode($salida, JSON_UNESCAPED_UNICODE);
    api_cache_guardar('tarifasv2', $json, CACHE_TARIFAS_MIN);

    api_cabeceras();
    header('X-Cache: MISS');
    echo $json;

} catch (Throwable $e) {
    api_error('No se pudieron cargar las tarifas en este momento.', 503, $e);
}
