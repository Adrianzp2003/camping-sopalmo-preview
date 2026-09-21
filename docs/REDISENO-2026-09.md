# Camping Sopalmo · Estudio y rediseño de la web

21 de septiembre de 2026

## Resultado

Rediseño de la portada y de las fichas de El Cortijillo y El Mirador, con revisión de la presentación de privacidad y condiciones. Preparado para el repositorio `Adrianzp2003/camping-sopalmo-preview`. La web de producción y el PMS se mantienen en su estado anterior durante esta fase de pruebas.

La propuesta usa una identidad cálida de tonos arena, verde y terracota, tipografía legible, fotografía real y llamadas a consultar la estancia. El objetivo es aumentar las consultas cualificadas; una mejora de ventas solo podrá confirmarse midiendo resultados después del lanzamiento.

## Qué detecté y qué he cambiado

| Prioridad | Situación anterior | Solución en la prueba |
|---|---|---|
| Alta | Introducción de vídeo y muchas tomas similares del dron antes de llegar a los alojamientos. | Portada directa con fotografía reciente y acceso inmediato a la consulta. Se eliminan vídeos y tomas aéreas de las páginas visibles. |
| Alta | Enlaces de WhatsApp al fijo `950 47 84 13`, mientras se mostraba `660 73 53 68`. | Enlaces y mensajes preparados dirigidos al móvil anunciado, `660 73 53 68`. Conviene verificar la recepción de un mensaje real con el propietario antes de producción; no he enviado mensajes. |
| Alta | La prueba estática prometía precio y disponibilidad en tiempo real, pero dependía de PHP, que GitHub Pages no ejecuta. | Formulario de consulta funcional en el navegador, con revisión del mensaje y apertura de WhatsApp o correo. Nunca muestra disponibilidad inventada ni confirma una reserva. |
| Alta | El repositorio de pruebas anunciaba 30 parcelas; la producción y su API indican 28. | Se unifica el contenido a 28 parcelas y dos casas. |
| Alta | Había que recorrer mucho contenido para entender las opciones de alojamiento. | Tres fichas visibles: parcelas, El Cortijillo y El Mirador, con fotografías, capacidad y acceso directo. |
| Media | La portada móvil mezclaba la navegación con el titular y dependía de efectos de entrada. | Cabecera separada, menú desplegable, botones de contacto fijos en móvil y contenido visible sin animaciones obligatorias. |
| Media | Se repetían escenarios y se ampliaban imágenes antiguas en superficies grandes. | Nueve fotografías nuevas del usuario y una selección más breve de las casas. Portadas de casas en columnas, sin estirar sus fotos antiguas a todo el ancho. |
| Media | Afirmaciones genéricas como «todo a 20 minutos», «320 días de sol» o «ni vecinos a dos metros». | Se sustituyen por información concreta y prudente, sin distancias o ventajas no comprobadas. |
| Media | Los precios hablaban del sistema de gestión y no ayudaban a entender qué incluían. | Ejemplos de parcela con dos adultos, casa para dos personas y larga estancia, con exclusiones, mínimos y tablas completas. |
| Media | Dudas importantes dispersas. | Preguntas frecuentes sobre reserva, playa, mascotas, horarios, extras, accesos y ocupación. |
| Media | La copia de pruebas era indexable. | `noindex, nofollow` en las cinco páginas y exclusión del rastreo en `robots.txt`. |

## Selección de fotografías

Se han revisado las 24 fotos nuevas de la carpeta principal, además de las imágenes existentes de las casas. Se emplean estas nueve:

| Original | Destino |
|---|---|
| caravanas abajo1.jpg | Portada: parcelas y sierra. |
| parcelas arriba.jpg | Ficha de parcelas. |
| vista general zona baja.jpg | Vida en el camping. |
| alagrrobos.jpg | Galería de vegetación y sombra. |
| aseos.jpg | Galería de instalaciones. |
| lavadoras (2).jpg | Galería de lavandería. |
| escaleras para zona abajo.jpg | Galería y explicación de desniveles. |
| parcela estetica.jpg | Estancias largas y ambiente de noche. |
| entrada camping.jpg | Contacto y llegada. |

Las imágenes similares de caravanas y lavandería se reducen a una elección representativa. Las fotografías de menor utilidad comercial o encuadre repetido quedan fuera de las páginas. Los originales de la carpeta del usuario permanecen intactos.

Se generan 19 archivos WebP en distintos tamaños, sin ampliar los originales ni alterar el contenido de las fotografías. Se corrige su orientación y se omiten los metadatos EXIF en las copias web. Los nueve originales suman 35,64 MB; las 19 variantes, 3,57 MB en total, aproximadamente un 90 % menos. El navegador carga el tamaño adecuado, no todas las variantes.

La portada pasa de 24 elementos de imagen a 12. No se usan vídeos, tipografías remotas, bibliotecas ni mapas incrustados. Las fotos inferiores se cargan de forma diferida. La imagen principal tiene prioridad y todas las imágenes de contenido declaran sus dimensiones. No se ha medido una puntuación Lighthouse ni se afirma un tiempo de carga real universal.

## Contenido y recorrido hacia la reserva

