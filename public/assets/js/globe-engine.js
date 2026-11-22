/* ==========================================================================
   MERIDIAN — Unified Cosmic 3D Earth Globe Engine
   Powered by MapLibre GL JS v5 (Native 3D Globe Projection)
   One Single Living Earth: Free Orbital Exploration -> Seamless Street/Port Zoom
   ========================================================================== */

window.MeridianGlobe = (function () {
  let heroMap = null;
  let workspaceMap = null;
  let currentLocations = [];
  let currentShipments = [];
  let activeShipment = null;
  let activeStyleMode = 'satellite'; // 'satellite' | 'osm'
  let onShipmentSelectCb = null;
  let activePopup = null;
  let starfieldStarted = false;
  let workspaceSpinTimer = null;
  let userInteracted = false;

  /* ------------------------------------------------------------------------
     COSMIC STARFIELD BACKDROP (SUBTLE REALISTIC DEEP SPACE)
     ------------------------------------------------------------------------ */
  function initStarfield(canvasId) {
    const canvas = document.getElementById(canvasId);
    if (!canvas || starfieldStarted) return;
    starfieldStarted = true;

    const ctx = canvas.getContext('2d');
    if (!ctx) return;

    let stars = [];
    function resize() {
      canvas.width = window.innerWidth;
      canvas.height = window.innerHeight;
      const count = Math.min(280, Math.floor((canvas.width * canvas.height) / 5500));
      stars = Array.from({ length: count }, () => ({
        x: Math.random() * canvas.width,
        y: Math.random() * canvas.height,
        r: Math.random() * 1.25 + 0.25,
        baseAlpha: Math.random() * 0.55 + 0.2,
        phase: Math.random() * Math.PI * 2,
        speed: Math.random() * 0.008 + 0.003,
        tint: Math.random() > 0.8 ? '#bae6fd' : '#f8fafc',
      }));
    }

    resize();
    window.addEventListener('resize', resize);

    function draw() {
      ctx.clearRect(0, 0, canvas.width, canvas.height);
      for (let i = 0; i < stars.length; i++) {
        const s = stars[i];
        s.phase += s.speed;
        const alpha = s.baseAlpha + Math.sin(s.phase) * 0.18;
        ctx.globalAlpha = Math.max(0.08, Math.min(0.95, alpha));
        ctx.fillStyle = s.tint;
        ctx.beginPath();
        ctx.arc(s.x, s.y, s.r, 0, Math.PI * 2);
        ctx.fill();
      }
      ctx.globalAlpha = 1;
      requestAnimationFrame(draw);
    }
    requestAnimationFrame(draw);
  }

  /* ------------------------------------------------------------------------
     MAPLIBRE v5 UNIFIED 3D GLOBE STYLE (SATELLITE EARTH + LABELS + OSM LAYER)
     ------------------------------------------------------------------------ */
  function buildGlobeStyle() {
    return {
      version: 8,
      projection: { type: 'globe' },
      sky: {
        'atmosphere-blend': ['interpolate', ['linear'], ['zoom'], 0, 0.85, 5, 0.45, 7, 0],
      },
      sources: {
        'esri-satellite': {
          type: 'raster',
          tiles: [
            'https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}',
          ],
          tileSize: 256,
          maxzoom: 17,
          attribution: 'Imagery &copy; Esri, Maxar, Earthstar Geographics',
        },
        'esri-labels': {
          type: 'raster',
          tiles: [
            'https://services.arcgisonline.com/ArcGIS/rest/services/Reference/World_Boundaries_and_Places/MapServer/tile/{z}/{y}/{x}',
          ],
          tileSize: 256,
          maxzoom: 17,
        },
        'osm-tiles': {
          type: 'raster',
          tiles: ['https://tile.openstreetmap.org/{z}/{x}/{y}.png'],
          tileSize: 256,
          maxzoom: 18,
          attribution: '&copy; OpenStreetMap contributors',
        },
      },
      layers: [
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
          paint: { 'raster-opacity': 0.88 },
          layout: { visibility: activeStyleMode === 'satellite' ? 'visible' : 'none' },
        },
        {
          id: 'layer-osm',
          type: 'raster',
          source: 'osm-tiles',
          layout: { visibility: activeStyleMode === 'osm' ? 'visible' : 'none' },
        },
      ],
    };
  }

  /* ------------------------------------------------------------------------
     SMART MARITIME SEA-LANE ROUTER ([lng, lat] GeoJSON order)
     ------------------------------------------------------------------------ */
  function buildSeaCorridorCoords(origin, dest) {
    const oLng = parseFloat(origin.longitude);
    const oLat = parseFloat(origin.latitude);
    const dLng = parseFloat(dest.longitude);
    const dLat = parseFloat(dest.latitude);
    const oCode = (origin.code || '').toUpperCase();
    const dCode = (dest.code || '').toUpperCase();

    const isEastAsia = (c, lng) => ['CNSHA', 'JPTYO'].includes(c) || lng > 112;
    const isSEAsia = (c, lng) => ['IDTPR', 'IDBLW', 'IDTAK', 'SGSIN'].includes(c) || (lng >= 90 && lng <= 112);
    const isEurope = (c, lng) => ['NLRTM', 'DEHAM'].includes(c) || lng < 25;

    const pts = [[oLng, oLat]];

    if (isSEAsia(oCode, oLng) && isEurope(dCode, dLng)) {
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
    } else if (isEurope(oCode, oLng) && isSEAsia(dCode, dLng)) {
      pts.push(
        [-4.5, 49.5],
        [-9.5, 43.4],
        [-5.6, 35.9],
        [15.2, 36.2],
        [32.3, 31.2],
        [34.1, 27.6],
        [43.4, 12.4],
        [80.2, 5.9],
        [95.3, 5.8]
      );
      if (dCode !== 'SGSIN') pts.push([103.852, 1.2903]);
    } else if (isEastAsia(oCode, oLng) && isEurope(dCode, dLng)) {
      pts.push(
        [118.0, 22.0],
        [110.0, 10.0],
        [103.852, 1.2903],
        [80.2, 5.9],
        [43.4, 12.4],
        [32.3, 31.2],
        [15.2, 36.2],
        [-5.6, 35.9],
        [-4.5, 49.5]
      );
    } else if ((isSEAsia(oCode, oLng) && isEastAsia(dCode, dLng)) || (isEastAsia(oCode, oLng) && isSEAsia(dCode, dLng))) {
      const sea = [
        [103.852, 1.2903],
        [111.0, 10.5],
        [119.0, 23.2],
      ];
      if (isEastAsia(oCode, oLng)) sea.reverse();
      sea.forEach(p => {
        if ((Math.abs(p[0] - oLng) > 1 || Math.abs(p[1] - oLat) > 1) && (Math.abs(p[0] - dLng) > 1 || Math.abs(p[1] - dLat) > 1)) {
          pts.push(p);
        }
      });
    } else if (isSEAsia(oCode, oLng) && isSEAsia(dCode, dLng)) {
      if ((oCode === 'IDTPR' && dCode === 'IDBLW') || (oCode === 'IDBLW' && dCode === 'IDTPR')) {
        pts.push([103.852, 1.2903]);
      }
    }

    pts.push([dLng, dLat]);
    return pts;
  }

  /* ------------------------------------------------------------------------
     GEOJSON DATA BUILDERS FOR PORTS, ROUTES & VESSELS
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
          type: l.type,
        },
      })),
    };
  }

  function buildRoutesGeoJSON(shipments, focused) {
    const features = [];

    if (shipments && shipments.length > 0) {
      shipments.forEach(s => {
        if (!s.origin_location || !s.destination_location) return;
        const isFocused = focused && focused.id === s.id;
        let color = '#38bdf8';
        if (s.is_delayed && s.status !== 'DELIVERED') color = '#f87171';
        else if (s.status === 'DELIVERED') color = '#34d399';
        else if (s.status === 'CREATED') color = '#fbbf24';

        features.push({
          type: 'Feature',
          geometry: {
            type: 'LineString',
            coordinates: buildSeaCorridorCoords(s.origin_location, s.destination_location),
          },
          properties: {
            shipment_id: s.id,
            tracking_number: s.tracking_number,
            color,
            focused: isFocused ? 1 : 0,
          },
        });
      });
    } else {
      // Subtle ambient maritime network lines when 0 shipments registered
      const ambientPairs = [
        [{ code: 'IDTPR', longitude: 106.8836, latitude: -6.1018 }, { code: 'NLRTM', longitude: 4.4777, latitude: 51.9244 }],
        [{ code: 'CNSHA', longitude: 121.4737, latitude: 31.2304 }, { code: 'SGSIN', longitude: 103.852, latitude: 1.2903 }],
        [{ code: 'JPTYO', longitude: 139.782, latitude: 35.618 }, { code: 'CNSHA', longitude: 121.4737, latitude: 31.2304 }],
      ];
      ambientPairs.forEach(([a, b]) => {
        features.push({
          type: 'Feature',
          geometry: {
            type: 'LineString',
            coordinates: buildSeaCorridorCoords(a, b),
          },
          properties: {
            shipment_id: null,
            tracking_number: '',
            color: 'rgba(56, 189, 248, 0.35)',
            focused: 0,
          },
        });
      });
    }

    return { type: 'FeatureCollection', features };
  }

  function buildVesselsGeoJSON(shipments, focused) {
    const features = [];
    (shipments || []).forEach(s => {
      const loc = s.current_location || s.origin_location;
      if (!loc) return;
      const isFocused = focused && focused.id === s.id;
      let color = '#38bdf8';
      if (s.is_delayed && s.status !== 'DELIVERED') color = '#ef4444';
      else if (s.status === 'DELIVERED') color = '#10b981';
      else if (s.status === 'CREATED') color = '#f59e0b';

      features.push({
        type: 'Feature',
        geometry: {
          type: 'Point',
          coordinates: [parseFloat(loc.longitude), parseFloat(loc.latitude)],
        },
        properties: {
          shipment_id: s.id,
          tracking_number: s.tracking_number,
          container_number: s.container_number || '—',
          cargo: s.cargo_description || '',
          status: s.status,
          port_name: loc.name,
          carrier: s.carrier ? s.carrier.name : '',
          color,
          focused: isFocused ? 1 : 0,
        },
      });
    });

    return { type: 'FeatureCollection', features };
  }

  /* ------------------------------------------------------------------------
     ATTACH INTERACTIVE DATA LAYERS ONTO ANY MAPLIBRE GLOBE INSTANCE
     ------------------------------------------------------------------------ */
  function attachDataLayers(map, interactive) {
    map.addSource('meridian-routes', {
      type: 'geojson',
      data: buildRoutesGeoJSON(currentShipments, activeShipment),
    });

    map.addSource('meridian-ports', {
      type: 'geojson',
      data: buildPortsGeoJSON(currentLocations),
    });

    map.addSource('meridian-vessels', {
      type: 'geojson',
      data: buildVesselsGeoJSON(currentShipments, activeShipment),
    });

    // Route outer glow
    map.addLayer({
      id: 'routes-glow',
      type: 'line',
      source: 'meridian-routes',
      layout: { 'line-cap': 'round', 'line-join': 'round' },
      paint: {
        'line-color': ['get', 'color'],
        'line-width': ['case', ['==', ['get', 'focused'], 1], 9, 4],
        'line-opacity': ['case', ['==', ['get', 'focused'], 1], 0.28, 0.14],
        'line-blur': 3,
      },
    });

    // Crisp dashed sea-lane curve
    map.addLayer({
      id: 'routes-line',
      type: 'line',
      source: 'meridian-routes',
      layout: { 'line-cap': 'round', 'line-join': 'round' },
      paint: {
        'line-color': ['get', 'color'],
        'line-width': ['case', ['==', ['get', 'focused'], 1], 3.2, 1.8],
        'line-dasharray': [2.5, 2],
        'line-opacity': ['case', ['==', ['get', 'focused'], 1], 0.98, 0.72],
      },
    });

    // Connected UN/LOCODE Ports
    map.addLayer({
      id: 'ports-ring',
      type: 'circle',
      source: 'meridian-ports',
      paint: {
        'circle-radius': ['interpolate', ['linear'], ['zoom'], 1, 4, 6, 6, 12, 8],
        'circle-color': '#0b192c',
        'circle-stroke-width': 2,
        'circle-stroke-color': '#7dd3fc',
        'circle-opacity': 0.9,
      },
    });

    // Live Vessel / Shipment Position Halo
    map.addLayer({
      id: 'vessels-halo',
      type: 'circle',
      source: 'meridian-vessels',
      paint: {
        'circle-radius': ['case', ['==', ['get', 'focused'], 1], 16, 11],
        'circle-color': ['get', 'color'],
        'circle-opacity': 0.26,
      },
    });

    // Live Vessel / Shipment Core Marker
    map.addLayer({
      id: 'vessels-dot',
      type: 'circle',
      source: 'meridian-vessels',
      paint: {
        'circle-radius': ['case', ['==', ['get', 'focused'], 1], 7.5, 5.5],
        'circle-color': ['get', 'color'],
        'circle-stroke-width': 2.2,
        'circle-stroke-color': '#ffffff',
      },
    });

    if (!interactive) return;

    // Cursor cues
    ['ports-ring', 'vessels-dot', 'routes-line'].forEach(layerId => {
      map.on('mouseenter', layerId, () => { map.getCanvas().style.cursor = 'pointer'; });
      map.on('mouseleave', layerId, () => { map.getCanvas().style.cursor = ''; });
    });

    // Clicking a Port freely zooms to inspect that terminal!
    map.on('click', 'ports-ring', e => {
      const f = e.features && e.features[0];
      if (!f) return;
      const coords = f.geometry.coordinates.slice();
      const p = f.properties;
      stopAutoSpin();
      map.flyTo({ center: coords, zoom: 11.5, duration: 1500, essential: true });
      showPopup(
        map,
        coords,
        `<div class="gl-pop">
          <div class="gl-pop-kicker">${p.code} · ${p.type}</div>
          <div class="gl-pop-title">${p.name}</div>
          <div class="gl-pop-sub">${p.country} · ${Number(coords[1]).toFixed(3)}, ${Number(coords[0]).toFixed(3)}</div>
        </div>`
      );
    });

    // Clicking a Vessel or Route selects that shipment & focuses it!
    const handleFeatureSelect = e => {
      const f = e.features && e.features[0];
      if (!f || !f.properties.tracking_number) return;
      if (typeof onShipmentSelectCb === 'function') {
        onShipmentSelectCb(f.properties.tracking_number);
      }
    };
    map.on('click', 'vessels-dot', handleFeatureSelect);
    map.on('click', 'routes-line', handleFeatureSelect);
  }

  function showPopup(map, lngLat, html) {
    if (activePopup) {
      activePopup.remove();
      activePopup = null;
    }
    if (typeof window.maplibregl === 'undefined') return;
    activePopup = new window.maplibregl.Popup({
      closeButton: true,
      closeOnClick: true,
      maxWidth: '280px',
      offset: 12,
    })
      .setLngLat(lngLat)
      .setHTML(html)
      .addTo(map);
  }

  function stopAutoSpin() {
    userInteracted = true;
    if (workspaceSpinTimer) {
      cancelAnimationFrame(workspaceSpinTimer);
      workspaceSpinTimer = null;
    }
  }

  /* ------------------------------------------------------------------------
     1. HOME PAGE HERO 3D GLOBE (INTERACTIVE & FREELY ROTATABLE)
     ------------------------------------------------------------------------ */
  function initHeroGlobe(containerId, locations) {
    initStarfield('cosmic-starfield');
    currentLocations = locations || currentLocations;

    const el = document.getElementById(containerId);
    if (!el || typeof window.maplibregl === 'undefined') return;

    if (heroMap) {
      heroMap.resize();
      syncHeroSources();
      return;
    }

    heroMap = new window.maplibregl.Map({
      container: containerId,
      style: buildGlobeStyle(),
      center: [72, 16],
      zoom: 1.55,
      minZoom: 1.1,
      maxZoom: 16,
      attributionControl: false,
    });

    heroMap.on('load', () => {
      attachDataLayers(heroMap, true);
      // Gentle orbital rotation until the user grabs the planet
      let heroSpinning = true;
      heroMap.on('mousedown', () => { heroSpinning = false; });
      heroMap.on('touchstart', () => { heroSpinning = false; });
      heroMap.on('wheel', () => { heroSpinning = false; });

      function spinHero() {
        if (heroMap && heroSpinning && !document.getElementById('page-home')?.classList.contains('hidden')) {
          const c = heroMap.getCenter();
          heroMap.setCenter([c.lng + 0.08, c.lat]);
        }
        requestAnimationFrame(spinHero);
      }
      requestAnimationFrame(spinHero);
    });
  }

  function syncHeroSources() {
    if (!heroMap || !heroMap.isStyleLoaded()) return;
    heroMap.getSource('meridian-ports')?.setData(buildPortsGeoJSON(currentLocations));
    heroMap.getSource('meridian-routes')?.setData(buildRoutesGeoJSON(currentShipments, null));
    heroMap.getSource('meridian-vessels')?.setData(buildVesselsGeoJSON(currentShipments, null));
  }

  /* ------------------------------------------------------------------------
     2. WORKSPACE FULL-UNIVERSE UNIFIED 3D EARTH GLOBE
     ------------------------------------------------------------------------ */
  function initWorkspaceGlobe(containerId, locations, shipments, onSelect) {
    initStarfield('cosmic-starfield');
    currentLocations = locations || [];
    currentShipments = shipments || [];
    onShipmentSelectCb = onSelect || null;

    const el = document.getElementById(containerId);
    if (!el || typeof window.maplibregl === 'undefined') return;

    if (workspaceMap) {
      workspaceMap.resize();
      syncWorkspaceData(currentLocations, currentShipments, activeShipment);
      return;
    }

    workspaceMap = new window.maplibregl.Map({
      container: containerId,
      style: buildGlobeStyle(),
      center: [68, 18],
      zoom: 1.75,
      minZoom: 1.0,
      maxZoom: 17,
      attributionControl: false,
    });

    workspaceMap.on('load', () => {
      attachDataLayers(workspaceMap, true);

      // Stop gentle orbital drift as soon as user touches/drags/zooms freely anywhere on Earth
      ['mousedown', 'touchstart', 'wheel', 'dragstart'].forEach(ev => {
        workspaceMap.on(ev, () => stopAutoSpin());
      });

      startGentleOrbit();
    });
  }

  function startGentleOrbit() {
    userInteracted = false;
    function step() {
      if (workspaceMap && !userInteracted && !activeShipment && workspaceMap.getZoom() < 2.4) {
        const c = workspaceMap.getCenter();
        workspaceMap.setCenter([c.lng + 0.045, c.lat]);
        workspaceSpinTimer = requestAnimationFrame(step);
      }
    }
    if (workspaceSpinTimer) cancelAnimationFrame(workspaceSpinTimer);
    workspaceSpinTimer = requestAnimationFrame(step);
  }

  function syncWorkspaceData(locations, shipments, focused) {
    currentLocations = locations || currentLocations;
    currentShipments = shipments || [];
    activeShipment = focused || null;

    if (workspaceMap && workspaceMap.isStyleLoaded()) {
      workspaceMap.getSource('meridian-ports')?.setData(buildPortsGeoJSON(currentLocations));
      workspaceMap.getSource('meridian-routes')?.setData(buildRoutesGeoJSON(currentShipments, activeShipment));
      workspaceMap.getSource('meridian-vessels')?.setData(buildVesselsGeoJSON(currentShipments, activeShipment));
    }
    syncHeroSources();
  }

  /* ------------------------------------------------------------------------
     3. SEAMLESS ZOOM ON THE EXACT SAME 3D GLOBE (ZERO MAP SWITCHING!)
     ------------------------------------------------------------------------ */
  function focusShipment(shipment) {
    if (!shipment || !shipment.origin_location || !shipment.destination_location) return;
    activeShipment = shipment;
    stopAutoSpin();

    if (!workspaceMap) return;
    syncWorkspaceData(currentLocations, currentShipments, activeShipment);

    const coords = buildSeaCorridorCoords(shipment.origin_location, shipment.destination_location);
    const bounds = coords.reduce(
      (b, c) => b.extend(c),
      new window.maplibregl.LngLatBounds(coords[0], coords[0])
    );

    const panelsHidden = document.getElementById('workspace-shell')?.classList.contains('panels-collapsed');
    const sidePad = (!panelsHidden && window.innerWidth > 1100) ? 420 : 60;

    // Smooth continuous fly-in ON THE EXACT SAME 3D EARTH GLOBE!
    workspaceMap.fitBounds(bounds, {
      padding: { top: 100, bottom: 96, left: sidePad, right: sidePad },
      maxZoom: 7.5,
      duration: 1850,
      essential: true,
    });

    const cur = shipment.current_location || shipment.origin_location;
    const curCoords = [parseFloat(cur.longitude), parseFloat(cur.latitude)];
    setTimeout(() => {
      if (workspaceMap && activeShipment && activeShipment.id === shipment.id) {
        showPopup(
          workspaceMap,
          curCoords,
          `<div class="gl-pop">
            <div class="gl-pop-kicker">${shipment.tracking_number} · ${shipment.status}</div>
            <div class="gl-pop-title">${cur.name}</div>
            <div class="gl-pop-sub">${shipment.container_number || 'Kontainer terdaftar'} · ${shipment.carrier ? shipment.carrier.name : ''}</div>
          </div>`
        );
      }
    }, 1100);
  }

  function zoomToPort(lat, lng, title, code) {
    if (!workspaceMap) return;
    stopAutoSpin();
    workspaceMap.flyTo({
      center: [parseFloat(lng), parseFloat(lat)],
      zoom: 12.5,
      pitch: 32,
      duration: 1650,
      essential: true,
    });
    showPopup(
      workspaceMap,
      [parseFloat(lng), parseFloat(lat)],
      `<div class="gl-pop">
        <div class="gl-pop-kicker">${code || 'TERMINAL'}</div>
        <div class="gl-pop-title">${title || 'Pelabuhan'}</div>
        <div class="gl-pop-sub">${Number(lat).toFixed(4)}, ${Number(lng).toFixed(4)}</div>
      </div>`
    );
  }

  function resetGlobe() {
    activeShipment = null;
    if (activePopup) {
      activePopup.remove();
      activePopup = null;
    }
    syncWorkspaceData(currentLocations, currentShipments, null);

    if (workspaceMap) {
      workspaceMap.flyTo({
        center: [68, 18],
        zoom: 1.75,
        pitch: 0,
        bearing: 0,
        duration: 1400,
        essential: true,
      });
      setTimeout(() => startGentleOrbit(), 1500);
    }
  }

  function setLayerMode(mode) {
    activeStyleMode = mode === 'osm' ? 'osm' : 'satellite';
    [workspaceMap, heroMap].forEach(m => {
      if (!m || !m.isStyleLoaded()) return;
      m.setLayoutProperty('layer-satellite', 'visibility', activeStyleMode === 'satellite' ? 'visible' : 'none');
      m.setLayoutProperty('layer-satellite-labels', 'visibility', activeStyleMode === 'satellite' ? 'visible' : 'none');
      m.setLayoutProperty('layer-osm', 'visibility', activeStyleMode === 'osm' ? 'visible' : 'none');
    });

    document.getElementById('btn-layer-sat')?.classList.toggle('active', activeStyleMode === 'satellite');
    document.getElementById('btn-layer-osm')?.classList.toggle('active', activeStyleMode === 'osm');
  }

  function zoomIn() {
    if (workspaceMap) { stopAutoSpin(); workspaceMap.zoomIn({ duration: 400 }); }
  }

  function zoomOut() {
    if (workspaceMap) { stopAutoSpin(); workspaceMap.zoomOut({ duration: 400 }); }
  }

  function resizeAll() {
    if (workspaceMap) workspaceMap.resize();
    if (heroMap) heroMap.resize();
  }

  return {
    initStarfield,
    initHeroGlobe,
    initWorkspaceGlobe,
    syncWorkspaceData,
    focusShipment,
    zoomToPort,
    resetGlobe,
    zoomIn,
    zoomOut,
    setLayerMode,
    resizeAll,
  };
})();
