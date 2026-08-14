<?php
declare(strict_types=1);

/**
 * precios_helper.php
 * - Camping: calcula por segmentos (alta/baja) usando tarifas_temporada.
 * - Casas (rural): semanas + noches sueltas con mínimos, temporada automática (jul-ago + SS).
 * - Invierno larga estancia: por tramos (tabla tarifas_invierno).
 *
 * Tablas usadas:
 *  - tarifas_temporada(temporada ENUM('alta','baja'), concepto, precio)
 *  - tarifas_rural(temporada ENUM('alta','baja'), tipo ENUM('noche','semana'), pax INT, precio DECIMAL, min_noches INT)
 *  - tarifas_invierno(dias_min INT, dias_max INT NULL, precio_noche DECIMAL)
 */

/* ===================== Fechas / utilidades ===================== */

function _dt(string $ymd): DateTimeImmutable
{
    return new DateTimeImmutable($ymd);
}

/** noches = días entre [entrada, salida) (salida excluida) */
function noches_entre(string $entrada, string $salida): int
{
    $d1 = _dt($entrada);
    $d2 = _dt($salida);
    return max(0, (int) $d1->diff($d2)->days);
}

/** Easter Sunday (domingo de resurrección) en Y-m-d */
function pascua_ymd(int $year): string
{
    return date('Y-m-d', easter_date($year));
}

/** Rango de Semana Santa: Domingo de Ramos (Pascua - 7 días) … Lunes de Pascua (+1 día) exclusivo */
function rango_semana_santa(int $year): array
{
    $easter = pascua_ymd($year); // Domingo de Resurrección
    $inicio = (new DateTimeImmutable($easter))->modify('-7 days')->format('Y-m-d'); // Domingo de Ramos
    $fin_excl = (new DateTimeImmutable($easter))->modify('+1 day')->format('Y-m-d'); // exclusivo
    return [$inicio, $fin_excl];
}

/** Rango Alta de verano: [Y-07-01, Y-09-01) (fin exclusivo para contar noches) */
function rango_verano(int $year): array
{
    return (["$year-07-01", "$year-09-01"]);
}

/** Intersección de dos rangos [a1,a2) ∩ [b1,b2) => [ini, fin) o null si vacío */
function intersectar_rangos(string $a1, string $a2, string $b1, string $b2): ?array
{
    $ini = max($a1, $b1);
    $fin = min($a2, $b2);
    if ($ini < $fin)
        return [$ini, $fin];
    return null;
}

/**
 * Segmenta [entrada, salida) en alta/baja:
 * [
 *   ['temporada'=>'alta'|'baja','desde'=>Y-m-d,'hasta'=>Y-m-d,'noches'=>N],
 *   ...
 * ]
 */
function segmentar_por_temporada(string $entrada, string $salida): array
{
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $entrada) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $salida) || $salida <= $entrada) {
        return [];
    }

    $y_from = (int) substr($entrada, 0, 4);
    $y_to = (int) substr($salida, 0, 4);

    $alta_segmentos = [];
    for ($y = $y_from; $y <= $y_to; $y++) {
        // Verano
        [$v_ini, $v_fin] = rango_verano($y);
        $x = intersectar_rangos($entrada, $salida, $v_ini, $v_fin);
        if ($x)
            $alta_segmentos[] = $x;

        // Semana Santa
        [$ss_ini, $ss_fin] = rango_semana_santa($y);
        $x2 = intersectar_rangos($entrada, $salida, $ss_ini, $ss_fin);
        if ($x2)
            $alta_segmentos[] = $x2;
    }

    // Unir solapes/contiguos
    usort($alta_segmentos, fn($a, $b) => strcmp($a[0], $b[0]));
    $alta_merge = [];
    foreach ($alta_segmentos as $seg) {
        if (!$alta_merge) {
            $alta_merge[] = $seg;
            continue;
        }
        $last =& $alta_merge[count($alta_merge) - 1];
        if ($seg[0] <= $last[1]) {
            $last[1] = max($last[1], $seg[1]);
        } else {
            $alta_merge[] = $seg;
        }
    }

    $segments = [];
    $cursor = $entrada;
    foreach ($alta_merge as [$a, $b]) {
        if ($cursor < $a) { // baja previa
            $n = noches_entre($cursor, $a);
            if ($n > 0)
                $segments[] = ['temporada' => 'baja', 'desde' => $cursor, 'hasta' => $a, 'noches' => $n];
        }
        $n = noches_entre($a, $b);
        if ($n > 0)
            $segments[] = ['temporada' => 'alta', 'desde' => $a, 'hasta' => $b, 'noches' => $n];
        $cursor = $b;
    }
    if ($cursor < $salida) {
        $n = noches_entre($cursor, $salida);
        if ($n > 0)
            $segments[] = ['temporada' => 'baja', 'desde' => $cursor, 'hasta' => $salida, 'noches' => $n];
    }

    return $segments;
}