1. Propuesta clara: camping familiar de 28 parcelas y dos casas en Sopalmo, Mojácar.
2. Elegir alojamiento mediante fichas visuales.
3. Conocer ambiente, instalaciones, desniveles y política de mascotas.
4. Entender precios y costes adicionales.
5. Resolver preguntas frecuentes.
6. Introducir fechas y ocupantes; revisar el mensaje preparado.
7. Abrir WhatsApp o correo, o llamar directamente.

Se especifica que el camping está a unos 4 km del Mediterráneo y fuera del parque natural, para ajustar expectativas. Se conserva la información de equipos y capacidad de las casas del proyecto original; no se han inventado reseñas, descuentos, urgencia, disponibilidad ni servicios nuevos.

## Precios

Fuente: [API pública de tarifas del camping](https://campingsopalmo.com/api/tarifas.php), consultada el 21/09/2026. La copia de pruebas contiene una referencia fechada y no una sincronización automática.

- Parcela y dos adultos: 25 €/noche en baja, electricidad aparte. Una sola noche tiene suplemento de 5 €.
- Casa completa para dos personas: 80 €/noche en baja, mínimo dos noches.
- Larga estancia de invierno: 12 €/noche para el tramo de 91 a 365 noches, parcela y dos personas, electricidad aparte.
- Se incluyen todos los conceptos de camping, las cuatro ocupaciones de casas y las tarifas semanales y de invierno.

En la web pública había una etiqueta «más de 91 noches»; la API establece que el tramo empieza en 91. La prueba sigue el dato de la API.

## Comprobaciones realizadas

- Cinco páginas revisadas en navegador a 360, 390, 768, 1024 y 1440 píxeles de ancho: 25 combinaciones, sin desbordamiento horizontal de la página.
- Inspección visual de portada móvil y escritorio, casas y tarjetas de tablet. Las tablas anchas mantienen desplazamiento dentro de su propio contenedor.
- Menú móvil: apertura, cierre al seleccionar una sección y cierre con Escape.
- Formulario: preparación de una consulta con fechas y siete noches; enlace de WhatsApp y correo correctamente codificado.
- Máximo de cinco personas en casas; rechazo de seis. Mínimo de dos noches en casas; una noche se invalida.
- Al cambiar datos se oculta el resumen anterior para evitar enviarlo desactualizado.
- Galerías: apertura, cierre y devolución del foco. Las fotos mantienen enlace directo si no hay JavaScript.
- 39 recursos locales comprobados mediante HTTP, referencias de imágenes, enlaces internos y anclas sin errores.
- Lychee: 205 referencias, 62 únicas, 179 correctas, 26 excluidas y cero errores. Las exclusiones incluyen protocolos de teléfono/correo y las reglas heredadas del proyecto.
- Un título principal por página, etiquetas de formulario, texto alternativo, indicadores de foco y respeto de movimiento reducido.
- Sintaxis JavaScript y XML del sitemap correctos. Sin errores ni avisos en la consola del navegador en las comprobaciones realizadas.

Estas comprobaciones usan el navegador disponible con tamaños adaptados; no equivalen a pruebas en dispositivos iOS/Android físicos, a una auditoría WCAG completa ni a resultados de tráfico real.

## Antes de llevarlo a producción

1. Revisar y aceptar la versión de pruebas y la selección de fotos.
2. Confirmar que el WhatsApp anunciado recibe correctamente las consultas.
3. Validar las condiciones de reserva: el proyecto ya identifica los plazos de cancelación y el anticipo como propuestas pendientes. Se conserva ese aviso y no se presentan como recién validados. La política de privacidad también conserva contenido heredado que debe revisar la asesoría, en particular proveedores y transferencias de datos.
4. Trasladar el diseño a `IFASTNET/web_camping/index.php` y `casa.php`, conservando la generación de tarifas del PMS y la lógica real de disponibilidad. Evitar mantener precios estáticos en producción.
5. Adaptar la explicación de privacidad al funcionamiento definitivo del formulario y su API.
6. Cambiar canonical y sitemap al dominio público, restaurar indexación y retirar el aviso de vista previa. Recuperar los datos estructurados de negocio del PHP original con datos verificados.
7. Hacer copia del sitio publicado, validar PHP, subir los archivos y verificar páginas, imágenes, consultas y enlaces. Conservar una vía de reversión.

## Cómo evaluar si mejora la conversión

Comparar consultas por cada 100 visitas antes y después: clics en teléfono/WhatsApp, consultas enviadas y reservas realmente cerradas. Separar móvil y ordenador, y camping y casas. Un clic a WhatsApp no equivale a una reserva.

No se ha añadido seguimiento de terceros en esta prueba. Para medir en producción habrá que elegir una herramienta adecuada y revisar su configuración de privacidad. La siguiente mejora comercial más útil sería contar con nuevas fotos de los interiores de las casas y una versión inglesa revisada, especialmente para la larga estancia.

## Fuentes de trabajo

- [Web pública de Camping Sopalmo](https://campingsopalmo.com).
- [Repositorio de producción](https://github.com/Adrianzp2003/IFASTNET/tree/main/web_camping).
- [Repositorio de pruebas](https://github.com/Adrianzp2003/camping-sopalmo-preview).
- Documentación `LEEME.md` de la web y `docs/TEST-LOOP.md` de la prueba.
- Fotografías de `C:\Users\mclar\Desktop\FOTOS PAGINA`, seleccionadas para este encargo.
