# CCAMS (Centralised code assignment and management system) server backend

This is the server backend for the CCAMS environment, holding the configuration of the transponder code ranges and groups, and serving as a central database to manage and assign transponder codes.

## Introduction
The server acts as the central interface to manage all transponder code assignments. It will evaluate any request received based on the configuration of code ranges and code groups. The main service user is the [EuroScope CCAMS plugin for VATSIM](https://github.com/kusterjs/CCAMS). But the service is open to any kind of plugin/(ATC) client. Please open an [issue](https://github.com/kusterjs/CCAMS-server/issues) if you want to add another client to use the service.

This server backend is used to:
* manage preferrential ranges for unique codes
* manage group codes (non-unique codes assigned to multiple aircraft)
* manage all requests for transponder codes at a central interface
* validate and verify requests based on different parameters
* keep a single list of reserved codes
* collect assigned transponder codes from different sources (vatsim-data, controllers sending a request)

You can review the current configuration status and the latest usage statistics on https://ccams.kilojuliett.ch/.

## Logic / Principle
In order to determine the most appropriate transponder code for a specific flight, the server will use information from the plugin sent including:
* the controller call sign
* origin
* destination
* flight rule
* aircraft position
* the controller connection type

### IFR Traffic
For IFR traffic, the server will identify the next available transponder code (ascending in the matching code range(s)) based on the airport (1st priority) and FIR (2nd priority) transponder code list, excluding:
* any non-discrete (ending with 00) codes
* any code already used by a pilot on the network (using the VATSIM JSON snapshot)
* any code already used reported by an incoming request
* any code assigned to an aircraft and reported by an incoming request

For departing aircraft, the [VATSpy](https://github.com/vatsimnetwork/vatspy-data-project) will be used to determine the FIR range if no airport range is defined. If the aiport and FIR transponder codes are all exhausted or if no matching entry in any of these lists can be found, a random transponder code outside the preferential range will be used.

### VFR Traffic
For VFR traffic, the server will identify an appropriate transponder code:
* from the code ranges with the condition 'VFR' (1st priority)
* from the group/area codes matching the provided conditions (2nd priority)

Group codes are intended to be assigned to multiple aircraft and can be restricted:
* by the controller call sign
* the origin of the aircraft
* the destination of the aircraft
* the position of the aircraft

Either a controller call sign restriction or position restriction is required at a minimum.

## Config Files
The [config](https://github.com/kusterjs/CCAMS-server/tree/main/config) files are intended to provide an easy way to edit them. A background task processes them afterwards to apply the changes to the assignment logic.

### DAT Files
DAT files are used for transponder code ranges. There are two files. The format is the same. The only difference is the order in which these entries are considered (airports 1st prio, FIR second prio).
The format is: `ATC unit or airport identifier`:`code range start`:`code range end`:`condition`
* `ATC unit or airport identifier`: ICAO designator matching the departure airport or the ATC call sign. FIR ranges should preferrably use 4 letters (unless the ATC positions use 3 letter identifiers), a partial match (up to the two first letters only will automatically be done during the search for a suitable range).
* `code range start`: 4 digit octal code for the first code of the range.
* `code range end`: 4 digit octal code for the last code of the range. As non-discrete codes will be disregarded when using codes from a transponder code range, multiple lines of definitions to specifically exclude non-discrete codes are not required. E.g. you can define a range from 1400 to 1777, but codes like 1400, 1500, 1600 etc. will be excluded in any case.
* `condition`: optional. Can be used to restrict the range to VFR traffic (use `VFR`) or by the arrival airport. Groups of arrival airport can be addressed by using only the common designator part as the restriction. E.g. a range of `LSAS` restricted to domestic flights would use the condition `LS`

### GEOJSON Files
GEOJSON files are used for transponder code groups to allow a simple geographical limit for defined codes. Such codes are not used for unique assignments, and therefore mostly used for VFR traffic to indicate contact with a specific controller and/or operating within a specific area. They may be known also as area codes. No specific file naming is required. All provided files will be processed in alphabetical order, allowing separate files being maintained for/by each vACC. `geometry` types `Polygon` or `MultiPolygon` are accepted. The only `CRS` currently allowed is `EPSG:4326` (`urn:ogc:def:crs:OGC:1.3:CRS84`). The following `properties` are processed:
* `squawk_code`: Mandatory. 4 digit octal code.
* `atc_callsign_match`: match required for the controller call sign. Multiple options can be provided separated by colon. E.g. `LSAS,LSAZ`
* `flight_rule`: `VFR` or `IFR`.
* `origin`: Origin airport ICAO designator, exact match required.
* `destination`: Destination airport ICAO designator, exact match required.

The `geometry` may be empty (`null`) if no specific geographical limitation is intended. But either a `geometry` or a property `atc_callsign_match` is required for a valid definition. Other properties are optional. Value `null` shall be used to indicate no restriction. Additional properties may be added to facilitate the editing/maintenance of the files, but will be disregarded for the processing on the CCAMS server.

## Changes / Improvements / Reports
To ensure compliance with regional and local transponder code ranges and schemes, local ops/tech staff may request a configuration change by:
* creating an [issue](https://github.com/kusterjs/CCAMS-server/issues) for this repo; or
* directly start a [pull request](https://github.com/kusterjs/CCAMS-server/pulls) by editing the files in the folder [config](https://github.com/kusterjs/CCAMS-server/tree/main/config) as required

The issue or pull requests will be closed upon implementation on the live environment.

Any other reports regarding functionalities or fixes are welcome!