/** Resumen simple: alta/baja/noches totales */
function resumen_noches_temporadas(string $entrada, string $salida): array
{
    $segs = segmentar_por_temporada($entrada, $salida);
    $alta = 0;
    $baja = 0;
    foreach ($segs as $s) {
        if ($s['temporada'] === 'alta')
            $alta += $s['noches'];
        else
            $baja += $s['noches'];
    }
    return ['alta' => $alta, 'baja' => $baja, 'total' => $alta + $baja, 'segmentos' => $segs];
}

/** Etiqueta simple para UI: 'alta' | 'baja' | 'mixta' (según fechas) */
function etiqueta_temporada(string $entrada, string $salida): string
{
    $r = resumen_noches_temporadas($entrada, $salida);
    if ($r['alta'] > 0 && $r['baja'] > 0)
        return 'mixta';
    if ($r['alta'] > 0)
        return 'alta';
    return 'baja';
}

/* ===================== Tarifas CAMPING ===================== */

/** Carga tarifas de BD y mezcla con defaults */
function cargar_tarifas(?PDO $pdo): array
{
    $out = tarifas_por_defecto();
    if (!$pdo)
        return $out;
    try {
        $st = $pdo->query("SELECT temporada, concepto, precio FROM tarifas_temporada");
        if ($st) {
            while ($r = $st->fetch(PDO::FETCH_ASSOC)) {
                $t = $r['temporada'];
                if ($t !== 'alta' && $t !== 'baja')
                    continue;
                $out[$t][$r['concepto']] = (float) $r['precio'];
            }
        }
    } catch (Exception $e) {
        // Si falla la BD, usamos defaults
    }
    return $out;
}

/** Valores por defecto (fallback) */
function tarifas_por_defecto(): array
{
    // Precios base estimados
    $base = [
        'parcela' => 15.00,
        'adulto' => 6.00,
        'nino' => 4.00,
        'perro' => 3.00,
        'electricidad' => 5.00,
        'tienda_extra' => 5.00,
        'caravana_extra' => 10.00,
        'autocaravana_extra' => 12.00,
        'coche_extra' => 5.00,
        'moto_extra' => 3.00,
        'frigorifico_extra' => 5.00
    ];

    // Alta un poco más cara
    $alta = array_map(fn($v) => $v * 1.2, $base);

    return ['alta' => $alta, 'baja' => $base];
}

/**
 * Camping: total por segmentos.
 * $in = ['entrada','salida','adultos','ninos','perros','electricidad'(0|1),
 *        'tienda_extra','caravana_extra','autocaravana_extra','coche_extra','moto_extra','frigorifico_extra',
 *        'parcelas_count' (>=1)]
 */
