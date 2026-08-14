<?php
/**
 * Presupuesto + disponibilidad en tiempo real.
 *
 * El precio lo calcula la MISMA librería que usa el PMS
 * (includes/precios_helper.php), así que lo que ve el cliente en la web
 * y lo que ve recepción en el gestor no pueden divergir.
 */
declare(strict_types=1);

require_once __DIR__ . '/../includes/api.php';
require_once __DIR__ . '/../includes/precios_helper.php';
require_once __DIR__ . '/../includes/disponibilidad.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    api_error('Método no permitido.', 405);
}

$in = api_entrada();

$entrada = api_fecha($in, 'entrada');
$salida  = api_fecha($in, 'salida');

if ($salida <= $entrada) {
    api_error('La fecha de salida debe ser posterior a la de entrada.');
}
if ($entrada < date('Y-m-d')) {
    api_error('La fecha de entrada no puede estar en el pasado.');
}

$noches = (int) ((strtotime($salida) - strtotime($entrada)) / 86400);
if ($noches > 365) {
    api_error('La estancia no puede superar un año.');
}

$tipo = ($in['tipo'] ?? 'camping') === 'rural' ? 'rural' : 'camping';

try {
    $pdo = getPDO();

    // ---------- Parámetros normalizados ----------
    if ($tipo === 'rural') {
        $personas = api_int($in, 'personas', 1, 5, 2);
        $params = [
            'alojamiento_tipo' => 'rural',
            'entrada'          => $entrada,
            'salida'           => $salida,
            'personas'         => $personas,
        ];
    } else {
        $adultos = api_int($in, 'adultos', 0, 12, 2);
        $ninos   = api_int($in, 'ninos',   0, 12, 0);
        if ($adultos + $ninos < 1) {
            api_error('Indica al menos una persona.');
        }
        $params = [
            'alojamiento_tipo'   => 'camping',
            'entrada'            => $entrada,
            'salida'             => $salida,
            'adultos'            => $adultos,
            'ninos'              => $ninos,
            'perros'             => api_int($in, 'perros',             0, 5, 0),
            'electricidad'       => api_int($in, 'electricidad',       0, 1, 0),
            'tienda_extra'       => api_int($in, 'tienda_extra',       0, 5, 0),
            'caravana_extra'     => api_int($in, 'caravana_extra',     0, 5, 0),
            'autocaravana_extra' => api_int($in, 'autocaravana_extra', 0, 5, 0),
            'coche_extra'        => api_int($in, 'coche_extra',        0, 5, 0),
            'moto_extra'         => api_int($in, 'moto_extra',         0, 5, 0),
            'frigorifico_extra'  => api_int($in, 'frigorifico_extra',  0, 5, 0),
            // Larga estancia: el gestor aplica tramos por nº de días
            'invierno_larga'     => !empty($in['larga_estancia']) ? 1 : 0,
        ];
    }

    // ---------- Precio (misma librería que el PMS) ----------
    $res = calcular_precio_reserva($pdo, $params);

    if (empty($res['ok'])) {
        api_error($res['msg'] ?? 'No se pudo calcular el precio para esas fechas.');
    }

    // ---------- Disponibilidad real ----------
    if ($tipo === 'rural') {
        $disp = ocupacion_rural($pdo, $entrada, $salida);
        $detalle = [
            'casas' => array_map(
                static fn($c) => ['nombre' => $c['nombre'], 'estado' => $c['libre'] ? 'libre' : 'ocupada'],
                $disp['casas']
            ),
        ];
        $mensaje = $disp['hay']
            ? ($disp['libres'] === 1 ? 'Queda una casa libre para esas fechas.' : 'Hay casas libres para esas fechas.')
            : 'Las dos casas están ocupadas en esas fechas.';
    } else {
        $disp = ocupacion_camping($pdo, $entrada, $salida);
        $detalle = [
            'libres'    => $disp['libres'],
            'capacidad' => $disp['capacidad'],
        ];
        $mensaje = $disp['hay']
            ? ($disp['libres'] <= 3
                ? 'Quedan pocas parcelas libres (' . $disp['libres'] . ') para esas fechas.'
                : 'Hay parcelas disponibles para esas fechas.')
            : 'No quedan parcelas libres en esas fechas.';
    }

    api_ok([
        'tipo'          => $tipo,
        'entrada'       => $entrada,
        'salida'        => $salida,
        'noches'        => $res['noches_total'] ?? $noches,
        'total'         => round((float) ($res['total'] ?? 0), 2),
        'desglose'      => $res['desglose'] ?? [],
        'suplemento'    => (float) ($res['suplemento'] ?? 0),
        'temporada'     => etiqueta_temporada($entrada, $salida),
        'disponible'    => $disp['hay'],
        'disponibilidad' => $mensaje,
        'detalle'       => $detalle,
    ]);

} catch (Throwable $e) {
    api_error('No se pudo calcular el presupuesto en este momento.', 503, $e);
}
