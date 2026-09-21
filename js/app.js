/* Static preview: enquiry preparation, never a live booking confirmation. */
(() => {
  'use strict';
  document.documentElement.classList.add('js');
  const toggle = document.querySelector('.menu-toggle');
  const menu = document.querySelector('#menu');
  const setMenu = open => {
    menu?.classList.toggle('open', open);
    toggle?.setAttribute('aria-expanded', String(open));
  };
  toggle?.addEventListener('click', () => setMenu(toggle.getAttribute('aria-expanded') !== 'true'));
  menu?.addEventListener('click', event => { if (event.target.closest('a')) setMenu(false); });
  document.addEventListener('keydown', event => {
    if (event.key === 'Escape' && toggle?.getAttribute('aria-expanded') === 'true') {
      setMenu(false); toggle.focus();
    }
  });
  window.matchMedia('(min-width: 951px)').addEventListener('change', () => setMenu(false));
  const form = document.querySelector('#stay-form');
  if (form) {
    const type = form.elements.alojamiento;
    const arrival = form.elements.entrada;
    const departure = form.elements.salida;
    const adults = form.elements.adultos;
    const children = form.elements.ninos;
    const result = document.querySelector('#query-result');
    const error = document.querySelector('#form-error');
    const today = () => {
      const parts = new Intl.DateTimeFormat('en-CA', {timeZone:'Europe/Madrid', year:'numeric', month:'2-digit', day:'2-digit'}).formatToParts(new Date());
      const get = name => parts.find(part => part.type === name).value;
      return `${get('year')}-${get('month')}-${get('day')}`;
    };
    const addDays = (date, days) => {
      const value = new Date(date + 'T12:00:00Z');
      value.setUTCDate(value.getUTCDate() + days);
      return value.toISOString().slice(0,10);
    };
    const isHouse = () => type.value === 'cortijillo' || type.value === 'mirador';
    const invalidateResult = () => { result.hidden = true; error.textContent = ''; };
    const updateRules = () => {
      arrival.min = today();
      departure.min = addDays(arrival.value || today(), isHouse() ? 2 : 1);
      adults.max = isHouse() ? '5' : '12';
      children.max = isHouse() ? '4' : '12';
      document.querySelector('#modalidad').disabled = isHouse();
      document.querySelector('#luz').disabled = isHouse();
      document.querySelector('#form-help').textContent = isHouse()
        ? 'Esta casa admite hasta 5 personas en total. Mínimo 2 noches.'
        : type.value === 'invierno'
          ? 'Te confirmamos el periodo de invierno y la tarifa según la duración. Electricidad aparte.'
          : 'Personas, electricidad y otros extras se confirman según la tarifa. Indica las edades de los niños en los detalles.';
    };
    updateRules();
    form.addEventListener('input', invalidateResult);
    form.addEventListener('change', () => { invalidateResult(); updateRules(); });
    document.querySelectorAll('[data-stay]').forEach(link => link.addEventListener('click', () => {
      type.value = link.dataset.stay;
      invalidateResult(); updateRules();
    }));
    form.addEventListener('submit', event => {
      event.preventDefault();
      invalidateResult(); updateRules();
      if (!form.reportValidity()) return;
      const nights = Math.round((new Date(departure.value + 'T12:00:00Z') - new Date(arrival.value + 'T12:00:00Z')) / 86400000);
      const count = Number(adults.value) + Number(children.value);
      let problem = '';
      if (arrival.value < today()) problem = 'Elige una llegada a partir de hoy.';
      else if (!Number.isFinite(nights) || nights < 1) problem = 'La salida debe ser posterior a la llegada.';
      else if (isHouse() && nights < 2) problem = 'Las casas rurales tienen una estancia mínima de 2 noches.';
      else if (isHouse() && count > 5) problem = 'Cada casa admite un máximo de 5 personas, contando adultos y niños.';
      if (problem) { error.textContent = problem; return; }
      const formatDate = value => new Intl.DateTimeFormat('es-ES', {day:'numeric',month:'long',year:'numeric',timeZone:'UTC'}).format(new Date(value+'T12:00:00Z'));
      const lines = [
        'Hola, Camping Sopalmo. Quisiera consultar precio y disponibilidad.',
        `Alojamiento: ${type.options[type.selectedIndex].text}.`,
        `Llegada: ${formatDate(arrival.value)}.`,
        `Salida: ${formatDate(departure.value)} (${nights} ${nights === 1 ? 'noche' : 'noches'}).`,
        `${adults.value} ${Number(adults.value) === 1 ? 'adulto' : 'adultos'} y ${children.value} ${Number(children.value) === 1 ? 'niño' : 'niños'}.`
      ];
      if (!isHouse() && form.elements.modalidad.value) lines.push(`Viajamos con: ${form.elements.modalidad.value}.`);
      if (!isHouse() && form.elements.luz.checked) lines.push('Necesitamos electricidad.');
      if (form.elements.mascota.checked) lines.push('Viajamos con mascota; queremos consultar las condiciones.');
      if (form.elements.notas.value.trim()) lines.push(form.elements.notas.value.trim());
      const message = lines.join('\n');
      document.querySelector('#query-summary').textContent = message;
      document.querySelector('#query-wa').href = 'https://wa.me/34660735368?text=' + encodeURIComponent(message);
      document.querySelector('#query-email').href = 'mailto:campingsopalmo@gmail.com?subject=' + encodeURIComponent('Consulta de estancia · Camping Sopalmo') + '&body=' + encodeURIComponent(message);
      result.hidden = false;
      result.focus({preventScroll:true});
      result.scrollIntoView({behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'instant' : 'smooth', block:'center'});
    });
  }
  const dialog = document.querySelector('#photo-dialog');
  if (dialog && typeof dialog.showModal === 'function') {
    let trigger;
    document.querySelectorAll('.photo-link').forEach(link => link.addEventListener('click', event => {
      event.preventDefault(); trigger = link;
      const original = link.querySelector('img');
      const large = document.querySelector('#photo-large');
      large.src = link.href; large.alt = original.alt;
      document.querySelector('#photo-caption').textContent = link.closest('figure').querySelector('figcaption').textContent;
      dialog.showModal(); document.body.classList.add('dialog-open');
    }));
    dialog.querySelector('.dialog-close').addEventListener('click', () => dialog.close());
    dialog.addEventListener('click', event => { if (event.target === dialog) {
      const r = dialog.getBoundingClientRect();
      if (event.clientX < r.left || event.clientX > r.right || event.clientY < r.top || event.clientY > r.bottom) dialog.close();
    }});
    dialog.addEventListener('close', () => { document.body.classList.remove('dialog-open'); trigger?.focus(); });
  }
})();
