/* ==========================================================================
   MERIDIAN — Multimodal 3D Earth & Route Animation Engine
   MapLibre GL JS v5 · Daylight OSM / Esri Satellite
   Animated Vehicle Markers: Truk Darat Cabang -> Kapal Mitra (Laut) / Pesawat Mitra (Udara)
   ========================================================================== */

window.MeridianGlobe = (function () {
  let heroMap = null;
  let workspaceMap = null;
  let currentLocations = [];
  let currentShipments = [];
  let activeShipment = null;
  let activeStyleMode = 'osm'; // 'osm' (Daylight clean default) | 'satellite'
  let onShipmentSelectCb = null;
  let activePopup = null;
  let vehicleMarkers = new Map(); // shipmentId -> { marker, el, currentPct, targetPct, pathCoords, mode }
  let animRafId = null;

  function buildGlobeStyle() {
    return {
      version: 8,
      projection: { type: 'globe' },
      sky: {
        'sky-color': '#dbeafe',
        'horizon-color': '#f1f5f9',
        'atmosphere-blend': ['interpolate', ['linear'], ['zoom'], 0, 0.7, 5, 0.35, 7, 0],
      },
      sources: {
        'osm-tiles': {
          type: 'raster',
          tiles: ['https://tile.openstreetmap.org/{z}/{x}/{y}.png'],
          tileSize: 256,
          maxzoom: 18,
          attribution: '&copy; OpenStreetMap contributors',
        },
        'esri-satellite': {
          type: 'raster',
          tiles: [
            'https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}',
          ],
          tileSize: 256,
          maxzoom: 17,
          attribution: 'Imagery &copy; Esri, Maxar',
        },
        'esri-labels': {
          type: 'raster',
          tiles: [
            'https://services.arcgisonline.com/ArcGIS/rest/services/Reference/World_Boundaries_and_Places/MapServer/tile/{z}/{y}/{x}',
          ],
          tileSize: 256,
          maxzoom: 17,
        },
      },
      layers: [
        {
          id: 'layer-osm',
          type: 'raster',
          source: 'osm-tiles',
          layout: { visibility: activeStyleMode === 'osm' ? 'visible' : 'none' },
        },
        {
          id: 'layer-satellite',
          type: 'raster',
          source: 'esri-satellite',
          layout: { visibility: activeStyleMode === 'satellite' ? 'visible' : 'none' },
        },
        {
          id: 'layer-satellite-labels',
          type: 'raster',
          source: 'esri-labels',
          paint: { 'raster-opacity': 0.9 },
          layout: { visibility: activeStyleMode === 'satellite' ? 'visible' : 'none' },
        },
      ],
    };
  }

  /* ------------------------------------------------------------------------
     MULTIMODAL PATH BUILDERS (LAND TRUCK / SEA CORRIDOR / AIR GREAT-CIRCLE)
     ------------------------------------------------------------------------ */
  function buildAirArcCoords(origin, dest, steps = 36) {
    const oLng = parseFloat(origin.longitude);
    const oLat = parseFloat(origin.latitude);
    const dLng = parseFloat(dest.longitude);
    const dLat = parseFloat(dest.latitude);

    // Handle antimeridian cleanly for Indonesia -> USA flights (go eastward across Pacific or shortest path)
    let endLng = dLng;
    if (Math.abs(endLng - oLng) > 180) {
      endLng = endLng < oLng ? endLng + 360 : endLng - 360;
    }

    const pts = [];
    for (let i = 0; i <= steps; i++) {
      const t = i / steps;
      const lng = oLng + (endLng - oLng) * t;
      const baseLat = oLat + (dLat - oLat) * t;
      const arcLift = Math.sin(Math.PI * t) * Math.min(18, Math.abs(endLng - oLng) * 0.14);
      const normLng = lng > 180 ? lng - 360 : (lng < -180 ? lng + 360 : lng);
      pts.push([normLng, Math.max(-80, Math.min(82, baseLat + arcLift))]);
    }
    return pts;
  }

  function buildSeaCorridorCoords(origin, dest) {
    const oLng = parseFloat(origin.longitude);
    const oLat = parseFloat(origin.latitude);
    const dLng = parseFloat(dest.longitude);
    const dLat = parseFloat(dest.latitude);
    const oCode = (origin.code || '').toUpperCase();
    const dCode = (dest.code || '').toUpperCase();

    const isIndonesiaOrSG = (c, lng) =>
      ['IDTPR', 'IDBLW', 'IDPNK', 'IDMAK', 'IDDJJ', 'IDKNO', 'IDCGK', 'WIOO', 'IDUPG', 'WAJJ', 'SGSIN'].includes(c) ||
      (lng >= 94 && lng <= 142 && Math.abs(oLat) < 12);
    const isEurope = (c, lng) => ['NLRTM', 'DEHAM'].includes(c) || (lng >= -12 && lng <= 25 && dLat > 30);
    const isUSA = (c, lng) => ['USLAX', 'USJFK'].includes(c) || lng < -50;
    const isEastAsia = (c, lng) => ['CNSHA', 'JPTYO'].includes(c);

    const pts = [[oLng, oLat]];

    if (isIndonesiaOrSG(oCode, oLng) && isUSA(dCode, dLng)) {
      // Trans-Pacific corridor from Indonesia to US West Coast (Los Angeles)
      if (oLng < 115) pts.push([115.0, -3.5]);
      pts.push(
        [128.0, 4.0],
        [142.0, 18.0],
        [165.0, 28.0],
        [179.5, 32.0],
        [-179.5, 32.0],
        [-152.0, 34.0],
        [-132.0, 34.0]
      );
    } else if (isIndonesiaOrSG(oCode, oLng) && isEurope(dCode, dLng)) {
      if (oCode !== 'SGSIN') pts.push([103.852, 1.2903]);
      pts.push(
        [95.3, 5.8],
        [80.2, 5.9],
        [43.4, 12.4],
        [34.1, 27.6],
        [32.3, 31.2],
        [15.2, 36.2],
        [-5.6, 35.9],
        [-9.5, 43.4],
        [-4.5, 49.5]
      );
    } else if (isIndonesiaOrSG(oCode, oLng) && isEastAsia(dCode, dLng)) {
      if (oCode !== 'SGSIN' && oLng < 112) pts.push([103.852, 1.2903]);
      pts.push([113.0, 10.5], [120.0, 23.0]);
    } else if (isIndonesiaOrSG(oCode, oLng) && isIndonesiaOrSG(dCode, dLng)) {
      // Inter-island Java Sea / Makassar Strait routing
      pts.push([(oLng + dLng) / 2, ((oLat + dLat) / 2) - 0.8]);
    }

    pts.push([dLng, dLat]);
    return pts;
  }

  function buildFullShipmentPath(s) {
    const mode = (s.transport_mode || s.carrier?.transport_mode || 'SEA').toUpperCase();
    const origin = s.origin_location;
    const dest = s.destination_location;
    if (!origin || !dest) return { land: [], main: [], combined: [], mode };

    const land = [];
    if (s.pickup_latitude && s.pickup_longitude) {
      land.push(
        [parseFloat(s.pickup_longitude), parseFloat(s.pickup_latitude)],
        [parseFloat(origin.longitude), parseFloat(origin.latitude)]
      );
    }

    const main = mode === 'AIR'
      ? buildAirArcCoords(origin, dest)
      : buildSeaCorridorCoords(origin, dest);

    const combined = land.length >= 2 ? [land[0], ...main] : main;
    return { land, main, combined, mode };
  }

  /* Interpolate a point along a polyline at progress t in [0, 1] */
  function pointAlongPath(coords, t) {
    if (!coords || coords.length === 0) return [106.88, -6.10];
    if (coords.length === 1 || t <= 0) return coords[0];
    if (t >= 1) return coords[coords.length - 1];

    const segLengths = [];
    let total = 0;
    for (let i = 0; i < coords.length - 1; i++) {
      let dx = coords[i + 1][0] - coords[i][0];
      // Handle antimeridian jump cleanly
      if (Math.abs(dx) > 180) dx = dx > 0 ? dx - 360 : dx + 360;
      const dy = coords[i + 1][1] - coords[i][1];
      const len = Math.hypot(dx, dy);
      segLengths.push(len);
      total += len;
    }
    if (total === 0) return coords[0];

    const target = total * t;
    let acc = 0;
    for (let i = 0; i < segLengths.length; i++) {
      const len = segLengths[i];
      if (acc + len >= target) {
        const localT = len === 0 ? 0 : (target - acc) / len;
        let x1 = coords[i][0];
        let x2 = coords[i + 1][0];
        if (Math.abs(x2 - x1) > 180) {
          x2 = x2 < x1 ? x2 + 360 : x2 - 360;
        }
        let lng = x1 + (x2 - x1) * localT;
        if (lng > 180) lng -= 360;
        if (lng < -180) lng += 360;
        const lat = coords[i][1] + (coords[i + 1][1] - coords[i][1]) * localT;
        return [lng, lat];
      }
      acc += len;
    }
    return coords[coords.length - 1];
  }

  /* Crisp SVG Vehicle Icons (Truk Darat / Kapal Laut / Pesawat Udara) */
  function getVehicleSvg(visualMode) {
    if (visualMode === 'LAND') {
      // Truk Armada Cabang
      return `<svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="5" width="14" height="11" rx="1.5"/><path d="M15 9h4l3 3v4h-7V9z"/><circle cx="5.5" cy="18" r="2"/><circle cx="18.5" cy="18" r="2"/></svg>`;
    }
    if (visualMode === 'AIR') {
      // Pesawat Kargo
      return `<svg width="19" height="19" viewBox="0 0 24 24" fill="currentColor"><path d="M21 16v-2l-8-5V3.5a1.5 1.5 0 0 0-3 0V9l-8 5v2l8-2.5V19l-2 1.5V22l3.5-1 3.5 1v-1.5L13 19v-5.5l8 2.5z"/></svg>`;
    }
    // Kapal Kontainer Laut
    return `<svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 17l2 3h14l2-3-9-4-9 4z"/><path d="M5 14V8h14v6"/><path d="M9 8V5h6v3"/></svg>`;
  }

  /* ------------------------------------------------------------------------
     GEOJSON BUILDERS FOR PORTS/AIRPORTS & MULTIMODAL ROUTES
     ------------------------------------------------------------------------ */
  function buildPortsGeoJSON(locations) {
    return {
      type: 'FeatureCollection',
      features: (locations || []).map(l => ({
        type: 'Feature',
        geometry: {
          type: 'Point',
          coordinates: [parseFloat(l.longitude), parseFloat(l.latitude)],
        },
        properties: {
          id: l.id,
          code: l.code,
          name: l.name,
          country: l.country,
          island: l.island || '',
          type: l.type,
          dotColor: l.type === 'AIRPORT' ? '#4f46e5' : '#0284c7',
        },
      })),
    };
  }

  function buildRoutesGeoJSON(shipments, focused) {
    const seaFeatures = [];
    const airFeatures = [];
    const landFeatures = [];

    if (shipments && shipments.length > 0) {
      shipments.forEach(s => {
        const { land, main, mode } = buildFullShipmentPath(s);
        if (land.length >= 2) {
          landFeatures.push({
            type: 'Feature',
            geometry: { type: 'LineString', coordinates: land },
            properties: { shipment_id: s.id, tracking_number: s.tracking_number },
          });
        }
        if (main.length >= 2) {
          const isFocused = focused && focused.id === s.id;
          let color = mode === 'AIR' ? '#4f46e5' : '#0284c7';
          if (s.is_delayed && s.status !== 'DELIVERED') color = '#dc2626';
          else if (s.status === 'DELIVERED') color = '#059669';
          else if (s.status === 'CREATED') color = '#d97706';

          const feat = {
            type: 'Feature',
            geometry: { type: 'LineString', coordinates: main },
            properties: {
              shipment_id: s.id,
              tracking_number: s.tracking_number,
              color,
              focused: isFocused ? 1 : 0,
            },
          };
          if (mode === 'AIR') airFeatures.push(feat);
          else seaFeatures.push(feat);
        }
      });
    } else {
      // Ambient 5-island Indonesia to World network preview when 0 shipments exist
      const ambientSea = [
        [{ code: 'IDBLW', longitude: 98.6881, latitude: 3.7836 }, { code: 'NLRTM', longitude: 4.4777, latitude: 51.9244 }],
        [{ code: 'IDTPR', longitude: 106.8836, latitude: -6.1018 }, { code: 'USLAX', longitude: -118.272, latitude: 33.7405 }],
        [{ code: 'IDMAK', longitude: 119.4, latitude: -5.1166 }, { code: 'JPTYO', longitude: 139.782, latitude: 35.618 }],
        [{ code: 'IDPNK', longitude: 109.332, latitude: -0.016 }, { code: 'SGSIN', longitude: 103.852, latitude: 1.2903 }],
      ];
      ambientSea.forEach(([a, b]) => {
        seaFeatures.push({
          type: 'Feature',
          geometry: { type: 'LineString', coordinates: buildSeaCorridorCoords(a, b) },
          properties: { color: '#0284c7', focused: 0 },
        });
      });

      const ambientAir = [
        [{ code: 'IDKNO', longitude: 98.8853, latitude: 3.6422 }, { code: 'USJFK', longitude: -73.7781, latitude: 40.6413 }],
        [{ code: 'IDCGK', longitude: 106.6558, latitude: -6.1256 }, { code: 'USLAX', longitude: -118.272, latitude: 33.7405 }],
      ];
      ambientAir.forEach(([a, b]) => {
        airFeatures.push({
          type: 'Feature',
          geometry: { type: 'LineString', coordinates: buildAirArcCoords(a, b) },
          properties: { color: '#4f46e5', focused: 0 },
        });
      });
    }

    return {
      sea: { type: 'FeatureCollection', features: seaFeatures },
      air: { type: 'FeatureCollection', features: airFeatures },
      land: { type: 'FeatureCollection', features: landFeatures },
    };
  }

  function attachDataLayers(map, interactive) {
    map.addSource('meridian-sea-routes', { type: 'geojson', data: { type: 'FeatureCollection', features: [] } });
    map.addSource('meridian-air-routes', { type: 'geojson', data: { type: 'FeatureCollection', features: [] } });
    map.addSource('meridian-land-routes', { type: 'geojson', data: { type: 'FeatureCollection', features: [] } });
    map.addSource('meridian-ports', { type: 'geojson', data: { type: 'FeatureCollection', features: [] } });

    // 1. Land truck pickup corridor (orange dashed)
    map.addLayer({
      id: 'layer-land-routes',
      type: 'line',
      source: 'meridian-land-routes',
      paint: {
        'line-color': '#ea580c',
        'line-width': 3.5,
        'line-dasharray': [2, 1.5],
      },
    });

    // 2. Sea maritime corridor (solid blue/status)
    map.addLayer({
      id: 'layer-sea-routes',
      type: 'line',
      source: 'meridian-sea-routes',
      layout: { 'line-cap': 'round', 'line-join': 'round' },
      paint: {
        'line-color': ['coalesce', ['get', 'color'], '#0284c7'],
        'line-width': ['case', ['==', ['get', 'focused'], 1], 4, 2.4],
        'line-opacity': ['case', ['==', ['get', 'focused'], 1], 0.95, 0.55],
      },
    });

    // 3. Air cargo arc (indigo dashed)
    map.addLayer({
      id: 'layer-air-routes',
      type: 'line',
      source: 'meridian-air-routes',
      layout: { 'line-cap': 'round' },
      paint: {
        'line-color': ['coalesce', ['get', 'color'], '#4f46e5'],
        'line-width': ['case', ['==', ['get', 'focused'], 1], 3.8, 2.2],
        'line-dasharray': [3, 2],
        'line-opacity': ['case', ['==', ['get', 'focused'], 1], 0.95, 0.55],
      },
    });

    // 4. Ports & Airports dots (clean clickable pins; OSM tiles provide readable place labels without requiring external glyph fonts)
    map.addLayer({
      id: 'layer-ports-dot',
      type: 'circle',
      source: 'meridian-ports',
      paint: {
        'circle-radius': ['interpolate', ['linear'], ['zoom'], 1, 5.5, 6, 8],
        'circle-color': ['coalesce', ['get', 'dotColor'], '#0284c7'],
        'circle-stroke-width': 2.2,
        'circle-stroke-color': '#ffffff',
      },
    });

    if (interactive) {
      map.on('mouseenter', 'layer-ports-dot', () => { map.getCanvas().style.cursor = 'pointer'; });
      map.on('mouseleave', 'layer-ports-dot', () => { map.getCanvas().style.cursor = ''; });
      map.on('click', 'layer-ports-dot', e => {
        const f = e.features && e.features[0];
        if (!f) return;
        const p = f.properties;
        const coords = f.geometry.coordinates.slice();
        const tipe = p.type === 'AIRPORT' ? 'Bandara Kargo Mitra' : 'Pelabuhan Kapal Mitra';
        showPopup(map, coords, `
          <div class="gl-pop-kicker">${p.code} &middot; ${tipe}</div>
          <div class="gl-pop-title">${p.name}</div>
          <div class="gl-pop-sub">${p.island ? 'Pulau ' + p.island + ' · ' : ''}${p.country}</div>
        `);
      });
    }
  }

  function showPopup(map, coords, html) {
    if (activePopup) activePopup.remove();
    activePopup = new maplibregl.Popup({ closeButton: true, offset: 14, maxWidth: '280px' })
      .setLngLat(coords)
      .setHTML(html)
      .addTo(map);
  }

  /* ------------------------------------------------------------------------
     ANIMATED MULTIMODAL VEHICLE MARKERS (TRUCK -> SHIP / AIRPLANE)
     ------------------------------------------------------------------------ */
  function syncAnimatedVehicles(map, shipments, focused) {
    if (!map) return;

    const validIds = new Set();

    (shipments || []).forEach(s => {
      const { land, main, combined, mode } = buildFullShipmentPath(s);
      if (combined.length === 0) return;

      validIds.add(s.id);
      const pct = Math.max(0, Math.min(100, Number(s.travel_progress_pct ?? 20))) / 100;

      // Determine current active vehicle icon (Truck on land pickup leg, then Ship or Airplane!)
      let visualMode = mode;
      let activePath = main;
      let pathProgress = pct;

      if (land.length >= 2 && (['CREATED', 'BOOKED', 'CONFIRMED', 'PICKED_UP'].includes(s.status) || pct <= 0.25)) {
        visualMode = 'LAND';
        activePath = land;
        pathProgress = s.status === 'CREATED' ? 0.15 : (s.status === 'CONFIRMED' ? 0.45 : (s.status === 'PICKED_UP' ? 0.85 : Math.min(1, pct * 4)));
      } else {
        visualMode = mode === 'AIR' ? 'AIR' : (mode === 'LAND' ? 'LAND' : 'SEA');
        activePath = main;
        pathProgress = land.length >= 2 ? Math.max(0.05, (pct - 0.2) / 0.8) : pct;
      }

      const modeBadgeClass = s.status === 'DELIVERED'
        ? 'mode-done'
        : (s.is_delayed ? 'mode-delayed' : (visualMode === 'AIR' ? 'mode-air' : (visualMode === 'LAND' ? 'mode-land' : 'mode-sea')));

      const modeText = visualMode === 'LAND' ? 'TRUK' : (visualMode === 'AIR' ? 'PESAWAT' : 'KAPAL');
      const daysLabel = s.simulated_days_elapsed > 0 ? ` · H+${s.simulated_days_elapsed}` : '';

      let entry = vehicleMarkers.get(s.id);
      if (!entry) {
        const el = document.createElement('div');
        el.className = 'vehicle-marker';
        el.innerHTML = `
          <div class="vehicle-badge ${modeBadgeClass}">${getVehicleSvg(visualMode)}</div>
          <div class="vehicle-label">${modeText} · ${s.tracking_number}${daysLabel}</div>
        `;
        el.addEventListener('click', () => {
          if (onShipmentSelectCb) onShipmentSelectCb(s.id);
          const pos = pointAlongPath(activePath, pathProgress);
          showPopup(map, pos, `
            <div class="gl-pop-kicker">${s.tracking_number} &middot; ${modeText}</div>
            <div class="gl-pop-title">${s.cargo_description || 'Pengiriman Kargo'}</div>
            <div class="gl-pop-sub">Progres perjalanan: ${Math.round(pct * 100)}%${daysLabel}</div>
          `);
        });

        const startCoords = pointAlongPath(activePath, pathProgress);
        const marker = new maplibregl.Marker({ element: el, anchor: 'center' })
          .setLngLat(startCoords)
          .addTo(map);

        entry = { marker, el, currentPct: pathProgress, targetPct: pathProgress, pathCoords: activePath, visualMode };
        vehicleMarkers.set(s.id, entry);
      } else {
        entry.el.innerHTML = `
          <div class="vehicle-badge ${modeBadgeClass}">${getVehicleSvg(visualMode)}</div>
          <div class="vehicle-label">${modeText} · ${s.tracking_number}${daysLabel}</div>
        `;
        entry.pathCoords = activePath;
        entry.targetPct = pathProgress;
        if (entry.visualMode !== visualMode) {
          entry.visualMode = visualMode;
          entry.currentPct = Math.max(0, pathProgress - 0.12);
        }
      }
    });

    // Remove markers for deleted/unloaded shipments
    for (const [id, entry] of vehicleMarkers.entries()) {
      if (!validIds.has(id)) {
        entry.marker.remove();
        vehicleMarkers.delete(id);
      }
    }

    startVehicleGlideLoop();
  }

  function startVehicleGlideLoop() {
    if (animRafId) return;
    function step() {
      let moving = false;
      vehicleMarkers.forEach(entry => {
        const diff = entry.targetPct - entry.currentPct;
        if (Math.abs(diff) > 0.002) {
          entry.currentPct += diff * 0.08;
          moving = true;
        } else {
          entry.currentPct = entry.targetPct;
        }
        const coords = pointAlongPath(entry.pathCoords, entry.currentPct);
        entry.marker.setLngLat(coords);
      });
      if (moving) {
        animRafId = requestAnimationFrame(step);
      } else {
        animRafId = null;
      }
    }
    animRafId = requestAnimationFrame(step);
  }

  function pushDataToMap(map, locations, shipments, focused, withVehicles) {
    if (!map || !map.isStyleLoaded()) return;
    const routes = buildRoutesGeoJSON(shipments, focused);
    map.getSource('meridian-sea-routes')?.setData(routes.sea);
    map.getSource('meridian-air-routes')?.setData(routes.air);
    map.getSource('meridian-land-routes')?.setData(routes.land);
    map.getSource('meridian-ports')?.setData(buildPortsGeoJSON(locations));

    if (withVehicles) {
      syncAnimatedVehicles(map, shipments, focused);
    }
  }

  /* ------------------------------------------------------------------------
     PUBLIC API
     ------------------------------------------------------------------------ */
  function initHeroGlobe(containerId, locations) {
    const el = document.getElementById(containerId);
    if (!el || typeof maplibregl === 'undefined') return;
    currentLocations = locations || currentLocations;

    if (heroMap) {
      pushDataToMap(heroMap, currentLocations, [], null, false);
      heroMap.resize();
      return;
    }

    heroMap = new maplibregl.Map({
      container: containerId,
      style: buildGlobeStyle(),
      center: [115, -1.5],
      zoom: 2.5,
      attributionControl: false,
    });

    heroMap.on('load', () => {
      attachDataLayers(heroMap, true);
      pushDataToMap(heroMap, currentLocations, [], null, false);
    });
  }

  function initWorkspaceGlobe(containerId, onSelectShipment) {
    const el = document.getElementById(containerId);
    if (!el || typeof maplibregl === 'undefined') return;
    onShipmentSelectCb = onSelectShipment;

    if (workspaceMap) {
      setTimeout(() => {
        workspaceMap.resize();
        pushDataToMap(workspaceMap, currentLocations, currentShipments, activeShipment, true);
      }, 60);
      return;
    }

    workspaceMap = new maplibregl.Map({
      container: containerId,
      style: buildGlobeStyle(),
      center: [115, 2],
      zoom: 2.6,
      attributionControl: false,
    });

    workspaceMap.addControl(new maplibregl.NavigationControl({ visualizePitch: true }), 'top-right');

    workspaceMap.on('load', () => {
      attachDataLayers(workspaceMap, true);
      pushDataToMap(workspaceMap, currentLocations, currentShipments, activeShipment, true);
    });
  }

  function syncWorkspaceData(locations, shipments, focused) {
    currentLocations = locations || currentLocations;
    currentShipments = shipments || [];
    activeShipment = focused || null;
    if (heroMap) pushDataToMap(heroMap, currentLocations, [], null, false);
    if (workspaceMap) pushDataToMap(workspaceMap, currentLocations, currentShipments, activeShipment, true);
  }

  function focusShipment(s) {
    activeShipment = s || null;
    if (!workspaceMap || !s) return;
    pushDataToMap(workspaceMap, currentLocations, currentShipments, activeShipment, true);

    const { combined } = buildFullShipmentPath(s);
    if (combined.length >= 2) {
      const targetPt = pointAlongPath(combined, Math.max(0.15, (Number(s.travel_progress_pct ?? 30) / 100)));
      workspaceMap.flyTo({ center: targetPt, zoom: 3.4, duration: 1100, essential: true });
    }
  }

  function zoomToPort(lat, lng, name, code) {
    if (!workspaceMap) return;
    const coords = [parseFloat(lng), parseFloat(lat)];
    workspaceMap.flyTo({ center: coords, zoom: 7.5, pitch: 25, duration: 1200, essential: true });
    showPopup(workspaceMap, coords, `
      <div class="gl-pop-kicker">${code}</div>
      <div class="gl-pop-title">${name}</div>
    `);
  }

  function setLayerMode(mode) {
    activeStyleMode = mode === 'satellite' ? 'satellite' : 'osm';
    document.getElementById('btn-layer-sat')?.classList.toggle('btn-primary', activeStyleMode === 'satellite');
    document.getElementById('btn-layer-sat')?.classList.toggle('btn-outline', activeStyleMode !== 'satellite');
    document.getElementById('btn-layer-osm')?.classList.toggle('btn-primary', activeStyleMode === 'osm');
    document.getElementById('btn-layer-osm')?.classList.toggle('btn-outline', activeStyleMode !== 'osm');

    [heroMap, workspaceMap].forEach(m => {
      if (!m || !m.isStyleLoaded()) return;
      m.setLayoutProperty('layer-osm', 'visibility', activeStyleMode === 'osm' ? 'visible' : 'none');
      m.setLayoutProperty('layer-satellite', 'visibility', activeStyleMode === 'satellite' ? 'visible' : 'none');
      m.setLayoutProperty('layer-satellite-labels', 'visibility', activeStyleMode === 'satellite' ? 'visible' : 'none');
    });
  }

  function resetView() {
    if (!workspaceMap) return;
    if (activePopup) activePopup.remove();
    workspaceMap.flyTo({ center: [115, 2], zoom: 2.5, pitch: 0, bearing: 0, duration: 900 });
  }

  function resizeAll() {
    if (heroMap) heroMap.resize();
    if (workspaceMap) workspaceMap.resize();
  }

  return {
    initHeroGlobe,
    initWorkspaceGlobe,
    syncWorkspaceData,
    focusShipment,
    zoomToPort,
    setLayerMode,
    resetView,
    resizeAll,
  };
})();
