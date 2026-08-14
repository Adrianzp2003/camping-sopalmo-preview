<?php
/**
 * Disponibilidad real, leída del gestor.
 *
 * Dos cosas que el código anterior hacía mal y aquí se corrigen:
 *
 *  1. Solo miraba `reservas`. Pero el PMS mueve la fila a `estancias`
 *     en el check-in, así que un cliente YA ALOJADO no contaba como
 *     ocupante y la web ofrecía parcelas que estaban llenas.
 *  2. La capacidad estaba a fuego (CAP_TOTAL = 28) cuando en el gestor
 *     hay 30 unidades. Ahora se cuenta de la tabla `unidades`.
 */
declare(strict_types=1);

/** Tablas donde puede vivir una ocupación vigente. */
const TABLAS_OCUPACION = ['reservas', 'estancias'];


/** Nº de parcelas activas según el gestor. */
function capacidad_parcelas(PDO $pdo): int
{
    $sql = "SELECT COUNT(*)
              FROM unidades u
              JOIN bloques b ON b.id = u.bloque_id
             WHERE b.codigo = 'parcelas'
               AND u.activo = 1
               AND b.activo = 1";
    $n = (int) $pdo->query($sql)->fetchColumn();
    return $n > 0 ? $n : 30;
}


/** Unidades rurales activas: ['cortijillo' => 'El Cortijillo', ...] */
function unidades_rurales(PDO $pdo): array
{
    $sql = "SELECT u.nombre
              FROM unidades u
              JOIN bloques b ON b.id = u.bloque_id
             WHERE b.codigo = 'casas_rurales'
               AND u.activo = 1
             ORDER BY u.id";
    $out = [];
    foreach ($pdo->query($sql)->fetchAll(PDO::FETCH_COLUMN) as $nombre) {
        $out[_slug_unidad((string) $nombre)] = (string) $nombre;
    }
    if (!$out) {
        $out = ['cortijillo' => 'El Cortijillo', 'mirador' => 'El Mirador de la Rambla'];
    }
    return $out;
}


function _slug_unidad(string $s): string
{
    $s = strtolower(strtr($s, [
        'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ñ' => 'n',
        'Á' => 'a', 'É' => 'e', 'Í' => 'i', 'Ó' => 'o', 'Ú' => 'u', 'Ñ' => 'n',
    ]));
    // strpos en vez de str_contains: el hosting corre PHP 7.4.
    if (strpos($s, 'cortijillo') !== false) return 'cortijillo';
    if (strpos($s, 'mirador')    !== false) return 'mirador';
    return preg_replace('/[^a-z0-9]+/', '_', $s) ?: 'unidad';
}


/**
 * Filas que solapan con [$desde, $hasta) en reservas + estancias.
 * Intervalo semiabierto: quien se va el día que otro entra no solapa.
 */
function _filas_solapadas(PDO $pdo, string $desde, string $hasta, ?string $tipo): array
{
    $filas = [];
    foreach (TABLAS_OCUPACION as $tabla) {
        $cond = "fecha_entrada < :hasta AND fecha_salida > :desde";
        $args = [':desde' => $desde, ':hasta' => $hasta];

        if ($tipo === 'camping') {
            $cond .= " AND (alojamiento_tipo IS NULL OR alojamiento_tipo IN ('camping','invierno'))";
        } elseif ($tipo !== null) {
            $cond .= " AND alojamiento_tipo = :tipo";
            $args[':tipo'] = $tipo;
        }

        $sql = "SELECT parcelas_csv, cupos_bloqueados, aloj_unidad, alojamiento_tipo
                  FROM `$tabla`
                 WHERE $cond";
        try {
            $st = $pdo->prepare($sql);
            $st->execute($args);
            foreach ($st->fetchAll() as $r) {
                $filas[] = $r;
            }
        } catch (PDOException $e) {
            // Si una tabla no existiera, seguimos con la otra en vez de caernos.
            continue;
        }
    }
    return $filas;
}


/** Ocupación del camping entre dos fechas. */
function ocupacion_camping(PDO $pdo, string $desde, string $hasta): array
{
    $capacidad = capacidad_parcelas($pdo);
    $ocupadas  = [];
    $cupos     = 0;

    foreach (_filas_solapadas($pdo, $desde, $hasta, 'camping') as $r) {
        $csv = trim((string) ($r['parcelas_csv'] ?? ''));
        if ($csv !== '') {
            foreach (explode(',', $csv) as $p) {
                $p = trim($p);
                if ($p !== '') {
                    $ocupadas[$p] = true;
                }
            }
        } else {
            // Reserva sin parcela asignada todavía: bloquea cupo.
            $cupos += max(0, (int) ($r['cupos_bloqueados'] ?? 0));
        }
    }

    $libres = max(0, $capacidad - count($ocupadas) - $cupos);

    return [
        'capacidad' => $capacidad,
        'ocupadas'  => count($ocupadas) + $cupos,
        'libres'    => $libres,
        'hay'       => $libres > 0,
    ];
}


/** Estado de cada casa rural entre dos fechas. */
function ocupacion_rural(PDO $pdo, string $desde, string $hasta): array
{
    $casas  = unidades_rurales($pdo);
    $estado = [];
    foreach ($casas as $slug => $nombre) {
        $estado[$slug] = ['nombre' => $nombre, 'libre' => true];
    }

    foreach (_filas_solapadas($pdo, $desde, $hasta, 'rural') as $r) {
        $slug = _slug_unidad(trim((string) ($r['aloj_unidad'] ?? '')));
        if (isset($estado[$slug])) {
            $estado[$slug]['libre'] = false;
        } else {
            // Sin unidad concreta: no sabemos cuál, bloqueamos por prudencia.
            foreach ($estado as $k => $_) {
                $estado[$k]['libre'] = false;
            }
        }
    }

    $libres = array_filter($estado, static fn($c) => $c['libre']);

    return [
        'casas'  => $estado,
        'libres' => count($libres),
        'hay'    => count($libres) > 0,
    ];
}