function calcular_total_reserva(array $in, ?PDO $pdo = null): array
{
    $entrada = (string) ($in['entrada'] ?? '');
    $salida = (string) ($in['salida'] ?? '');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $entrada) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $salida) || $salida <= $entrada) {
        return ['noches_total' => 0, 'noches_alta' => 0, 'noches_baja' => 0, 'desglose' => [], 'suplemento' => 0.0, 'total' => 0.0];
    }

    $seg = resumen_noches_temporadas($entrada, $salida);
    $segs = $seg['segmentos'];
    $noches_tot = $seg['total'];
    $noches_alta = $seg['alta'];
    $noches_baja = $seg['baja'];

    $tar = $pdo ? cargar_tarifas($pdo) : tarifas_por_defecto();

    $adultos = (int) ($in['adultos'] ?? 0);
    $ninos = (int) ($in['ninos'] ?? 0);
    $perros = (int) ($in['perros'] ?? 0);
    $elec = (int) ($in['electricidad'] ?? 0) ? 1 : 0;

    $tienda = (int) ($in['tienda_extra'] ?? 0);
    $carav = (int) ($in['caravana_extra'] ?? 0);
    $auto = (int) ($in['autocaravana_extra'] ?? 0);
    $coche = (int) ($in['coche_extra'] ?? 0);
    $moto = (int) ($in['moto_extra'] ?? 0);
    $frigo = (int) ($in['frigorifico_extra'] ?? 0);
    $parcelas = max(1, (int) ($in['parcelas_count'] ?? 1));

    $desglose = [];
    $total = 0.0;

    foreach ($segs as $s) {
        $t = $s['temporada']; // 'alta'|'baja'
        $px = $tar[$t] ?? [];

        $por_noche =
            ($parcelas * ($px['parcela'] ?? 0.0)) +
            ($adultos * ($px['adulto'] ?? 0.0)) +
            ($ninos * ($px['nino'] ?? 0.0)) +
            ($perros * ($px['perro'] ?? 0.0)) +
            ($elec ? ($px['electricidad'] ?? 0.0) : 0.0) +
            ($tienda * ($px['tienda_extra'] ?? 0.0)) +
            ($carav * ($px['caravana_extra'] ?? 0.0)) +
            ($auto * ($px['autocaravana_extra'] ?? 0.0)) +
            ($coche * ($px['coche_extra'] ?? 0.0)) +
            ($moto * ($px['moto_extra'] ?? 0.0)) +
            ($frigo * ($px['frigorifico_extra'] ?? 0.0));

        $sub = $por_noche * $s['noches'];
        $total += $sub;

        $desglose[] = [
            'temporada' => $t,
            'desde' => $s['desde'],
            'hasta' => $s['hasta'],
            'noches' => $s['noches'],
            'por_noche' => round($por_noche, 2),
            'subtotal' => round($sub, 2),
        ];
    }

    // Suplemento 1 noche (solo camping)
    $supl = ($noches_tot === 1) ? 5.0 : 0.0;
    $total += $supl;

    return [
        'ok' => true,
        'noches_total' => $noches_tot,
        'noches_alta' => $noches_alta,
        'noches_baja' => $noches_baja,
        'desglose' => $desglose,
        'suplemento' => $supl,
        'total' => round($total, 2),
    ];
}

/* ======== Descuentos y pendiente comunes (UI) ======== */

// Descuento aplicado sobre un total base.
function calcular_descuento_aplicado(float $total_base, ?string $tipo, $valor): float
{
    if ($total_base <= 0)
        return 0.0;
    $tipo = $tipo ? strtolower(trim($tipo)) : '';
    $valor = is_null($valor) ? 0 : (float) $valor;

    if ($tipo === 'pct') {
        $valor = max(0.0, min(100.0, $valor));
        return round($total_base * ($valor / 100.0), 2);
    }
    if ($tipo === 'importe') {
        return round(max(0.0, min($total_base, $valor)), 2);
    }
    return 0.0;
}

// Total pendiente (a mostrar), sin modificar precio_total guardado.
function calcular_pendiente(float $total_base, ?string $tipo, $valor, float $prepago): float
{
    $dto = calcular_descuento_aplicado($total_base, $tipo, $valor);
    $pendiente = $total_base - $dto - max(0.0, $prepago);
    return round(max(0.0, $pendiente), 2);
}

/* ===================== Utilidades de BD ===================== */

