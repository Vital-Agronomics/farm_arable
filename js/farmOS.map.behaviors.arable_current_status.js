(function ($) {
  farmOS.map.behaviors.arable_current_status = {
    attach: function (instance) {

      const devices = Drupal.settings.farm_map.behaviors.arable_current_status.devices;

      // Bail if no devices were provided.
      if (!devices) {
        return;
      }

      let promises = [];
      Object.values(devices).forEach( device => {
        const apiKey = device.sensor_settings.api_key;
        const deviceName = device.sensor_settings.device.name;
        promises.push(deviceInfo(apiKey, deviceName));
      });

      Promise.all(promises).then(devices => {

        // Build list of features.
        let features = [];
        devices.forEach(info => {

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

            // Add description HTML to geojson properties.
            info.description = description;

            // Add to list of features.
            features.push({
              type: "Feature",
              geometry: {type: "Point", coordinates: [gps[0], gps[1]]},
              properties: info,
            });
          }
        });

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
}(jQuery));
