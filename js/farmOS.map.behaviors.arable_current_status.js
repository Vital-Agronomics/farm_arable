(function ($) {
  farmOS.map.behaviors.arable_current_status = {
    attach: function (instance) {

      const devices = Drupal.settings.farm_map.behaviors.arable_current_status.devices;
      const units = Drupal.settings.farm_map.behaviors.arable_current_status.units;

      // Bail if no devices were provided.
      if (!devices) {
        return;
      }

      let promises = [];
      Object.values(devices).forEach( device => {
        const apiKey = device.sensor_settings.api_key;
        const deviceName = device.sensor_settings.device.name;

        // Build promise to query device info and latest data.
        const data = Promise.all([
          deviceInfo(apiKey, deviceName),
          latestDeviceData(apiKey, deviceName, units)
        ])
          .then(data => { return {'info': data[0], 'data': data[1]} });

        // Add promise to list of promises.
        promises.push(data);
      });

      Promise.all(promises).then(devices => {

        // Build list of features.
        let features = [];
        devices.forEach(device => {

          // Save values from API responses.
          const info = device.info;
          const data = device.data;

          // Check if location info exists.
          if (info.current_location && info.current_location.gps) {
            const gps = info.current_location.gps;

            // Values to display in popup.
            const values = {
              'Signal Strength': info.signal_strength,
              'Battery Percentage': info.batt_pct + '%',
              'Last Seen': new Date(info.last_seen).toLocaleString(),
              'Last Post': new Date(info.last_post).toLocaleString(),
            }

            // Build description HTML.
            let description = "<p>";
            for (const [key, value] of Object.entries(values)) {
              description += `<strong>${key}: </strong>${value}<br>`;
            }
            description += '</p>';

            // Add to list of features.
            features.push({
              type: "Feature",
              geometry: {type: "Point", coordinates: [gps[0], gps[1]]},
              properties: {name: info.name, description, info, data},
            });
          }
        });

        // SVG template.
        const iconTemplate = '<svg xmlns="http://www.w3.org/2000/svg" width="45" height="45" x="0px" y="0px" viewBox="0 0 100 100"><polygon fill="#ffed91" points="12.5,5 12.5,80 35,80 50,95 65,80 87.5,80 87.5,5 "></polygon><text x="50" y="50" font-size="30" font-family="sans-serif" font-weight="bold" text-anchor="middle" fill="dark-grey">${text}</text></svg>';

        var styleCache = {};
        function styleFunction (feature, resolution, style) {

          // Get feature data.
          const data = feature.get('data', {});

          // Build a text value.
          let text_value = '--';

          // Check if temp data is available.
          if (data.tair) {
            text_value = Math.round(data.tair) + '&#176;';
          }

          // Return from style cache if exists.
          if (styleCache[text_value]) {
            return styleCache[text_value];
          }

          // Build icon svg.
          const iconSvg = iconTemplate.replace('${text}', text_value);

          // Build style.
          const iconStyle = new style.Style({
            image: new style.Icon({
              opacity: 1,
              src: 'data:image/svg+xml;charset=utf-8,' + encodeURIComponent(iconSvg),
            })
          });

          // Save style to cache.
          styleCache[text_value] = iconStyle;

          return iconStyle;
        }

        // Assemble all features into one geojson.
        const geojson = {
          type: "FeatureCollection",
          features,
        }

        // Add features as geojson layer.
        var opts = {
          title: 'Arable Sensors',
          color: 'yellow',
          geojson,
          styleFunction,
        };
        instance.addLayer('geojson', opts);
      })
    },

    // Display above other layers.
    weight: 100,
  };

  // Helper function to load device info.
  function deviceInfo(apiKey, deviceName) {
    const path = 'https://api.arable.cloud/api/v2/devices/' + deviceName;

    return fetch(path, {
      method: 'GET',
      headers: {
        'Accept': 'application/json',
        'Authorization': 'Apikey ' + apiKey,
      }
    })
      .then(response => response.json());
  }

  // Helper function to load latest device info.
  function latestDeviceData(apiKey, deviceName, units = {}) {

    // Hourly API data endpoint.
    let url = new URL('https://api.arable.cloud/api/v2/data/hourly');

    // Add params.
    url.searchParams.append('device', deviceName);
    url.searchParams.append('limit', '1');
    url.searchParams.append('order', 'desc');

    // Add params for configured units.
    for (let unit of Object.keys(units)) {
      url.searchParams.append(unit, units[unit]);
    }

    return fetch(url.toString(), {
      method: 'GET',
      headers: {
        'Accept': 'application/json',
        'Authorization': 'Apikey ' + apiKey,
      },
    })
      .then(response => response.json())
      .then(data => data.length ? data[0] : {});
  }
}(jQuery));