function precio_concepto(?PDO $pdo, string $concepto, string $temporada = 'baja'): float
{
    if (!$pdo) {
        $defaults = tarifas_por_defecto();
        return $defaults[$temporada][$concepto] ?? 0.0;
    }
    $q = $pdo->prepare("SELECT precio FROM tarifas_temporada WHERE concepto = ? AND temporada = ? LIMIT 1");
    $q->execute([$concepto, $temporada]);
    $row = $q->fetch(PDO::FETCH_ASSOC);
    return $row ? (float) $row['precio'] : 0.0;
}

/* ===================== CASAS (alojamiento_tipo = 'rural') ===================== */

/** Fallback de precios (por si no existe/está vacía la tabla tarifas_rural) */
function rural_fallback_matrix(): array
{
    return [
        'baja' => [
            'noche' => ['min' => 2, 'p' => [2 => 70.00, 3 => 70.00, 4 => 80.00, 5 => 90.00]],
            'semana' => ['min' => 7, 'p' => [2 => 400.00, 3 => 400.00, 4 => 450.00, 5 => 500.00]],
        ],
        'alta' => [
            'noche' => ['min' => 1, 'p' => [2 => 80.00, 3 => 90.00, 4 => 100.00, 5 => 110.00]],
            'semana' => ['min' => 7, 'p' => [2 => 550.00, 3 => 610.00, 4 => 680.00, 5 => 750.00]],
        ],
    ];
}

/** Lee de BD tarifas_rural; si no hay fila (o no existe tabla), usa fallback */
function rural_tarifa(?PDO $pdo, string $temporada, string $tipo, int $pax): ?array
{
    if ($pdo) {
        try {
            $q = $pdo->prepare("SELECT precio, min_noches FROM tarifas_rural WHERE temporada=? AND tipo=? AND pax=? LIMIT 1");
            $q->execute([$temporada, $tipo, $pax]);
            if ($row = $q->fetch(PDO::FETCH_ASSOC)) {
                $min = isset($row['min_noches']) ? (int) $row['min_noches'] : ($tipo === 'semana' ? 7 : ($temporada === 'baja' ? 2 : 1));
                return ['precio' => (float) $row['precio'], 'min' => $min];
            }
        } catch (Throwable $e) {
            // ignorar y usar fallback
        }
    }
    $m = rural_fallback_matrix();
    return isset($m[$temporada][$tipo]['p'][$pax])
        ? ['precio' => (float) $m[$temporada][$tipo]['p'][$pax], 'min' => (int) $m[$temporada][$tipo]['min']]
        : null;
}

/** Temporada para casas: alta si hay cualquier noche en alta/SS, si no baja */
function infer_temporada_rural(string $entrada, string $salida): string
{
    $r = resumen_noches_temporadas($entrada, $salida);
    return ($r['alta'] > 0) ? 'alta' : 'baja';
}

/**
 * Calcula precio de casas combinando semanas + noches sueltas.
 * - Si hay ≥1 semana, el resto puede ser < mínimo de noches sueltas (ej. 7n + 1n en baja ⇒ 400 + 70).
 * - Si NO hay tarifa por noche o no cumple mínimos y hay tarifa semanal, se suma 1 semana.
 *
 * @param ?string $temporada Puede ser null ⇒ se infiere de las fechas.
 */
