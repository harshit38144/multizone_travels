(function () {
  var root = document.getElementById('mzUpcomingToursMap');
  if (!root) return;

  var stage = root.querySelector('.mz-tour-map-stage');
  var canvas = root.querySelector('.mz-tour-map');
  var overlay = root.querySelector('.mz-map-overlay');
  var panel = root.querySelector('.mz-ops-panel');
  var payload = window.MZ_UPCOMING_MAP || { ok: false, error: 'Tour data was not provided.', tours: [] };
  var map = null;
  var raf = 0;
  var running = false;
  var routes = [];
  var pinned = null;
  var active = null;
  var labelKeys = {};
  var colors = ['#38bdf8', '#fbbf24', '#22d3ee', '#60a5fa', '#f59e0b'];
  var reducedMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  var planeSvg = '<svg viewBox="0 0 24 24" aria-hidden="true">'
    + '<g transform="rotate(90 12 12)">'
    + '<path fill="currentColor" stroke="#041422" stroke-width="0.6" stroke-linejoin="round" d="M21 16v-2l-8-5V3.5a1.5 1.5 0 0 0-3 0V9l-8 5v2l8-2.5V19l-2 1.5V22l3.5-1 3.5 1v-1.5L13 19v-5.5l8 2.5z"/>'
    + '</g></svg>';

  function setOverlay(title, message, isError) {
    if (!overlay) return;
    overlay.hidden = false;
    overlay.classList.toggle('is-error', !!isError);
    overlay.innerHTML = '<div><strong></strong><p></p></div>';
    overlay.querySelector('strong').textContent = title;
    overlay.querySelector('p').textContent = message;
  }

  function hideOverlay() {
    if (overlay) overlay.hidden = true;
  }

  function esc(value) {
    return String(value == null ? '' : value)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function tipHtml(tour) {
    return '<div class="mz-tip"><strong>' + esc(tour.name) + '</strong>'
      + '<span>' + esc(tour.departure_date || 'Date not set') + '</span>'
      + '<span>' + esc(tour.departure_city || 'Departure pending') + ' → ' + esc(tour.destination || 'Destination pending') + '</span>'
      + '<span>' + esc(tour.travellers_label || ((tour.travellers || 0) + ' travellers')) + '</span>'
      + '<em>' + esc(tour.status || 'Confirmed') + '</em></div>';
  }

  function curve(from, to, bend) {
    var dx = to.lng - from.lng;
    var dy = to.lat - from.lat;
    var len = Math.sqrt(dx * dx + dy * dy) || 1;
    var mag = Math.min(14, Math.max(2.2, len * 0.22));
    return {
      cLat: (from.lat + to.lat) / 2 + (-dx / len) * mag * bend,
      cLng: (from.lng + to.lng) / 2 + (dy / len) * mag * bend
    };
  }

  function pointAt(route, t) {
    var u = 1 - t;
    return L.latLng(
      u * u * route.from.lat + 2 * u * t * route.control.cLat + t * t * route.to.lat,
      u * u * route.from.lng + 2 * u * t * route.control.cLng + t * t * route.to.lng
    );
  }

  function planeIcon(color) {
    return L.divIcon({
      className: '',
      iconSize: [36, 36],
      iconAnchor: [18, 18],
      html: '<div class="mz-plane" style="color:' + color + '">' + planeSvg + '</div>'
    });
  }

  function placeLabel(latlng, text) {
    var key = latlng.lat.toFixed(2) + ',' + latlng.lng.toFixed(2) + ':' + text;
    if (labelKeys[key]) return;
    labelKeys[key] = true;
    L.marker(latlng, {
      interactive: false,
      keyboard: false,
      icon: L.divIcon({
        className: 'mz-city-label',
        html: '<span>' + esc(text) + '</span>',
        iconSize: [0, 0],
        iconAnchor: [0, 0]
      })
    }).addTo(map);
  }

  function bindTip(layer, tour) {
    layer.bindTooltip(tipHtml(tour), {
      className: 'mz-flight-tip',
      sticky: true,
      opacity: 1,
      direction: 'top',
      offset: [0, -8]
    });
  }

  function drawRoute(tour, index) {
    var bend = index % 2 === 0 ? 1 : -1;
    var color = colors[index % colors.length];
    var control = curve(tour.from, tour.to, bend);
    var route = {
      tour: tour,
      color: color,
      from: tour.from,
      to: tour.to,
      control: control,
      t: index * 0.17,
      prevT: index * 0.17,
      speed: 0.00009 + index * 0.000012,
      angle: null,
      trail: []
    };
    var line = [];
    for (var i = 0; i <= 64; i++) line.push(pointAt(route, i / 64));

    route.glow = L.polyline(line, {
      color: color,
      weight: 9,
      opacity: 0.28,
      lineCap: 'round',
      interactive: false,
      className: 'mz-route-glow'
    }).addTo(map);

    route.flow = L.polyline(line, {
      color: color,
      weight: 2.4,
      opacity: 0.95,
      dashArray: '1.2 8',
      lineCap: 'round',
      className: 'mz-route-flow'
    }).addTo(map);
    bindTip(route.flow, tour);
    route.flow.on('mouseover', function () { focusRoute(route, false); });
    route.flow.on('mouseout', function () { restorePinned(); });
    route.flow.on('click', function () { focusRoute(route, true); });

    route.trailLine = L.polyline([], {
      color: '#ffffff',
      weight: 2,
      opacity: 0.55,
      lineCap: 'round',
      interactive: false
    }).addTo(map);

    var from = L.latLng(tour.from.lat, tour.from.lng);
    var to = L.latLng(tour.to.lat, tour.to.lng);
    route.origin = L.circleMarker(from, {
      radius: 4.5, color: color, weight: 2, fillColor: '#04101f', fillOpacity: 1
    }).addTo(map);
    route.dest = L.circleMarker(to, {
      radius: 6, color: '#fff', weight: 2, fillColor: color, fillOpacity: 1
    }).addTo(map);
    route.halo = L.circleMarker(to, {
      radius: 11, color: color, weight: 1, fillColor: color, fillOpacity: 0.18, interactive: false
    }).addTo(map);
    [route.origin, route.dest].forEach(function (marker) {
      bindTip(marker, tour);
      marker.on('mouseover', function () { focusRoute(route, false); });
      marker.on('mouseout', function () { restorePinned(); });
      marker.on('click', function () { focusRoute(route, true); });
    });
    placeLabel(from, tour.departure_city || tour.from.label || 'Departure');
    placeLabel(to, tour.to.label || tour.destination || 'Destination');

    route.marker = L.marker(pointAt(route, route.t), {
      icon: planeIcon(color),
      interactive: true,
      keyboard: true,
      zIndexOffset: 500 + index
    }).addTo(map);
    bindTip(route.marker, tour);
    route.marker.on('mouseover', function () { focusRoute(route, false); });
    route.marker.on('mouseout', function () { restorePinned(); });
    route.marker.on('click', function () { focusRoute(route, true); });
    route.iconEl = route.marker.getElement() ? route.marker.getElement().querySelector('.mz-plane') : null;
    routes.push(route);
  }

  function applyFocusStyles() {
    routes.forEach(function (route) {
      var on = !active || route === active;
      var lineOpacity = on ? 0.96 : 0.16;
      var glowOpacity = on ? 0.42 : 0.06;
      route.flow.setStyle({ opacity: lineOpacity, weight: on ? 3 : 1.6 });
      route.glow.setStyle({ opacity: glowOpacity });
      route.trailLine.setStyle({ opacity: on ? 0.6 : 0.08 });
      route.origin.setStyle({ opacity: on ? 1 : 0.28, fillOpacity: on ? 1 : 0.25 });
      route.dest.setStyle({ opacity: on ? 1 : 0.28, fillOpacity: on ? 1 : 0.25 });
      route.halo.setStyle({ opacity: on ? 1 : 0.2, fillOpacity: on ? 0.18 : 0.04 });
      route.dim = on ? 1 : 0.38;
      if (!on) route.marker.setOpacity(0.38);
    });
    if (panel) {
      var buttons = panel.querySelectorAll('.mz-ops-legend button');
      for (var i = 0; i < buttons.length; i++) {
        buttons[i].classList.toggle('is-active', routes[i] && routes[i] === active);
      }
    }
  }

  function selectedHtml(tour) {
    if (!tour) {
      return '<p class="mz-ops-empty">No upcoming confirmed tours to follow.</p>';
    }
    var href = tour.detail_url || ('crm/quotation_generator.php?id=' + encodeURIComponent(tour.id || ''));
    return '<div class="mz-ops-selected is-changing">'
      + '<h4>' + esc(tour.name) + '</h4>'
      + '<p class="mz-ops-route">' + esc(tour.departure_city || 'Departure pending') + ' → ' + esc(tour.destination || 'Destination pending') + '</p>'
      + '<p class="mz-ops-meta">' + esc(tour.departure_date || 'Date not set') + '</p>'
      + '<span class="mz-ops-status">' + esc(tour.status || 'Confirmed') + '</span>'
      + (tour.id ? '<a class="mz-ops-view" href="' + esc(href) + '">View Tour</a>' : '')
      + '</div>';
  }

  function renderPanel(tour) {
    if (!panel) return;
    var legend = routes.map(function (route, index) {
      var label = route.tour.destination || route.tour.name;
      return '<li><button type="button" data-route="' + index + '">'
        + '<i class="mz-ops-swatch" style="background:' + route.color + ';color:' + route.color + '"></i>'
        + '<span>' + esc(label) + '</span></button></li>';
    }).join('');
    panel.innerHTML = '<div class="mz-ops-head"><h3>Upcoming Tours</h3><span class="mz-ops-count">' + routes.length + '</span></div>'
      + (legend ? '<ul class="mz-ops-legend">' + legend + '</ul>' : '')
      + '<div class="mz-ops-slot"></div>';
    updateSelected(tour);
  }

  function updateSelected(tour) {
    if (!panel) return;
    var slot = panel.querySelector('.mz-ops-slot');
    if (!slot) return;
    slot.innerHTML = selectedHtml(tour);
    var card = slot.querySelector('.mz-ops-selected');
    if (card) requestAnimationFrame(function () { card.classList.remove('is-changing'); });
  }

  function focusRoute(route, pin) {
    if (pin) pinned = route;
    if (active === route && !pin) return;
    active = route;
    applyFocusStyles();
    updateSelected(route.tour);
  }

  function restorePinned() {
    if (pinned && active !== pinned) focusRoute(pinned, true);
  }

  function angleDelta(from, to) {
    return ((to - from + 540) % 360) - 180;
  }

  function buildBearings(route) {
    var start = map.latLngToContainerPoint(pointAt(route, 0));
    var end = map.latLngToContainerPoint(pointAt(route, 1));
    var dist = Math.sqrt(Math.pow(end.x - start.x, 2) + Math.pow(end.y - start.y, 2));
    var steps = Math.max(8, Math.min(20, Math.round(dist / 28) || 8));
    var samples = [];
    for (var i = 0; i <= steps; i++) {
      var t1 = i / steps;
      var t2 = Math.min(1, t1 + (1 / steps));
      if (t2 <= t1) {
        t1 = Math.max(0, 1 - (1 / steps));
        t2 = 1;
      }
      var a = map.latLngToContainerPoint(pointAt(route, t1));
      var b = map.latLngToContainerPoint(pointAt(route, t2));
      samples.push(Math.atan2(b.y - a.y, b.x - a.x) * 180 / Math.PI);
    }
    for (var pass = 0; pass < 6; pass++) {
      var next = samples.slice();
      for (var n = 1; n < samples.length - 1; n++) {
        var midpoint = samples[n - 1] + angleDelta(samples[n - 1], samples[n + 1]) * 0.5;
        next[n] = samples[n] + angleDelta(samples[n], midpoint) * 0.65;
      }
      samples = next;
    }
    route.bearings = samples;
  }

  function bearingAt(route, t) {
    var samples = route.bearings;
    if (!samples || !samples.length) return null;
    var x = Math.max(0, Math.min(0.9999, t)) * (samples.length - 1);
    var i = Math.floor(x);
    var delta = angleDelta(samples[i], samples[Math.min(samples.length - 1, i + 1)]);
    return samples[i] + delta * (x - i);
  }

  function approachAngle(current, target) {
    return current + angleDelta(current, target) * 0.18;
  }

  function tick(now) {
    if (!running) return;
    if (!root.isConnected) {
      destroy();
      return;
    }
    var hidden = document.hidden;
    if (stage) stage.classList.toggle('is-paused', hidden || reducedMotion);
    routes.forEach(function (route) {
      if (!route.last) route.last = now;
      var dt = Math.min(32, now - route.last);
      route.last = now;
      if (!reducedMotion && !hidden) {
        route.t += route.speed * dt;
        if (route.t >= 1) route.t -= 1;
      }
      if (route.t < route.prevT) {
        route.trail = [];
        route.hiddenTicks = 4;
        route.angle = bearingAt(route, 0.02);
        route.opacity = 0;
        route.marker.setOpacity(0);
      }
      route.prevT = route.t;
      var pos = pointAt(route, route.t);
      route.marker.setLatLng(pos);
      route.trail.push(pos);
      if (route.trail.length > 10) route.trail.shift();
      if ((route.frame = (route.frame || 0) + 1) % 2 === 0) {
        route.trailLine.setLatLngs(route.trail);
      }

      var fade = 0.08;
      var alpha = 1;
      if (route.hiddenTicks > 0) {
        route.hiddenTicks -= 1;
        alpha = 0;
      } else if (route.t < fade) alpha = route.t / fade;
      else if (route.t > 1 - fade) alpha = (1 - route.t) / fade;
      var opacity = Math.max(0, Math.min(1, alpha)) * (route.dim || 1);
      if (route.opacity == null || Math.abs(route.opacity - opacity) > 0.03) {
        route.opacity = opacity;
        route.marker.setOpacity(opacity);
      }

      if (!route.iconEl && route.marker.getElement()) {
        route.iconEl = route.marker.getElement().querySelector('.mz-plane');
      }
      if (route.iconEl && map) {
        var target = bearingAt(route, route.t);
        if (target != null) {
          if (route.angle == null) route.angle = target;
          else if (!route.hiddenTicks) route.angle = approachAngle(route.angle, target);
          route.iconEl.style.transform = 'rotate(' + route.angle.toFixed(2) + 'deg)';
        }
      }
    });
    raf = requestAnimationFrame(tick);
  }

  function start() {
    if (running || !routes.length || reducedMotion) return;
    running = true;
    raf = requestAnimationFrame(tick);
  }

  function stop() {
    running = false;
    if (raf) cancelAnimationFrame(raf);
    raf = 0;
    if (stage) stage.classList.add('is-paused');
  }

  function onVisibility() {
    if (document.hidden) {
      stop();
      return;
    }
    routes.forEach(function (route) { route.last = 0; });
    if (stage) stage.classList.remove('is-paused');
    start();
  }

  function destroy() {
    stop();
    document.removeEventListener('visibilitychange', onVisibility);
    window.removeEventListener('pagehide', destroy);
    window.removeEventListener('resize', onResize);
    if (map) {
      map.remove();
      map = null;
    }
  }

  function onResize() {
    if (map) map.invalidateSize();
  }

  function boot() {
    renderPanel(null);
    if (!payload.ok) {
      setOverlay('Map unavailable', payload.error || 'Upcoming tours could not be loaded.', true);
      return;
    }
    if (typeof L === 'undefined') {
      setOverlay('Map unavailable', 'The map library did not load. Refresh the page to try again.', true);
      return;
    }

    map = L.map(canvas, {
      zoomControl: true,
      minZoom: 2,
      maxZoom: 6,
      worldCopyJump: false,
      scrollWheelZoom: false,
      fadeAnimation: false,
      zoomAnimation: false
    }).setView([22, 80], 3);

    L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/Canvas/World_Dark_Gray_Base/MapServer/tile/{z}/{y}/{x}', {
      attribution: 'Tiles &copy; Esri',
      maxZoom: 16
    }).addTo(map);

    var tours = Array.isArray(payload.tours) ? payload.tours : [];
    if (!tours.length) {
      var total = payload.total || 0;
      if (total === 0) {
        setOverlay('No upcoming tours', 'Confirmed tours with a future departure date will draw their routes here.');
      } else {
        setOverlay('Routes need coordinates', 'Upcoming tours were found, but their cities could not be placed on the map.');
      }
      renderPanel(null);
    } else {
      hideOverlay();
      var bounds = [];
      tours.forEach(function (tour, index) {
        drawRoute(tour, index);
        bounds.push([tour.from.lat, tour.from.lng], [tour.to.lat, tour.to.lng]);
      });
      if (bounds.length) {
        map.fitBounds(bounds, { paddingTopLeft: [320, 48], paddingBottomRight: [48, 160], maxZoom: 4 });
      }
      renderPanel(tours[0]);
      routes.forEach(buildBearings);
      focusRoute(routes[0], true);
    }

    if (panel) {
      panel.addEventListener('click', function (event) {
        var button = event.target.closest('button[data-route]');
        if (!button) return;
        var route = routes[parseInt(button.getAttribute('data-route'), 10)];
        if (route) focusRoute(route, true);
      });
      panel.addEventListener('mouseover', function (event) {
        var button = event.target.closest('button[data-route]');
        if (!button) return;
        var route = routes[parseInt(button.getAttribute('data-route'), 10)];
        if (route) focusRoute(route, false);
      });
      panel.addEventListener('mouseout', function (event) {
        if (event.target.closest('button[data-route]')) restorePinned();
      });
    }

    document.addEventListener('visibilitychange', onVisibility);
    window.addEventListener('pagehide', destroy);
    window.addEventListener('resize', onResize);
    map.on('zoomend moveend', function () {
      routes.forEach(buildBearings);
    });
    map.on('unload', stop);
    if (!reducedMotion) start();
    else if (stage) stage.classList.add('is-paused');
    setTimeout(onResize, 200);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
})();
