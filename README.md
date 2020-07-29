# Farm Sensor Arable

This module integrates the [Arable Mark] with the [farmOS] Farm Sensor module.

Sensors added to farmOS are associated with the unique `Device Name` given to the Arable Mark
device. A default `apikey` can be configured and used with all sensors or a unique `apikey` can be
saved with individual sensors. See the [API Key Docs] for info on creating an Arable API key.

See the [Arable Developer Docs] and [Arable API v2 Docs] for more info on integrating with the
Arable API.

## Features:

* The sensor asset page displays the current status of an Arable Mark device including:
    - Device State (`active`, `inactive`, `dormant`, etc)
    - Signal Strength
    - Last Post
    - Last Seen
    - Battery Percentage
    - Battery Voltage

* The provided `arable_current_status` map behavior will display the sensor's current GPS location
on the map as a marker with the current temperature. This behavior is included on the sensor asset
map and dashboard map.

* Configurable units for converting data returned via the API. (See [Unit Conversion])

* The provided dashboard pane displays current "Device Stats" including:
    - Devices Syncing (`Active` vs `New`)
    - Devices Not Syncing (`Inactive` vs `Dormant`)
    - Battery (`<29%`, `30-59%`, `>60%`)

# Maintainers:

Current maintainers:
- Paul Weidner (paul121) - https://github.com/paul121

[Arable Mark]: https://shop.arable.com/
[farmOS]: https://github.com/farmos/farmos
[API Key Docs]: https://developer.arable.com/guide/authentication.html#api-keys
[Arable Developer Docs]: https://developer.arable.com/
[Arable API v2 Docs]: https://api.arable.cloud/api/v2/doc
[Unit Conversion]: https://developer.arable.com/guide/data.html#unit-conversion