function calcular_precio_rural(?PDO $pdo, string $entrada, string $salida, int $personas, ?string $temporada = null): array
{
    $noches = noches_entre($entrada, $salida);
    if ($noches <= 0) {
        return ['ok' => false, 'msg' => 'Fechas inválidas', 'total' => 0];
    }
    $pax = max(2, min(5, (int) $personas));
    $temporada = $temporada ?: infer_temporada_rural($entrada, $salida);

    $N = rural_tarifa($pdo, $temporada, 'noche', $pax);
    $S = rural_tarifa($pdo, $temporada, 'semana', $pax);
    if (!$N && !$S)
        return ['ok' => false, 'msg' => 'No hay tarifas definidas para casas', 'total' => 0];

    $semanas = 0;
    $resto = $noches;
    $total = 0.0;
    $detalle = [];

    if ($S && $noches >= 7) {
        $semanas = intdiv($noches, 7);
        $resto = $noches - $semanas * 7;
        if ($semanas > 0) {
            $imp = $semanas * (float) $S['precio'];
            $total += $imp;
            $detalle[] = ["concepto" => "Semana (x{$semanas})", "ud" => $semanas, "pu" => round((float) $S['precio'], 2), "importe" => round($imp, 2)];
        }
    }

    if ($resto > 0) {
        if ($N) {
            $min_resto = ($semanas > 0) ? 1 : max(1, (int) $N['min']);
            if ($resto < $min_resto) {
                // sin cumplir mínimo y hay semanal → sumamos 1 semana
                if ($S) {
                    $total += (float) $S['precio'];
                    $detalle[] = ["concepto" => "Ajuste a 1 semana (mínimos)", "ud" => 1, "pu" => round((float) $S['precio'], 2), "importe" => round((float) $S['precio'], 2)];
                    $resto = 0;
                } else {
                    return ['ok' => false, 'msg' => "Mínimo por noche en {$temporada}: {$N['min']}", "total" => 0];
                }
            } else {
                $imp = $resto * (float) $N['precio'];
                $total += $imp;
                $detalle[] = ["concepto" => "Noches sueltas (x{$resto})", "ud" => $resto, "pu" => round((float) $N['precio'], 2), "importe" => round($imp, 2)];
            }
        } else {
            // no hay tarifa por noche → 1 semana más si existe
            if ($S) {
                $total += (float) $S['precio'];
                $detalle[] = ["concepto" => "Ajuste a 1 semana (sin tarifa por noche)", "ud" => 1, "pu" => round((float) $S['precio'], 2), "importe" => round((float) $S['precio'], 2)];
                $resto = 0;
            } else {
                return ['ok' => false, 'msg' => 'No existe tarifa por noche ni por semana', 'total' => 0];
            }
        }
    }

    return [
        'ok' => true,
        'tipo' => 'rural',
        'noches' => $noches,
        'temporada' => $temporada,
        'pax' => $pax,
        'total' => round($total, 2),
        'detalle' => $detalle,
        'semanas' => $semanas,
        'resto_noches' => $resto
    ];
}

/* ============ CAMPING - INVIERNO LARGA ESTANCIA (especial) ============ */

function precio_noche_invierno(?PDO $pdo, int $noches): float
{
    if (!$pdo) {
        // Fallback invierno
        if ($noches >= 90)
            return 12.0;
        if ($noches >= 30)
            return 14.0;
        return 0.0; // Menos de 30 noches no es larga estancia invierno
    }
    $q = $pdo->query("SELECT dias_min, dias_max, precio_noche FROM tarifas_invierno ORDER BY dias_min ASC");
    $precio = 0.0;
    while ($row = $q->fetch(PDO::FETCH_ASSOC)) {
        $min = (int) $row['dias_min'];
        $max = is_null($row['dias_max']) ? PHP_INT_MAX : (int) $row['dias_max'];
        if ($noches >= $min && $noches <= $max) {
            $precio = (float) $row['precio_noche'];
            break;
        }
    }
    return $precio;
}

/**
 * INVIERNO: base incluye 2 personas + parcela.
 * Extras (adultos extra, niños, perros, electricidad) a precio temporada 'baja' (cámbialo si quieres).
 */
