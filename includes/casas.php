<?php
/**
 * Datos de las dos casas rurales.
 *
 * Las descripciones de las fotos no son decorativas: son el texto alternativo
 * que lee un buscador y un lector de pantalla, y el pie que se muestra en el
 * visor a pantalla completa. Están escritas mirando cada foto una por una.
 */
declare(strict_types=1);

function casas_datos(): array
{
    return [
        'cortijillo' => [
            'slug'      => 'cortijillo',
            'nombre'    => 'El Cortijillo',
            'titular'   => 'El Cortijillo',
            'lema'      => 'Una casa encalada con la sierra por delante',
            'capacidad' => 5,
            'dormitorios' => 2,
            'banos'     => 1,
            'entradilla' => 'Construcción típica de la zona, encalada en blanco, '
                          . 'con terraza propia mirando a la Sierra Cabrera. Dos '
                          . 'dormitorios, chimenea y cocina completa.',
            'descripcion' => [
                'El Cortijillo es la más tradicional de las dos: paredes gruesas '
                . 'encaladas, vigas de madera a la vista y una terraza a la que da '
                . 'sombra una parra. Desde ella se ve la sierra y, al fondo, el mar.',
                'Dentro tiene dos dormitorios —uno con cama doble y otro con dos '
                . 'individuales—, salón con chimenea y sofá cama, y cocina '
                . 'independiente totalmente equipada. Aire acondicionado en el salón '
                . 'y en los dormitorios.',
            ],
            'equipamiento' => [
                'Dormitorios'  => ['Dormitorio con cama de matrimonio', 'Dormitorio con dos camas individuales', 'Sofá cama en el salón', 'Ropa de cama y toallas incluidas'],
                'Cocina'       => ['Cocina independiente', 'Horno y microondas', 'Frigorífico grande', 'Menaje completo', 'Lavavajillas'],
                'Salón'        => ['Chimenea de leña', 'Televisión', 'Aire acondicionado frío/calor', 'WiFi'],
                'Exterior'     => ['Terraza con parra y mesa', 'Vistas a la Sierra Cabrera', 'Zona de aparcamiento propia', 'Tendedero'],
            ],
            'portada' => 'cortijillo/cortijillo-00',
            'fotos' => [
                ['cortijillo/cortijillo-00', 'Terraza del Cortijillo con mesa y sillas de madera, mirando a la sierra'],
                ['cortijillo/cortijillo-14', 'Terraza cubierta por una parra, con vistas al valle'],
                ['cortijillo/cortijillo-01', 'Fachada encalada del Cortijillo entre pinos'],
                ['cortijillo/cortijillo-02', 'Patio de entrada con reja azul y plantas'],
                ['cortijillo/cortijillo-03', 'Detalle de la terraza con geranios'],
                ['cortijillo/cortijillo-05', 'Salón comedor con ventana grande y vistas al exterior'],
                ['cortijillo/cortijillo-04', 'Salón con sofá y aire acondicionado'],
                ['cortijillo/cortijillo-08', 'Comedor con chimenea de leña'],
                ['cortijillo/cortijillo-06', 'Mesa de comedor puesta junto a la ventana'],
                ['cortijillo/cortijillo-07', 'Cocina comedor con frigorífico y zona de trabajo'],
                ['cortijillo/cortijillo-09', 'Cocina equipada con horno, microondas y frigorífico'],
                ['cortijillo/cortijillo-10', 'Detalle de la encimera y los armarios de la cocina'],
                ['cortijillo/cortijillo-11', 'Dormitorio principal con cama de matrimonio'],
                ['cortijillo/cortijillo-12', 'Segundo dormitorio con dos camas individuales'],
                ['cortijillo/bano',          'Cuarto de baño con plato de ducha y mampara'],
            ],
        ],

        'mirador' => [
            'slug'      => 'mirador',
            'nombre'    => 'El Mirador de la Rambla',
            'titular'   => 'El Mirador<br>de la Rambla',
            'lema'      => 'Un espacio diáfano abierto a la rambla',
            'capacidad' => 5,
            'dormitorios' => 1,
            'banos'     => 1,
            'entradilla' => 'Casa de planta amplia y diáfana, con un dormitorio muy '
                          . 'espacioso, terraza a la sombra y salón-cocina abierto. '
                          . 'Pensada para una familia.',
            'descripcion' => [
                'El Mirador tiene otra lógica: en vez de repartir en cuartos '
                . 'pequeños, aprovecha un dormitorio grande donde caben la cama de '
                . 'matrimonio y dos individuales sin agobio. Funciona muy bien para '
                . 'familias con niños.',
                'El salón-cocina es un espacio único y luminoso, con salida directa a '
                . 'la terraza cubierta. El baño tiene bañera, que en un alojamiento '
                . 'rural con niños se agradece.',
            ],
            'equipamiento' => [
                'Dormitorios'  => ['Dormitorio amplio con cama de matrimonio', 'Dos camas individuales en el mismo espacio', 'Sofá cama en el salón', 'Ropa de cama y toallas incluidas'],
                'Cocina'       => ['Cocina abierta al salón', 'Horno y microondas', 'Frigorífico', 'Menaje completo'],
                'Salón'        => ['Chimenea', 'Televisión', 'Aire acondicionado frío/calor', 'WiFi'],
                'Exterior'     => ['Terraza cubierta con mesa', 'Zona ajardinada', 'Aparcamiento propio'],
            ],
            'portada' => 'mirador/mirador-1',
            'fotos' => [
                ['mirador/mirador-1',  'Terraza del Mirador con mesa y sillas a la sombra'],
                ['mirador/mirador-2',  'Salón comedor con mesa, televisión y sofá'],
                ['mirador/mirador-3',  'El salón desde el otro extremo, con la mesa junto a la ventana'],
                ['mirador/mirador-5',  'Cocina equipada con frigorífico, microondas y horno'],
                ['mirador/mirador-6',  'Dormitorio con cama de matrimonio'],
                ['mirador/mirador-7',  'Dormitorio visto desde la entrada'],
                ['mirador/mirador-4',  'Cuarto de baño con bañera'],
                ['mirador/mirador-8',  'Detalle del alojamiento'],
                ['mirador/mirador-11', 'Exterior de la casa'],
            ],
        ],
    ];
}

/** Devuelve una casa por su slug, o null. */
function casa_por_slug(?string $slug): ?array
{
    $c = casas_datos();
    return $slug !== null && isset($c[$slug]) ? $c[$slug] : null;
}

/**
 * Construye el srcset de una foto a partir de los WebP que existan.
 * Evita pedir al navegador un tamaño que no generamos.
 */
function foto_srcset(string $base, array $anchos = [1600, 900, 500]): array
{
    $raiz = __DIR__ . '/../fotos/';
    $src = '';
    $set = [];
    foreach ($anchos as $w) {
        $rel = "fotos/$base-$w.webp";
        if (is_file($raiz . "$base-$w.webp")) {
            $set[] = "$rel {$w}w";
            if ($src === '' || $w <= 900) {
                $src = $rel;
            }
        }
    }
    if (!$set) {
        // por si el original era más pequeño que todos los anchos pedidos
        foreach (glob($raiz . $base . '-*.webp') as $f) {
            $rel = 'fotos/' . basename(dirname($f)) . '/' . basename($f);
            $set[] = $rel . ' ' . (int) preg_replace('/.*-(\d+)\.webp$/', '$1', $f) . 'w';
            $src = $rel;
        }
    }
    return ['src' => $src, 'srcset' => implode(', ', $set)];
}
