/* =============================================================
   Camping Sopalmo — interacción
   Sin librerías. Todo lo esencial funciona ya sin este fichero.

   Regla que atraviesa todo el archivo: en móvil NO se descarga
   ningún vídeo. La guía es explícita — foto fija y punto, porque
   media web entra con la cobertura del pueblo.
   ============================================================= */
(function () {
  'use strict';

  var $  = function (s, c) { return (c || document).querySelector(s); };
  var $$ = function (s, c) { return Array.prototype.slice.call((c || document).querySelectorAll(s)); };

  var eur = new Intl.NumberFormat('es-ES', { style: 'currency', currency: 'EUR' });
  var fechaLarga = new Intl.DateTimeFormat('es-ES', { day: 'numeric', month: 'long' });

  var esMovil    = window.matchMedia('(max-width: 860px)').matches;
  var menosMovim = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  var conVideo   = !esMovil && !menosMovim;

  function svgIco(d, w) {
    return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="' + (w || 2) +
           '" stroke-linecap="round" stroke-linejoin="round" class="ico-aviso" aria-hidden="true">' + d + '</svg>';
  }
  var SVG_SI = svgIco('<path d="m4.5 12.5 5 5 10-11"/>');
  var SVG_NO = svgIco('<path d="M6 6l12 12M18 6 6 18"/>');
  var SVG_AV = svgIco('<path d="M12 3.6 1.8 20.4h20.4L12 3.6Z"/><path d="M12 9.6v4.2M12 17.2h.01"/>', 1.8);

  /* =========================================================
     1. ENTRADA — el aterrizaje
     Sin pantalla de carga ni contador: la portada ES la entrada.
     Aquí solo se decide CUÁNDO empieza el movimiento, y el
     movimiento en sí lo hace el CSS.

     Se espera a que la imagen de portada esté decodificada, no a
     que cargue la página entera: si esperásemos al `load`, la
     entrada se retrasaría por culpa de fotos de más abajo que no
     se están ni mirando.
     ========================================================= */

  var raiz = document.documentElement;
  var aterrizado = false;

  function aterrizar() {
    if (aterrizado) return;
    aterrizado = true;
    raiz.classList.add('aterrizado');
    raiz.classList.remove('precarga');
    // El velo ya no pinta nada una vez transparente.
    setTimeout(function () {
      var v = $('#velo');
      if (v && v.parentNode) v.parentNode.removeChild(v);
    }, 900);
  }

  /* ---------------------------------------------------------
     1.b DESCENSO DESDE EL ESPACIO

     Un vídeo de 3,2 s. Adorno puro, y se comporta como tal:
     no se descarga hasta que la portada está decodificada, y
     si no puede reproducirse del tirón NO se enseña — se borra
     y se entra por el aterrizaje corto de siempre.

     Se exige `canplaythrough` antes de arrancar. Empezar en
     cuanto hay unos fotogramas y quedarse a medias a mitad del
     picado es peor que no ponerlo.
     --------------------------------------------------------- */

  var ESP_MARGEN = 2600;   // cuánto se espera a que el vídeo esté listo
  var ESP_SALIDA = 500;    // fundido del bloque al terminar (igual en el CSS)

  var espacio = {
    nodo: null,
    video: null,
    lanzado: false,
    temporizadores: [],

    /* ¿Toca ponerlo? */
    procede: function (nodo) {
      if (!nodo) return false;
      var v = nodo.querySelector('video');
      if (!v || !v.canPlayType) return false;
      // Quien pide menos movimiento no ve esto (el CSS también lo oculta).
      var mq = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)');
      if (mq && mq.matches) return false;
      // Ni quien navega con ahorro de datos o con mala conexión: son ~2 MB,
      // y esperarlos con la pantalla en negro es peor que no verlos.
      var con = navigator.connection;
      if (con && (con.saveData || /^(slow-)?2g$|^3g$/.test(con.effectiveType || ''))) return false;
      // Una vez por visita: repetir el viaje en cada página cansa.
      // Con ?intro=1 se fuerza (sirve para enseñarlo).
      if (/[?&]intro=1/.test(location.search)) return true;
      try {
        if (sessionStorage.getItem('intro-vista')) return false;
      } catch (e) { /* sin sessionStorage, que se vea */ }
      return true;
    },

    /* Qué fichero pedir.

       El vídeo es el descenso generado por IA (Veo 3.1) sobre el camping,
       invertido y acelerado a 4 s. AV1 primero porque pesa menos con la
       misma calidad, y el de 1080 solo si la pantalla lo aprovecha; si no,
       el de 720. */
    fuente: function (v) {
      var ancho = Math.max(window.innerWidth, window.innerHeight) *
                  (window.devicePixelRatio || 1);
      var grande = ancho >= 1500;
      function puede(t) {
        var r = v.canPlayType(t);
        return r === 'probably' || r === 'maybe';
      }
      if (puede('video/mp4; codecs="av01.0.05M.08"')) {
        return 'video/descenso-' + (grande ? '1080' : '720') + '.av1.mp4';
      }
      if (puede('video/webm; codecs="vp9"')) {
        return 'video/descenso-720.webm';
      }
      return 'video/descenso-720.mp4';
    },

    marcarVista: function () {
      try { sessionStorage.setItem('intro-vista', '1'); } catch (e) {}
    },

    /* Pide el vídeo y avisa cuando se puede reproducir entero. */
    preparar: function (nodo, alListo, alFallo) {
      var v = nodo.querySelector('video');
      this.nodo = nodo;
      this.video = v;

      var resuelto = false;
      var margen = setTimeout(function () {
        if (!resuelto) { resuelto = true; alFallo(); }
      }, ESP_MARGEN);

      function listo() {
        if (resuelto) return;
        resuelto = true;
        clearTimeout(margen);
        alListo();
      }
      function fallo() {
        if (resuelto) return;
        resuelto = true;
        clearTimeout(margen);
        alFallo();
      }

      v.addEventListener('canplaythrough', listo, { once: true });
      v.addEventListener('error', fallo, { once: true });
      v.preload = 'auto';
      v.src = this.fuente(v);
      v.load();
    },

    lanzar: function () {
      if (this.lanzado || !this.nodo) return;
      this.lanzado = true;
      this.marcarVista();
      raiz.classList.add('espacio');

      var v = this.video;
      var self = this;

      // El vídeo es el descenso IA invertido: termina en el camping, la misma
      // escena que hay debajo. El fundido del bloque se dispara ANTES del
      // `ended`, cuando por pantalla ya solo queda la foto: así ningún
      // navegador llega a enseñar el fotograma negro que algunos pintan al
      // agotarse un vídeo, y el relevo es imperceptible.
      var disparado = false;
      var terminarUnaVez = function () {
        if (disparado) return;
        disparado = true;
        self.terminar();
      };
      v.addEventListener('timeupdate', function () {
        if (v.duration && v.duration - v.currentTime < 0.25) terminarUnaVez();
      });
      v.addEventListener('ended', terminarUnaVez, { once: true });

      var p = v.play();
      if (p && p.catch) {
        // Si el navegador rechaza la reproducción automática, no insistimos.
        p.catch(function () { self.saltar(); });
      }

      // Red de seguridad: si el vídeo se atasca, no dejamos la página tapada.
      this.temporizadores.push(setTimeout(function () {
        self.terminar();
      }, 6000));

      var saltar = function () { self.saltar(); };
      var btn = $('#esp-saltar');
      if (btn) btn.addEventListener('click', saltar);
      this.nodo.addEventListener('click', saltar);
      window.addEventListener('keydown', saltar, { once: true });
      window.addEventListener('wheel', saltar, { once: true, passive: true });
      window.addEventListener('touchstart', saltar, { once: true, passive: true });
    },

    /* Fin natural: el vídeo ya está en la foto de destino. */
    terminar: function () {
      if (!this.nodo) return;
      aterrizar();
      this.desvanecer();
    },

    /* Salto voluntario: el vídeo está a mitad, así que se corta. */
    saltar: function () {
      if (!this.nodo) return;
      aterrizar();
      this.desvanecer();
    },

    desvanecer: function () {
      var n = this.nodo;
      this.nodo = null;
      this.temporizadores.forEach(clearTimeout);
      if (!n) return;
      n.classList.add('saliendo');
      var v = this.video;
      setTimeout(function () {
        if (v) { try { v.pause(); v.removeAttribute('src'); v.load(); } catch (e) {} }
        if (n.parentNode) n.parentNode.removeChild(n);
        // OJO: la clase `espacio` del <html> NO se quita. La deriva de la
        // aérea sigue en marcha y quitarla cambiaría su regla a mitad de
        // transición: el navegador la reiniciaría y se vería frenar de golpe.
      }, ESP_SALIDA + 60);
    }
  };

  (function prepararEntrada() {
    var img = $('#hero-img');
    var nodo = $('#espacio');

    if (!espacio.procede(nodo)) {
      if (nodo && nodo.parentNode) nodo.parentNode.removeChild(nodo);
      nodo = null;
    }

    function tirarBloque() {
      if (nodo && nodo.parentNode) nodo.parentNode.removeChild(nodo);
      nodo = null;
      aterrizar();
    }

    /* La portada ya se puede pintar. Solo AHORA se pide el vídeo: antes
       competiría con la aérea del camping, que es el LCP. */
    function heroListo() {
      if (!nodo) { aterrizar(); return; }
      espacio.preparar(nodo, function () { espacio.lanzar(); }, tirarBloque);
    }

    // Sin imagen de portada (o sin JS previo) no bloqueamos nada.
    if (!img) { heroListo(); return; }

    function cuandoLista() {
      // decode() garantiza que la portada ya es pintable; si no existe,
      // seguimos igual.
      if (img.decode) {
        img.decode().then(heroListo).catch(heroListo);
      } else {
        heroListo();
      }
    }

    if (img.complete) {
      // TRAMPA: `complete` no significa "cargada bien", significa "ya no está
      // cargando" — y también vale true cuando la imagen ha FALLADO. Antes se
      // exigía `complete && naturalWidth > 0` y, si había fallado, se caía al
      // else a esperar un `load` que ya no podía llegar nunca: la entrada se
      // quedaba colgada, el bloque del descenso no se retiraba y el vídeo ni
      // se pedía. Aquí el caso "terminada pero rota" va por la vía del fallo.
      if (img.naturalWidth > 0) {
        cuandoLista();
      } else {
        heroListo();
      }
    } else {
      img.addEventListener('load', cuandoLista, { once: true });
      img.addEventListener('error', heroListo, { once: true });
    }

    // Red de seguridad: pase lo que pase se entra. Si el descenso ya ha
    // arrancado se le deja terminar, que él se encarga.
    setTimeout(function () {
      if (!espacio.lanzado) aterrizar();
    }, 3000);
  })();

  /* =========================================================
     3. NAVEGACIÓN
     ========================================================= */

  var nav = $('#nav');
  function navScroll() {
    if (!nav) return;
    var umbral = document.querySelector('.hero') ? window.innerHeight * 0.7 : 40;
    nav.classList.toggle('solida', window.scrollY > umbral);
  }
  navScroll();
  window.addEventListener('scroll', navScroll, { passive: true });

  // Menú móvil
  var burger = $('#hamburguesa'), menu = $('#menu');
  if (burger && menu) {
    var abrirMenu = function (abrir) {
      menu.classList.toggle('abierto', abrir);
      burger.setAttribute('aria-expanded', String(abrir));
      burger.setAttribute('aria-label', abrir ? 'Cerrar menú' : 'Abrir menú');
      document.body.style.overflow = abrir ? 'hidden' : '';
      if (abrir) nav.classList.add('solida'); else navScroll();
    };
    burger.addEventListener('click', function () {
      abrirMenu(!menu.classList.contains('abierto'));
    });
    menu.addEventListener('click', function (ev) {
      if (ev.target.tagName === 'A') abrirMenu(false);
    });
    // Cerrar con Escape o al tocar fuera: antes se quedaba abierto y tapaba la web.
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && menu.classList.contains('abierto')) abrirMenu(false);
    });
    document.addEventListener('click', function (e) {
      if (!menu.classList.contains('abierto')) return;
      if (!menu.contains(e.target) && !burger.contains(e.target)) abrirMenu(false);
    });
  }

  // Barra de progreso de lectura
  var barraLectura = document.createElement('div');
  barraLectura.className = 'progreso-lectura';
  document.body.appendChild(barraLectura);
  function progresoLectura() {
    var h = document.documentElement.scrollHeight - window.innerHeight;
    barraLectura.style.width = (h > 0 ? (window.scrollY / h) * 100 : 0) + '%';
  }
  window.addEventListener('scroll', progresoLectura, { passive: true });
  progresoLectura();

  // Sección activa en el menú
  var enlacesAncla = $$('.menu a').filter(function (a) {
    return (a.getAttribute('href') || '').indexOf('#') === 0;
  });
  if (enlacesAncla.length && 'IntersectionObserver' in window) {
    var secciones = enlacesAncla.map(function (a) {
      return document.querySelector(a.getAttribute('href'));
    }).filter(Boolean);

    var obsSec = new IntersectionObserver(function (ents) {
      ents.forEach(function (e) {
        if (!e.isIntersecting) return;
        enlacesAncla.forEach(function (a) {
          a.classList.toggle('actual', a.getAttribute('href') === '#' + e.target.id);
        });
      });
    }, { rootMargin: '-45% 0px -50% 0px' });
    secciones.forEach(function (s) { obsSec.observe(s); });
  }

  // Transición al salir a otra página
  var velo = document.createElement('div');
  velo.className = 'velo-salida';
  document.body.appendChild(velo);
  if (!menosMovim) {
    document.addEventListener('click', function (e) {
      var a = e.target.closest('a');
      if (!a) return;
      var href = a.getAttribute('href') || '';
      if (!href || href.charAt(0) === '#' || a.target === '_blank') return;
      if (/^(mailto:|tel:|https?:\/\/)/.test(href) && href.indexOf(location.host) === -1) return;
      if (e.metaKey || e.ctrlKey || e.shiftKey) return;
      e.preventDefault();
      velo.classList.add('activo');
      setTimeout(function () { location.href = href; }, 300);
    });
    window.addEventListener('pageshow', function (e) {
      if (e.persisted) velo.classList.remove('activo');
    });
  }

  /* ---------- 4. Scroll reveal ---------- */

  var revelables = $$('.revelar');
  if ('IntersectionObserver' in window && !menosMovim) {
    var obs = new IntersectionObserver(function (ents) {
      ents.forEach(function (e) {
        if (e.isIntersecting) { e.target.classList.add('dentro'); obs.unobserve(e.target); }
      });
    }, { rootMargin: '0px 0px -12% 0px', threshold: 0.08 });
    revelables.forEach(function (n) { obs.observe(n); });
  } else {
    revelables.forEach(function (n) { n.classList.add('dentro'); });
  }

  /* ---------- 5. Vídeo controlado por scroll ---------- */

  var escena  = $('.escena-scroll'),
      barra   = $('#escena-barra'),
      pasos   = $$('.escena-paso'),
      secImg  = $('#sec-img'),
      secCarga = $('#sec-carga');

  if (escena && secImg) {
    /*
       SECUENCIA POR SCROLL (técnica nº 7 de la guía)

       El dron se aleja del camping y el visitante controla el movimiento
       con la rueda. Tres decisiones que hacen que esto vaya fino:

       1. Se PRECARGAN todos los fotogramas antes de permitir el barrido.
          Si se van pidiendo sobre la marcha, el scroll va a tirones y se
          ven huecos en blanco.
       2. La precarga no arranca hasta que la sección se acerca, para no
          competir con la portada.
       3. En móvil se usa un juego de imágenes más pequeño (m01…) — la
          mitad de peso y en una pantalla de 6 pulgadas no se nota.
    */
    var TOTAL   = parseInt(escena.dataset.frames, 10) || 28;
    var PREFIJO = esMovil ? 'm' : 'f';
    var cache   = [];
    var listos  = 0;
    var arrancado = false;
    var ultimo  = -1;

    function dos(n) { return (n < 10 ? '0' : '') + n; }

    function precargar() {
      if (cache.length) return;
      for (var i = 1; i <= TOTAL; i++) {
        (function (n) {
          var im = new Image();
          im.decoding = 'async';
          im.onload = im.onerror = function () {
            listos++;
            if (secCarga) {
              secCarga.style.setProperty('--p', (listos / TOTAL * 100) + '%');
            }
            if (listos >= TOTAL) {
              arrancado = true;
              if (secCarga) secCarga.classList.add('fuera');
              pintar();
            }
          };
          im.src = 'fotos/secuencia/' + PREFIJO + dos(n) + '.webp';
          cache[n - 1] = im;
        })(i);
      }
    }

    // No descargamos 3,5 MB hasta que la sección está cerca.
    if ('IntersectionObserver' in window) {
      new IntersectionObserver(function (ents, obs) {
        ents.forEach(function (e) {
          if (e.isIntersecting) { precargar(); obs.disconnect(); }
        });
      }, { rootMargin: '600px 0px' }).observe(escena);
    } else {
      precargar();
    }

    function progreso() {
      var r = escena.getBoundingClientRect();
      var recorrido = r.height - window.innerHeight;
      return recorrido <= 0 ? 0 : Math.min(1, Math.max(0, -r.top / recorrido));
    }

    function pintar() {
      var p = progreso();
      if (barra) barra.style.width = (p * 100).toFixed(1) + '%';

      if (arrancado) {
        var idx = Math.min(TOTAL - 1, Math.round(p * (TOTAL - 1)));
        if (idx !== ultimo) {
          ultimo = idx;
          // La imagen ya está descargada y decodificada: el cambio es inmediato.
          secImg.src = cache[idx].src;
        }
      }

      var ip = Math.min(pasos.length - 1, Math.floor(p * pasos.length * 0.999));
      pasos.forEach(function (n, i) { n.classList.toggle('visible', i === ip); });
    }

    window.addEventListener('scroll', pintar, { passive: true });
    window.addEventListener('resize', pintar);
    pintar();

  } else if (pasos.length) {
    pasos.forEach(function (n) { n.classList.add('visible'); n.style.position = 'relative'; });
    if (escena) escena.style.height = 'auto';
  }



  /* =========================================================
     6. VISOR A PANTALLA COMPLETA
     Teclado (flechas y Esc), gestos de deslizar y precarga de la
     siguiente, para que al pasar no aparezca en blanco.
     ========================================================= */

  var visor = $('#visor');
  if (visor) {
    var items = $$('.gc-item');
    var vImg = $('#visor-img'), vPie = $('#visor-pie'), vCont = $('#visor-contador');
    var idx = 0, ultimoFoco = null;

    function precargar(i) {
      if (i < 0 || i >= items.length) return;
      var im = new Image();
      im.src = items[i].dataset.grande;
    }

    function mostrar(i) {
      idx = (i + items.length) % items.length;
      var it = items[idx];
      visor.classList.remove('listo');
      var im = new Image();
      im.onload = function () {
        vImg.src = im.src;
        vImg.alt = it.dataset.pie || '';
        visor.classList.add('listo');
      };
      im.src = it.dataset.grande;
      vPie.textContent = it.dataset.pie || '';
      vCont.textContent = (idx + 1) + ' / ' + items.length;
      precargar(idx + 1); precargar(idx - 1);
    }

    function abrir(i) {
      ultimoFoco = document.activeElement;
      visor.hidden = false;
      document.body.classList.add('visor-abierto');
      requestAnimationFrame(function () { visor.classList.add('abierto'); });
      mostrar(i);
      $('#visor-cerrar').focus();
    }
    function cerrar() {
      visor.classList.remove('abierto', 'listo');
      document.body.classList.remove('visor-abierto');
      setTimeout(function () { visor.hidden = true; vImg.removeAttribute('src'); }, 300);
      if (ultimoFoco) ultimoFoco.focus();
    }

    items.forEach(function (it, i) { it.addEventListener('click', function () { abrir(i); }); });
    $('#visor-cerrar').addEventListener('click', cerrar);
    $('#visor-prev').addEventListener('click', function () { mostrar(idx - 1); });
    $('#visor-sig').addEventListener('click', function () { mostrar(idx + 1); });
    visor.addEventListener('click', function (e) { if (e.target === visor) cerrar(); });

    document.addEventListener('keydown', function (e) {
      if (visor.hidden) return;
      if (e.key === 'Escape') cerrar();
      else if (e.key === 'ArrowRight') mostrar(idx + 1);
      else if (e.key === 'ArrowLeft') mostrar(idx - 1);
    });

    // Gestos: deslizar horizontal cambia de foto, vertical cierra.
    var x0 = 0, y0 = 0;
    visor.addEventListener('touchstart', function (e) {
      x0 = e.changedTouches[0].clientX; y0 = e.changedTouches[0].clientY;
    }, { passive: true });
    visor.addEventListener('touchend', function (e) {
      var dx = e.changedTouches[0].clientX - x0;
      var dy = e.changedTouches[0].clientY - y0;
      if (Math.abs(dx) > 55 && Math.abs(dx) > Math.abs(dy)) mostrar(idx + (dx < 0 ? 1 : -1));
      else if (dy > 90) cerrar();
    }, { passive: true });
  }

  /* =========================================================
     7. Pestañas de tarifas
     ========================================================= */

  var pestanas = $$('.pestana');
  pestanas.forEach(function (p) {
    p.addEventListener('click', function () {
      pestanas.forEach(function (o) {
        o.setAttribute('aria-selected', String(o === p));
        var panel = document.getElementById(o.dataset.panel);
        if (panel) panel.classList.toggle('oculto', o !== p);
      });
    });
  });

  /* =========================================================
     8. Calculadora
     ========================================================= */

  var form = $('#formulario');
  if (!form) return;

  var tipoFijo   = form.dataset.tipoFijo || '';    // las fichas de casa lo fijan
  var casaSlug   = form.dataset.casa || '';
  var elTipo     = $('#tipo');
  var elEntrada  = $('#entrada'), elSalida = $('#salida'), btn = $('#btn-calcular');
  var resInicial = $('#res-inicial'), resDatos = $('#res-datos'), resError = $('#res-error');
  var resTotal   = $('#res-total'), resNota = $('#res-nota'), resDisp = $('#res-disponibilidad');
  var resDesglose = $('#res-desglose'), resWa = $('#res-wa');

  function iso(d) { return d.toISOString().slice(0, 10); }
  var hoy = new Date();
  elEntrada.value = iso(new Date(hoy.getTime() + 86400000));
  elSalida.value  = iso(new Date(hoy.getTime() + 5 * 86400000));
  elEntrada.min   = iso(hoy);
  elSalida.min    = iso(new Date(hoy.getTime() + 86400000));

  elEntrada.addEventListener('change', function () {
    var e = new Date(elEntrada.value);
    if (isNaN(e)) return;
    var minSal = new Date(e.getTime() + 86400000);
    elSalida.min = iso(minSal);
    if (elSalida.value <= elEntrada.value) elSalida.value = iso(minSal);
  });

  function aplicarTipo() {
    if (tipoFijo) return;
    var rural = elTipo.value === 'rural';
    $$('.grupo-camping').forEach(function (n) { n.classList.toggle('oculto', rural); });
    $$('.grupo-rural').forEach(function (n) { n.classList.toggle('oculto', !rural); });
  }
  if (elTipo) { elTipo.addEventListener('change', aplicarTipo); aplicarTipo(); }

  function escapar(s) { var d = document.createElement('div'); d.textContent = String(s == null ? '' : s); return d.innerHTML; }
  function textoFecha(f) { var d = new Date(f + 'T00:00:00'); return isNaN(d) ? f : fechaLarga.format(d); }

  function mostrarError(msg) {
    resInicial.classList.add('oculto'); resDatos.classList.add('oculto'); resError.classList.remove('oculto');
    resError.innerHTML = '<div class="aviso aviso-no">' + SVG_AV + '<div>' + escapar(msg) +
      '<br><small>También puedes llamarnos y te lo decimos al momento.</small></div></div>';
  }

  function pintar(r) {
    resInicial.classList.add('oculto'); resError.classList.add('oculto'); resDatos.classList.remove('oculto');
    resTotal.textContent = eur.format(r.total || 0);

    var noches = r.noches || 0;
    var porNoche = noches ? eur.format((r.total || 0) / noches) : '';
    resNota.textContent = noches + (noches === 1 ? ' noche' : ' noches') +
      (porNoche ? ' · ' + porNoche + ' por noche' : '') + (r.temporada ? ' · temporada ' + r.temporada : '');

    var libre = r.disponible;
    var extra = '';
    if (r.detalle && r.detalle.casas) {
      // En la ficha de una casa solo interesa ESA casa.
      var claves = Object.keys(r.detalle.casas);
      if (casaSlug && claves.indexOf(casaSlug) !== -1) {
        var c = r.detalle.casas[casaSlug];
        libre = c.estado === 'libre';
        extra = '';
      } else {
        extra = '<br>' + claves.map(function (k) {
          var x = r.detalle.casas[k];
          return '<small>' + escapar(x.nombre) + ': ' + (x.estado === 'libre' ? 'libre' : 'ocupada') + '</small>';
        }).join(' · ');
      }
    }
    var msg = casaSlug
      ? (libre ? 'Libre en esas fechas.' : 'Ocupada en esas fechas.')
      : (r.disponibilidad || '');
    resDisp.innerHTML = '<div class="aviso ' + (libre ? 'aviso-ok' : 'aviso-no') + '">' +
      (libre ? SVG_SI : SVG_NO) + '<div>' + escapar(msg) + extra + '</div></div>';

    resDesglose.innerHTML = '';
    (r.desglose || []).forEach(function (d) {
      var li = document.createElement('li');
      var etq = d.desde && d.hasta
        ? textoFecha(d.desde) + ' – ' + textoFecha(d.hasta) + ' (' + d.noches + ' n.)'
        : (d.concepto || 'Estancia');
      li.innerHTML = '<span class="etiqueta">' + escapar(etq) + '</span><span class="valor">' + eur.format(d.subtotal || 0) + '</span>';
      resDesglose.appendChild(li);
    });
    if (r.suplemento > 0) {
      var ls = document.createElement('li');
      ls.innerHTML = '<span class="etiqueta">Suplemento de una sola noche</span><span class="valor">' + eur.format(r.suplemento) + '</span>';
      resDesglose.appendChild(ls);
    }
    var tot = document.createElement('li');
    tot.innerHTML = '<span class="etiqueta"><strong>Total</strong></span><span class="valor"><strong>' + eur.format(r.total || 0) + '</strong></span>';
    resDesglose.appendChild(tot);

    var texto = 'Hola, me interesa una estancia en el Camping Sopalmo.\n' +
      (casaSlug ? (casaSlug === 'cortijillo' ? 'Casa El Cortijillo' : 'Casa El Mirador de la Rambla')
                : (r.tipo === 'rural' ? 'Casa rural' : 'Parcela de camping')) + '\n' +
      'Entrada: ' + r.entrada + '\nSalida: ' + r.salida + '\nNoches: ' + noches +
      '\nPresupuesto web: ' + eur.format(r.total || 0);
    resWa.href = 'https://wa.me/34950478413?text=' + encodeURIComponent(texto);
  }

  form.addEventListener('submit', function (ev) {
    ev.preventDefault();
    if (!elEntrada.value || !elSalida.value) { mostrarError('Indica las fechas de entrada y salida.'); return; }
    if (elSalida.value <= elEntrada.value) { mostrarError('La salida debe ser posterior a la entrada.'); return; }

    var tipo = tipoFijo || (elTipo ? elTipo.value : 'camping');
    var datos = { tipo: tipo, entrada: elEntrada.value, salida: elSalida.value };

    if (tipo === 'rural') {
      datos.personas = +($('#personas') || {}).value || 2;
    } else {
      datos.adultos = +$('#adultos').value || 0;
      datos.ninos   = +$('#ninos').value || 0;
      datos.perros  = +$('#perros').value || 0;
      datos.electricidad      = $('#electricidad').checked ? 1 : 0;
      datos.tienda_extra      = $('#tienda_extra').checked ? 1 : 0;
      datos.coche_extra       = $('#coche_extra').checked ? 1 : 0;
      datos.frigorifico_extra = $('#frigorifico_extra').checked ? 1 : 0;
      datos.larga_estancia    = $('#larga_estancia').checked ? 1 : 0;
    }

    btn.disabled = true;
    var textoBtn = btn.textContent;
    btn.innerHTML = '<span class="cargando"></span> Consultando…';

    fetch('api/calcular.php', {
      method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(datos)
    })
      .then(function (res) { return res.json().catch(function () { return { ok: false }; }); })
      .then(function (r) {
        if (!r || !r.ok) { mostrarError(r && r.msg ? r.msg : 'No hemos podido calcular el precio.'); return; }
        pintar(r);
        resDatos.scrollIntoView({ behavior: menosMovim ? 'auto' : 'smooth', block: 'nearest' });
      })
      .catch(function () { mostrarError('No hemos podido conectar. Inténtalo de nuevo.'); })
      .finally(function () { btn.disabled = false; btn.textContent = textoBtn; });
  });
})();