function calcular_precio_camping_invierno(?PDO $pdo, string $entrada, string $salida, int $adultos, int $ninos, int $perros, int $electricidad, string $temporada_extras = 'baja'): array
{
    $noches = noches_entre($entrada, $salida);
    if ($noches <= 0) {
        return ['ok' => false, 'msg' => 'Fechas inválidas', 'total' => 0];
    }

    $pu_base = precio_noche_invierno($pdo, $noches);
    if ($pu_base <= 0) {
        return ['ok' => false, 'msg' => 'No hay tarifa de invierno para ese rango', 'total' => 0];
    }

    $detalle = [];
    $total = 0.0;

    // Base (incluye 2 personas + parcela)
    $imp_base = $noches * $pu_base;
    $total += $imp_base;
    $detalle[] = ["concepto" => "Base invierno (incluye parcela + 2 personas)", "ud" => $noches, "pu" => round($pu_base, 2), "importe" => round($imp_base, 2)];

    // Extras
    $adultos_extra = max(0, $adultos - 2);
    if ($adultos_extra > 0) {
        $pu_adulto = precio_concepto($pdo, 'adulto', $temporada_extras);
        $imp = $adultos_extra * $noches * $pu_adulto;
        $total += $imp;
        $detalle[] = ["concepto" => "Adultos extra", "ud" => "{$adultos_extra} x {$noches}n", "pu" => round($pu_adulto, 2), "importe" => round($imp, 2)];
    }

    if ($ninos > 0) {
        $pu_nino = precio_concepto($pdo, 'nino', $temporada_extras);
        $imp = $ninos * $noches * $pu_nino;
        $total += $imp;
        $detalle[] = ["concepto" => "Niños", "ud" => "{$ninos} x {$noches}n", "pu" => round($pu_nino, 2), "importe" => round($imp, 2)];
    }

    if ($perros > 0) {
        $pu_perro = precio_concepto($pdo, 'perro', $temporada_extras);
        $imp = $perros * $noches * $pu_perro;
        $total += $imp;
        $detalle[] = ["concepto" => "Perros", "ud" => "{$perros} x {$noches}n", "pu" => round($pu_perro, 2), "importe" => round($imp, 2)];
    }

    if ((int) $electricidad === 1) {
        $pu_elec = precio_concepto($pdo, 'electricidad', $temporada_extras);
        $imp = $noches * $pu_elec;
        $total += $imp;
        $detalle[] = ["concepto" => "Electricidad", "ud" => $noches, "pu" => round($pu_elec, 2), "importe" => round($imp, 2)];
    }

    return [
        'ok' => true,
        'tipo' => 'camping_invierno',
        'noches' => $noches,
        'total' => round($total, 2),
        'detalle' => $detalle
    ];
}

/* ==================== ENRUTADOR GENERAL ==================== */
/**
 * Uso desde formularios:
 *  - CASAS: ['alojamiento_tipo'=>'rural','entrada'=>Y-m-d,'salida'=>Y-m-d,'personas'=>N, 'temporada'=>null|alta|baja]
 *  - CAMPING + INVIERNO: ['alojamiento_tipo'=>'camping','invierno_larga'=>true, 'adultos'=>N,'ninos'=>N,'perros'=>N,'electricidad'=>0|1,'entrada'=>...,'salida'=>...]
 *  - CAMPING normal: usa calcular_total_reserva() directamente (esto no lo rompe).
 */
function calcular_precio_reserva(?PDO $pdo, array $p): array
{
    if (($p['alojamiento_tipo'] ?? '') === 'rural') {
        return calcular_precio_rural(
            $pdo,
            $p['entrada'],
            $p['salida'],
            (int) ($p['personas'] ?? $p['adultos'] ?? 2),
            $p['temporada'] ?? null // null ⇒ se infiere por fechas
        );
    }

    if (($p['alojamiento_tipo'] ?? '') === 'camping') {
        if (!empty($p['invierno_larga'])) {
            return calcular_precio_camping_invierno(
                $pdo,
                $p['entrada'],
                $p['salida'],
                (int) ($p['adultos'] ?? 0),
                (int) ($p['ninos'] ?? 0),
                (int) ($p['perros'] ?? 0),
                (int) ($p['electricidad'] ?? 0),
                'baja' // extras a precio 'baja'
            );
        } else {
            // Camping estándar
            return calcular_total_reserva($p, $pdo);
        }
    }

    return ['ok' => false, 'msg' => 'Tipo de alojamiento no reconocido.', 'total' => 0];
}
